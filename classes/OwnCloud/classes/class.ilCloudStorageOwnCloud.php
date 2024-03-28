<?php

// required??
require_once(__DIR__ . "/../vendor/autoload.php");
use ILIAS\DI\Container;

use \League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use \League\OAuth2\Client\Provider\GenericProvider;
use \League\OAuth2\Client\OptionProvider\PostAuthOptionProvider;

//Sn: the authService and afterAuth* functions into serviceGUI and interface

class ilCloudStorageOwnCloud implements ilCloudStorageServiceInterface
{
    public const SERVICE_ID = 'ocld';

    public const SERVICE_NAME = 'OwnCloud';
    
    public const INI_FILENAME = 'plugin';
    
    public const PLUGIN_PATH = './Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage';

    public const CALLBACK_URL = 'owncl_callback_url';
    
    public const AUTH_BEARER = 'owncl_access_token';

    public const OAUTH2_PROVIDER_OPTIONS = 'owncl_oauth2_provider_options';

    public const OAUTH2_TOKEN_REQUEST_AUTH = 'owncl_oauth2_token_request_auth';

    public const DEFAULT_COLLABORATION_APP_FORMATS = 'xls,xlsx,doc,docx,dot,dotx,odt,ott,rtf,txt,pdf,pdfa,html,epub,xps,djvu,djv,ppt,pptx';

    public const DEFAULT_WEBDAV_PATH = 'remote.php/webdav';

    public const DEFAULT_OAUTH2_PATH = 'index.php/apps/oauth2';

    public ?ilObjCloudStorage $object = null;

    public ?ilCloudStorageConfig $config = null;

    public ?Container $dic = null;

    public ?ilCloudStorageOwnCloudToken $user_token = null;

    public ?ilCloudStorageOwnCloudClient $owncl_client = null;

    public ?GenericProvider $oauth2_provider = null;

    public array $provider_options = [];

    private ?int $parent_ref_id = 0;

    public function __construct(int $a_ref_id, int $connId, int $a_parent_ref_id = 0)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->dic->logger()->root()->debug("ilCloudStorageOwnCloud __construct");
        $this->config = ilCloudStorageConfig::getInstance($connId);

        $this->object = new ilObjCloudStorage($a_ref_id);

        $this->parent_ref_id = $a_parent_ref_id;
        
        //$this->pluginIniSet = ilObjCloudStorage::setPluginIniSet($this->config);
        if ($this->config->getOAuth2Active()) {
            $this->provider_options = [
                        'clientId' => $this->config->getOAuth2ClientID(),
                        'clientSecret' => $this->config->getOAuth2ClientSecret(),
                        'redirect_uri' => self::getRedirectUri(),
                        'urlAuthorize' => $this->config->getFullOAuth2Path() . '/authorize',
                        'urlAccessToken' => $this->config->getFullOAuth2Path() . '/api/v1/token',
                        'urlResourceOwnerDetails' => $this->config->getFullOAuth2Path() . '/resource'
            ];
            $this->oauth2_provider = new GenericProvider($this->provider_options,['optionProvider' => $this->getOptionProvider($this->config->getOAuth2TokenRequestAuth())]);
        }
        $this->owncl_client = new ilCloudStorageOwnCloudClient($this);
    }

    public function getServiceId(): string
    {
        return self::SERVICE_ID;
    }

    public function getServiceName(): string
    {
        return self::SERVICE_NAME;
    }

    public static function getDefaultCollaborationAppFormats(): string
    {
        return self::DEFAULT_COLLABORATION_APP_FORMATS;
    }

    public static function getDefaultWebDavPath(): string
    {
        return self::DEFAULT_WEBDAV_PATH;
    }

    public static function getDefaultOAuth2Path(): string
    {
        return self::DEFAULT_OAUTH2_PATH;
    }

    /**
     * Authentication
     */
    public function authService(string $callback_url = ""): void
    {

        $this->dic->logger()->root()->debug("authService");
        if ($this->config->getOAuth2Active()) {
            try {
                $this->OAuth2Authenticate($callback_url);
            } catch(ilCloudStorageException $e) {
                $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage(), true);
            }
        } else {
            try {
                $this->basicAuthenticate($callback_url);
            } catch(ilCloudStorageException $e) {
                $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage(), true);
            }
        }
    }

    public function OAuth2Authenticate(string $callback_url): void 
    {
        $this->dic->logger()->root()->debug("OAuth2Authenticate");
        $this->checkAndRefreshAuthentication();
        if ($this->getToken()->getAccessToken() && $this->owncl_client->hasConnection()) {
            $this->dic->logger()->root()->debug("hasConnection");
            header("Location: " . htmlspecialchars_decode($callback_url));
        } else {
            $this->dic->logger()->root()->debug("no connection");
            if ($this->dic->user()->getId() != $this->object->getOwnerId()) {
                $this->dic->logger()->root()->debug("user differs");
                // Sn: ToDo language entry
                throw new ilCloudStorageException(ilCloudStorageException::AUTHENTICATION_FAILED, 'Der Ordner kann zur Zeit nur vom Besitzer geöffnet werden.');
            } else {
                ilSession::set(self::CALLBACK_URL, ilObjCloudStorage::getHttpPath() . $callback_url);
                ilSession::set(self::OAUTH2_PROVIDER_OPTIONS, $this->provider_options);
                ilSession::set(self::OAUTH2_TOKEN_REQUEST_AUTH, $this->config->getOAuth2TokenRequestAuth());
                $this->oauth2_provider->authorize(array('redirect_uri' => self::getRedirectUri()));
            }
        }
    }

    public function basicAuthenticate(): void 
    {
        $this->dic->logger()->root()->debug("basicAuthenticate");
        // Sn: ToDo
    }

    public function afterAuthService(): void
    {
        $this->dic->logger()->root()->debug("afterAuthService");
        //$this->dic->ctrl()->setCmd('edit');

        $root_folder = $this->object->getRootFolder();

        if (!$this->getClient()->folderExists($root_folder)) {
            $this->createFolder($root_folder);
        }
        if ($this->config->getOAuth2Active()) {
            $this->OAuth2AfterAuthentication();
        } else {
            $this->basicAfterAuthentication();
        }
        ilSession::clear(self::CALLBACK_URL);
        ilSession::clear(self::OAUTH2_PROVIDER_OPTIONS);
        ilSession::clear(self::OAUTH2_TOKEN_REQUEST_AUTH);
        $this->dic->ctrl()->redirectToURL($this->dic->ctrl()->getLinkTargetByClass(array('ilObjCloudStorageGUI'), "editProperties"));
    }

    public function OAuth2AfterAuthentication(): void
    {
        $this->dic->logger()->root()->debug("OAuth2AfterAuthentication");
        if (!$this->owncl_client->hasConnection()) {
            $token = unserialize(ilSession::get(self::AUTH_BEARER));
            $this->getToken()->storeUserToken($token,$this->object->getConnId());
        }

        // since the auth token are per user and not per object,
        // all objects of this user have to be marked as authenticated

        // Sn: ToDo check if this is ok: change to all ref_ids from all obj_ids 
        // because Plugin Objects are instantiated with ref_id only
        
        foreach ($this->object->getAllWithSameOwnerAndConnection() as $obj_id) {
            $ref_ids = ilObject::_getAllReferences($obj_id);
            foreach ($ref_ids as $ref_id) {
                $obj = new ilObjCloudStorage($ref_id);
                $obj->setAuthComplete(true);
                $obj->update();
            }
        }
    }

    public function basicAfterAuthentication(): void
    {
        $this->dic->logger()->root()->debug("basicAfterAuthentication");
        //Sn: ToDo
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

    public function getHeaders(): array
    {
        $this->dic->logger()->root()->debug("getHeaders");
        if ($this->config->getOAuth2Active()) {
            return array('Authorization' => 'Bearer ' . $this->getToken()->getAccessToken());
        } else {
            return array(
                'Authorization' => 'Basic ' . base64_encode($this->object->getUsername() . ':' . $this->object->getPassword())
            );
        }
    }

    public function getClientSettings(): array
    {
        $this->dic->logger()->root()->debug("getClientSettings");
        if ($this->config->getOAuth2Active()) {
            if ($this->config->getProxyURL() != '') {
                return array(
                    'baseUri' => $this->config->getFullWebDAVPath(),
                    'proxy'   => $this->config->getProxyURL(),
                );
            } else {
                return array(
                    'baseUri' => $this->config->getFullWebDAVPath(),
                );
            }
        } else {
            if ($this->config->getProxyURL() != '') {
                return array(
                    'baseUri'  => $this->config->getFullWebDAVPath(),
                    'userName' => $this->object->getUsername(),
                    'password' => $this->object->getPassword(),
                    'proxy'    => $this->config->getProxyURL(),
                );
            } else {
                return array(
                    'baseUri'  => $this->config->getFullWebDAVPath(),
                    'userName' => $this->object->getUsername(),
                    'password' => $this->object->getPassword(),
                );
            }
        }
    }

    public function getToken(): ilCloudStorageOwnCloudToken
    {
        $this->dic->logger()->root()->debug("getToken");
        if (!$this->user_token) {
            $this->loadToken();
        }
        //$this->dic->logger()->root()->debug(var_export($this->user_token,true));
        return $this->user_token;
    }

    public function loadToken(): void
    {
        $this->dic->logger()->root()->debug("loadToken");
        //global $ilUser;
        // at object creation, the object and owner id does not yet exist, therefore we take the current user's id
        //$this->user_token = ilCloudStorageOwnCloudToken::getUserToken($ilOwnCloud ? $ilOwnCloud->object->getOwnerId() : $ilUser->getId());
        assert($this->object instanceof ilObjCloudStorage);
        $this->user_token = ilCloudStorageOwnCloudToken::getUserToken($this->object->getConnId(), $this->object->getOwnerId());
    }

    public static function storeTokenToSession(League\OAuth2\Client\Token\AccessToken $access_token): void
    {
        global $DIC;
        $DIC->logger()->root()->debug("storeTokenToSession");
        ilSession::set(self::AUTH_BEARER, serialize($access_token));
    }


    protected function loadTokenFromSession(): League\OAuth2\Client\Token\AccessToken
    {
        $this->dic->logger()->root()->debug("loadTokenFromSession");
        return unserialize(ilSession::get(self::AUTH_BEARER));
    }

    public function checkAndRefreshAuthentication(): bool
    {
        $this->dic->logger()->root()->debug("checkAndRefreshAuthentication");

        if (!$this->getToken()->getAccessToken() && !$this->getToken()->getRefreshToken()) {
            $this->dic->logger()->root()->debug("No access or refresh token found for user with id " . $this->getToken()->getUserId());
            return false;
        }

        if ($this->getToken()->isExpired()) {
            $atom_query = $this->dic->database()->buildAtomQuery();
            $atom_query->addTableLock(ilCloudStorageOwnCloudToken::DB_TABLE_NAME);
            $atom_query->addQueryCallable(function (ilDBInterface $ilDB) {
                $this->loadToken(); // reload token and check again inside table lock to prevent race condition
                if (!$this->getToken()->isExpired()) {
                    return true;
                }
                $refresh_token = $this->getToken()->getRefreshToken();
                try {
                    $this->refreshToken();
                    $msg = 'Token successfully refreshed for user with id ' . $this->getToken()->getUserId() . ' with refresh token ' . $refresh_token;
                    $this->dic->logger()->root()->debug($msg);
                    return true;
                } catch (Exception $e) {
                    $msg = 'Exception: Token refresh for user with id ' . $this->getToken()->getUserId()
                    . ' and refresh token ' . $refresh_token
                    . ' failed with message: ' . $e->getMessage();
                    $this->dic->logger()->root()->debug($msg);
                    return false;
                }
            });
            $atom_query->run();
        }
        return true;
    }

    /**
     * @throws ilCloudStorageException
     */
    public function checkConnection(): void
    {
        $this->dic->logger()->root()->debug("checkConnection");

        $status = $this->getClient()->getHTTPStatus();
        
        // unauthorized
        if ($status == 401) {
            throw new ilCloudStorageException(ilCloudStorageException::NOT_AUTHORIZED);
        }

        // everything else
        if ($status > 401) {
            throw new ilCloudStorageException(ilCloudStorageException::NO_CONNECTION);
        }
    }

    public function refreshToken(): void
    {
        $this->dic->logger()->root()->debug("refreshToken");
        $this->getToken()->storeUserToken($this->oauth2_provider->getAccessToken('refresh_token', array(
            'refresh_token' => $this->getToken()->getRefreshToken()
        )),$this->object->getConnId());
    }

    public static function getRedirectUri(): string
    {
        return ilObjCloudStorage::getHttpPath() . 'Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/OwnCloud/redirect.php';
    }

    public static function redirectToObject(): void
    {
        global $DIC;
        $DIC->logger()->root()->debug("redirectToObject");
        $code = $DIC->http()->wrapper()->query()->retrieve(
            "code",
            $DIC->refinery()->to()->string()
        );
        $oauth2_provider_options = ilSession::get(self::OAUTH2_PROVIDER_OPTIONS);
        $option_provider = self::getOptionProvider(ilSession::get(self::OAUTH2_TOKEN_REQUEST_AUTH));
        $oauth2_provider = new GenericProvider($oauth2_provider_options,['optionProvider' => $option_provider]);
        self::storeTokenToSession($oauth2_provider->getAccessToken('authorization_code', array(
            'code'         => $code,
            'redirect_uri' => self::getRedirectUri()
        )));
        $DIC->ctrl()->redirectToURL(ilSession::get(self::CALLBACK_URL));
    }

    /**
     * Tree
     */
    public function getRootId(string $root_path): string 
    {
        return "root";
    }

    public function addToFileTree(ilCloudStorageFileTree $file_tree, string $parent_folder = "/"): void
    {
        $this->dic->logger()->root()->debug("addToFileTree");
        $files = $this->getClient()->listFolder($parent_folder);
        foreach ($files as $k => $item) {
            $this->dic->logger()->root()->debug("files...");
            $size = ($item instanceof ilCloudStorageOwnCloudFile) ? $size = $item->getSize() : null;
            $is_dir = $item instanceof ilCloudStorageOwnCloudFolder;
            $file_tree->addNode($item->getFullPath(), $item->getId(), $is_dir, strtotime($item->getDateTimeLastModified()), $size);
        }
    }

    public function addToFileTreeById(ilCloudStorageFileTree $file_tree, $id): bool 
    {
        $this->dic->logger()->root()->debug("addToFileTreeById");
        return false;
    }

    public function getFile(string $path = "", ?ilCloudStorageFileTree $file_tree = null): void
    {
        $this->getClient()->deliverFile($path);
    }

    public function getFileById(string $id): bool 
    {
        return false;
    }

    public function createFolder(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        if ($file_tree instanceof ilCloudStorageFileTree) {
            $path = ilCloudStorageUtil::joinPaths($file_tree->getRootPath(), $path);
        }

        if ($path != '/' && !$this->getClient()->folderExists($path)) {
            $this->getClient()->createFolder($path);
        }
    }

    public function createFolderById(string $id, string  $folder_name) : string
    {
        $path = $this->idToPath($id, $folder_name);

        $this->createFolder($path);

        return $this->pathToId($path);
    }

    public function putFile(string $tmp_name, string $file_name, string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        $path = ilCloudStorageUtil::joinPaths($file_tree->getRootPath(), $path);
        if ($path == '/') {
            $path = '';
        }
        $this->getClient()->uploadFile($path . "/" . $file_name, $tmp_name);
    }

    public function putFileById($tmp_name, $file_name, $id): bool
    {
        return false;
    }

    public function deleteItem(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        $path = ilCloudStorageUtil::joinPaths($file_tree->getRootPath(), $path);
        $this->getClient()->delete($path);
    }

    public function deleteItemById(string $id): bool
    {
        return false;
    }
    
    public function isCaseSensitive(): bool
    {
        return true;
    }

    public function getClient(): ilCloudStorageOwnCloudClient
    {
         return $this->owncl_client;
    }

    protected function idToPath(string $id, string $folder_name = '') : string
    {
        $path = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId())->getNodeFromId($id)->getPath();

        if (!empty($folder_name)) {
            $path = ilCloudStorageUtil::joinPaths($path, $folder_name);
        }

        return $path;
    }

    protected function pathToId(string $path) : string
    {
        $node = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId())->getNodeFromPath($path);

        if ($node === null) {
            $id = $this->getClient()->pathToId($path);

            $node = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId())->addNode($path, $id, true);
        }

        return $node->getId();
    }

    private function getParentRefId(): int {
        return ($this->parent_ref_id != 0) ? $this->parent_ref_id : $this->dic['tree']->getParentId($this->object->getRefId());
    }
}
