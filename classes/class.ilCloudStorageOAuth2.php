<?php

declare(strict_types=1);

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use \League\OAuth2\Client\Provider\GenericProvider;
use \League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use \League\OAuth2\Client\OptionProvider\PostAuthOptionProvider;


use Sabre\DAV\Client;

/**
 * Class ilCloudStorageOAuth2
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 */
class ilCloudStorageOAuth2
{

    public const SESSION_CALLBACK_URL = 'oauth2_callback_url';
    
    public const SESSION_AUTH_BEARER = 'oauth2_access_token';

    public const SESSION_AUTH_BASIC = 'oauth2_user_account';

    public const SESSION_CONN_ID= 'oauth2_conn_id';

    public const SESSION_LAST_CMD = 'oauth2_last_cmd';

    //public const SESSION_OAUTH2_PROVIDER_OPTIONS = 'oauth2_provider_options';

    public const SESSION_OAUTH2_TOKEN_REQUEST_AUTH = 'oauth2_token_request_auth';

    const DB_TABLE_NAME = 'rep_robj_xcls_oauth2';
   
    private ?int $conn_id = 0;

    private ?int $user_id = 0;
    
    private ?string $access_token = '';

    private ?string $refresh_token = '';
    
    private ?int $valid_through = 0;

    // object model
    public function isExpired(): bool
    {
        return ((int) $this->getValidThrough() != 0) && ($this->getValidThrough() <= time());
    }

    public function setConnId(int $conn_id): void
    {
        $this->conn_id = $conn_id;
    }

    public function getConnId(): int
    {
        return $this->conn_id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getAccessToken(): string
    {
        return $this->access_token;
    }

    public function setAccessToken(string $access_token): void
    {
        $this->access_token = $access_token;
    }

    public function getRefreshToken(): string
    {
        return $this->refresh_token;
    }

    public function setRefreshToken(string $refresh_token): void
    {
        $this->refresh_token = $refresh_token;
    }

    public function getValidThrough(): int
    {
        return $this->valid_through;
    }

    public function setValidThrough(int $valid_through): void
    {
        $this->valid_through = $valid_through;
    }

    // object functions
    private function store(): void {
        global $DIC;
        $query = $DIC->database()->query("SELECT user_id FROM " . self::DB_TABLE_NAME . " WHERE conn_id = " . $this->getConnId() . " AND user_id = " . $this->getUserId());
        $ret = $DIC->database()->fetchAssoc($query);
        if (!is_null($ret)) {
            $DIC->database()->manipulateF(
                'UPDATE ' . self::DB_TABLE_NAME . ' SET access_token = %s, refresh_token = %s, valid_through = %s WHERE conn_id = %s AND user_id = %s',
                array('text', 'text', 'integer', 'integer', 'integer'),
                array($this->getAccessToken(), $this->getRefreshToken(), $this->getValidThrough(), $this->getConnId(), $this->getUserId())
            );
        } else {
            $DIC->database()->manipulateF(
                'INSERT INTO ' . self::DB_TABLE_NAME . ' (conn_id, user_id, access_token, refresh_token, valid_through) VALUES (%s, %s, %s, %s, %s)',
                array('integer', 'integer', 'text', 'text', 'integer'),
                array($this->getConnId(), $this->getUserId(), $this->getAccessToken(), $this->getRefreshToken(), $this->getValidThrough())
            );
        }
    }
    
    public function storeUserToken(League\OAuth2\Client\Token\AccessToken $token, int $conn_id): void
    {
        $this->setConnId($conn_id);
        $this->setAccessToken($token->getToken());
        $this->setRefreshToken($token->getRefreshToken());
        $this->setValidThrough($token->getExpires());
        $this->store();
    }

    public static function getUserToken(int $conn_id, int $user_id = 0): ilCloudStorageOAuth2
    {
        global $DIC;
        if ($user_id == 0) {
            global $ilUser;
            $user_id = $ilUser->getId();
        }
        $query = $DIC->database()->query("SELECT * FROM " . self::DB_TABLE_NAME . " WHERE conn_id = " . $conn_id . " AND user_id = " . $user_id);
        $ret = $DIC->database()->fetchAssoc($query);
        if (is_null($ret)) {
            $token = new self();
            $token->setConnId($conn_id);
            $token->setUserId($user_id);
        } else {
            $token = new self();
            $token->setConnId($conn_id);
            $token->setUserId($user_id);
            $token->setAccessToken($ret['access_token']);
            $token->setRefreshToken($ret['refresh_token']);
            $token->setValidThrough($ret['valid_through']);
        }
        return $token;
    }

    public static function deleteUserToken(int $conn_id, int $user_id = 0): void
    {
        global $DIC;
        if ($user_id == 0) {
            global $ilUser;
            $user_id = $ilUser->getId();
        }
        $DIC->database()->manipulate("DELETE FROM " . self::DB_TABLE_NAME . " WHERE conn_id = " . $conn_id . " AND user_id = " . $user_id);
    }

    // static oauth specific functions
    public static function checkAndRefreshAuthentication(int $user_id, ilCloudStorageConfig $config): bool
    {
        global $DIC;
        $DIC->logger()->root()->debug("checkAndRefreshAuthentication");
        $conn_id = $config->getConnId();

        $token = self::getUserToken($conn_id, $user_id);
        if (!$token->getAccessToken() && !$token->getRefreshToken()) {
            $DIC->logger()->root()->debug("No access or refresh token found for user with id " . $token->getUserId());
            return false;
        } else {
            if ($token->isExpired()) {
                $atom_query = $DIC->database()->buildAtomQuery();
                $atom_query->addTableLock(ilCloudStorageOAuth2::DB_TABLE_NAME);
                $atom_query->addTableLock("rep_robj_xcls_conn");
                $atom_query->addQueryCallable(function (ilDBInterface $ilDB) use ($DIC, $conn_id, $user_id, $config) {
                    $token = ilCloudStorageOAuth2::getUserToken($conn_id, $user_id); // reload token and check again inside table lock to prevent race condition
                    if (!$token->isExpired()) {
                        return true;
                    }
                    $refresh_token = $token->getRefreshToken();
                    try {
                        self::refreshToken($conn_id,$user_id, $config);
                        $msg = 'Token successfully refreshed for user with id ' . $token->getUserId() . ' with refresh token ' . $refresh_token;
                        $DIC->logger()->root()->debug($msg);
                        return true;
                    } catch (Exception $e) {
                        $msg = 'Exception: Token refresh for user with id ' . $token->getUserId()
                        . ' and refresh token ' . $refresh_token
                        . ' failed with message: ' . $e->getMessage();
                        $DIC->logger()->root()->debug($msg);
                        return false;
                    }
                });
                $atom_query->run();
            } else {
                return true;
            }
        }
        return true;
    }

    public static function refreshToken(int $conn_id, int $user_id, ilCloudStorageConfig $config): void
    {
        $token = self::getUserToken($conn_id, $user_id);
        $provider = self::getOAuth2Provider($config);
        $token->storeUserToken($provider->getAccessToken('refresh_token', array(
            'refresh_token' => $token->getRefreshToken()
        )),$config->getConnId());
    }

    public static function getRedirectUri(int $conn_id): string
    {
        // for compatibiliy with old client registrations
        $config = ilCloudStorageConfig::getInstance($conn_id);
        $serviceName = ilCloudStorageConfig::AVAILABLE_FS_CONN[$config->getServiceId()];
        $plugin_path = ilObjCloudStorage::getHttpPath() . 'Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage';
        // old client registration
        if (file_exists(__DIR__ . '/' . $serviceName . '/redirect.php')) {
            return $plugin_path . '/classes/' . $serviceName . '/redirect.php';
        } else { // new services
            return $plugin_path . '/redirect.php?conn_id=' . (string) $conn_id;
        }
    }

    public static function getOptionProvider(string $oAuth2TokenRequestAuth)
    {
        switch ($oAuth2TokenRequestAuth) {
            case ilCloudStorageConfig::POST_BODY:
                return new PostAuthOptionProvider();
            case ilCloudStorageConfig::HEADER:
            default:
                return new HttpBasicAuthOptionProvider();
        }
    }

    public static function getOAuth2ProviderOptions(ilCloudStorageConfig $config): array {
        return [
            'clientId' => $config->getOAuth2ClientID(),
            'clientSecret' => $config->getOAuth2ClientSecret(),
            'redirectUri' => self::getRedirectUri($config->getConnId()),
            'urlAuthorize' => $config->getFullOAuth2Path() . '/authorize',
            'urlAccessToken' => $config->getFullOAuth2Path() . '/api/v1/token',
            'urlResourceOwnerDetails' => $config->getFullOAuth2Path() . '/resource'
        ];
    }

    public static function getOAuth2Provider(ilCloudStorageConfig $config): GenericProvider {
        global $DIC;
        $options = self::getOAuth2ProviderOptions($config);
        //$DIC->logger()->root()->debug(var_export($options, true));
        return new GenericProvider($options,['optionProvider' => self::getOptionProvider($config->getOAuth2TokenRequestAuth())]);
    }


    public static function getHeaders(int $conn_id, int $user_id): array
    {
        $token = self::getUserToken($conn_id, $user_id);
        return array('Authorization' => 'Bearer ' . $token->getAccessToken());
    }

    // generic auth connection functions
    public static function checkConnection(int $conn_id, int $user_id, ilCloudStorageConfig $config): void
    {
        $status = self::getHTTPStatus($conn_id, $user_id, $config);
        
        // unauthorized
        if ($status == 401) {
            throw new ilCloudStorageException(ilCloudStorageException::NOT_AUTHORIZED);
        }

        // everything else
        if ($status > 401) {
            throw new ilCloudStorageException(ilCloudStorageException::NO_CONNECTION);
        }
    }

    public static function getHTTPStatus(int $conn_id, int $user_id, ilCloudStorageConfig $config): int
    {
        global $DIC;
        try {
            $client = new Client(self::getClientSettings($config));
            $response = $client->request('PROPFIND', '', null, self::getHeaders($conn_id, $user_id));
        } catch (Exception $e) {
            $DIC->logger()->root()->error($e->getMessage());
            throw new ilCloudStorageException(ilCloudStorageException::NO_CONNECTION, $e->getMessage());
            return -1;
        }
        return $response['statusCode'];
    }

    public static function getClientSettings(ilCloudStorageConfig $config): array
    {
        if ($config->getProxyURL() != '') {
            return array(
                'baseUri' => $config->getFullWebDAVPath(),
                'webDavPath' => $config->getWebDavPath(),
                'proxy'   => $config->getProxyURL(),
            );
        } else {
            return array(
                'baseUri' => $config->getFullWebDAVPath(),
                'webDavPath' => $config->getWebDavPath(),
            );
        }
    }

    public static function Authenticate(int $user_id, ilCloudStorageConfig $config): void 
    { 
        global $DIC;
        $DIC->logger()->root()->debug("OAuth2: Authenticate");
        $serviceName = ilCloudStorageConfig::AVAILABLE_FS_CONN[$config->getServiceId()];
        $redirectURI = self::getRedirectUri($config->getConnId());
        $DIC->ctrl()->setParameterByClass('ilObjCloudStorageGUI','cmd','afterServiceAuth');
        $DIC->ctrl()->setParameterByClass('ilObjCloudStorageGUI','auth_mode','true');
        $callbackUrl = $DIC->ctrl()->getLinkTargetByClass('ilObjCloudStorageGUI');
        $DIC->logger()->root()->debug($callbackUrl);
        if (!self::checkAndRefreshAuthentication($user_id, $config)) {
            $DIC->logger()->root()->debug("checkAndRefreshAuthentication failed");
            $provider = self::getOAuth2Provider($config);
            ilSession::set(self::SESSION_CALLBACK_URL, ilObjCloudStorage::getHttpPath() . $callbackUrl);
            ilSession::set(self::SESSION_CONN_ID, $config->getConnId());
            $provider->authorize(array('redirect_uri' => $redirectURI));
        } else {
            $DIC->logger()->root()->debug("hasConnection");
            header("Location: " . htmlspecialchars_decode($callbackUrl));
        }
    }

    public static function getSessionName(string $session_name): string {
        return $session_name;
    }

    public static function redirect(): void {
        global $DIC;
        $DIC->logger()->root()->debug("redirect");
        try {
            $code = $DIC->http()->wrapper()->query()->retrieve(
                "code",
                $DIC->refinery()->to()->string()
            );
            $conn_id = ilSession::get(self::SESSION_CONN_ID);
            $config = ilCloudStorageConfig::getInstance((int) $conn_id);
            $oauth2_provider = self::getOAuth2Provider($config);
            self::storeTokenToSession($oauth2_provider->getAccessToken('authorization_code', array(
                'code'         => $code,
                'redirect_uri' => self::getRedirectUri($conn_id)
            )));
            $DIC->ctrl()->redirectToURL(ilSession::get(self::SESSION_CALLBACK_URL));
        } 
        catch (Exception $e) 
        {
            $DIC->logger()->root()->error($e->getMessage());
        }
    }

    public static function storeTokenToSession(League\OAuth2\Client\Token\AccessToken $access_token): void
    {
        global $DIC;
        $DIC->logger()->root()->debug("storeTokenToSession");
        ilSession::set(self::SESSION_AUTH_BEARER, serialize($access_token));
    }


    protected function loadTokenFromSession(): League\OAuth2\Client\Token\AccessToken
    {
        global $DIC;
        $DIC->logger()->root()->debug("loadTokenFromSession");
        return unserialize(ilSession::get(self::SESSION_AUTH_BEARER));
    }
}