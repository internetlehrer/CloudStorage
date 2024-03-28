<?php

use ILIAS\DI\Container;

/**
 * CloudStorage configuration class
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 * @version $Id$
 *
 */
class ilCloudStorageConfig
{

    const POST_BODY = 'post_body';
    const HEADER = 'header';
    
    #region PROPERTIES
    public const PLUGIN_ID = 'xcls';
    public const AVAILABLE_FS_CONN = [
        'ocld'		=> 'OwnCloud',
        'ncld'      => 'NextCloud'
    ];
    public const AVAILABLE_XCLS_SERVICES = [
        'ocld'     => 'ilCloudStorageOwnCloud'
    ];
    
    public const AVAILABILITY_NONE = 0;  // Type is not longer available (error message)
    public const AVAILABILITY_EXISTING = 1; // Existing objects of the can be used, but no new created
    public const AVAILABILITY_CREATE = 2;  // New objects of this type can be created

    public const FS_RELATED_FUNCTION = [];

    private Container $dic;
    private ilDBInterface $db;

    private static ?ilCloudStorageConfig $instance = null;

    private ?int $connId = null;
    private string $title = '';
    private int $availability = 0;
    private string $hint = '';
    
    private bool $account = false;
    private string $accountUsername = '';
    private string  $accountPassword = '';
    private string $baseDirectory = '/ILIASshare';
    private bool $baseDirectoryAllowOverride = false;
    private bool $collaborationAppIntegration = false;
    private string $collaborationAppFormats = '';
    #'xls,xlsx,doc,docx,dot,dotx,odt,ott,rtf,txt,pdf,pdfa,html,epub,xps,djvu,djv,ppt,pptx';
    private string $collaborationAppMappingField = 'login';
    private string $collaborationAppUrl = '';
    private bool $oauth2Active = true;
    private string $oauth2ClientId = '';
    private string $oauth2ClientSecret = '';
    private string $oauth2Path = '';
    private string $oauth2TokenRequestAuth = 'header';
    private string $serverUrl = '';
    private string $webdavUrl = '';
    private string $proxyUrl = '';
    private string $webdavPath = '';

    public object $option;

    private array $objConfigAvailSetting = [
        'ocld'   => []
    ];

    private string $objIdsSpecial = '';
    private ?string $serviceId = null;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?string $authMethod = '';
    #endregion PROPERTIES

    #region INIT READ WRITE

    public function getObjConfigAvailSetting(string $service = ''): array
    {
        if(!(bool)$service) {
            return $this->objConfigAvailSetting;
        }

        return $this->objConfigAvailSetting[$service];

    }

    public function __construct(?int $connId = null)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->db = $this->dic->database();
        if(!is_null($connId)) {
            $this->read($connId);
        }
    }

    /**
     * Get singleton instance
     *
     * @param int|null $connId
     * @return ilCloudStorageConfig
     */
    public static function getInstance(?int $connId = null): self
    {
        if(self::$instance instanceof self && is_null($connId)) {
            return self::$instance;
        }
        return self::$instance = new ilCloudStorageConfig($connId);
    }

    public function create(): void
    {
        $this->save(false);
    }

    public function save(bool $update = true): void
    {
        $ilDB = $this->db;

        $a_data=array(
            'title'                     => ['text', $this->getTitle()],
            'hint'		                => ['text', $this->getHint()],
            'availability'		        => ['integer', $this->getAvailability()],
            'account'                   => ['integer', $this->ilBoolToInt($this->getAccount())],
            'account_username'          => ['text', $this->getAccountUsername()],
            'account_password'          => ['text', $this->getAccountPassword()],
            'base_directory'            => ['text', $this->getBaseDirectory()],
            'bd_allow_override'         => ['integer', $this->ilBoolToInt($this->getBaseDirectoryAllowOverride())],
            'col_app_integration'      => ['integer', $this->ilBoolToInt($this->getCollaborationAppIntegration())],
            'col_app_formats'          => ['text', $this->getCollaborationAppFormats()],
            'col_app_mapping_field'    => ['text', $this->getCollaborationAppMappingField()],
            'col_app_url'              => ['text', $this->getCollaborationAppUrl()],
            'oa2_active'                => ['integer', $this->ilBoolToInt($this->getOAuth2Active())],
            'oa2_client_id'             => ['text', $this->getOAuth2ClientId()],
            'oa2_client_secret'         => ['text', $this->getOAuth2ClientSecret()],
            'oa2_path'                  => ['text', $this->getOAuth2Path()],
            'oa2_token_request_auth'    => ['text', $this->getOAuth2TokenRequestAuth()],
            'server_url'                => ['text', $this->getServerURL()],
            'proxy_url'                 => ['text', $this->getProxyURL()],
            'webdav_url'                => ['text', $this->getWebDavURL()],
            'obj_ids_special'			=> array('text',$this->getObjIdsSpecial()),
            'service_id'			    => ['string', $this->getServiceId()],
            'webdav_path'               => ['text', $this->getWebDavPath()],
            'access_token'              => ['string', $this->getAccessToken()],
            'refresh_token'             => ['string', $this->getRefreshToken()],
            'auth_method'               => ['string', $this->getAuthMethod()]
        );

        $result = $ilDB->query("SELECT * FROM rep_robj_xcls_conn");
        $numConn = $ilDB->numRows($result);

        if(!$update) {
            $result = $ilDB->query("SELECT MAX(id) id FROM rep_robj_xcls_conn");
            $row = $ilDB->fetchObject($result);
            $connId = (bool)$numConn ? (int)$row->id + 1 : 1;
            $this->setConnId($connId);
        }
        if(!$update || $numConn === 0) {
            $a_data['id'] = array('integer', $this->getConnId());
            $ilDB->insert('rep_robj_xcls_conn', $a_data);
        } else {
            $ilDB->update('rep_robj_xcls_conn', $a_data, array('id' => array('integer', $this->getConnId())));
        }
    }

    public function setDefaultValues(array $exclude = ['obj_ids_special', 'service_id']): void
    {
        $this->account = false;
        $this->accountUsername = '';
        $this->accountPassword = '';
        $this->baseDirectory = '/ILIASshare';
        $this->baseDirectoryAllowOverride = false;
        $this->collaborationAppIntegration = false;
        $this->collaborationAppFormats = $this->getCollaborationAppFormats(true);
        $this->collaborationAppMappingField = 'login';
        $this->collaborationAppUrl = '';
        $this->oauth2Active = false;
        $this->oauth2ClientId = '';
        $this->oauth2ClientSecret = '';
        $this->oauth2Path = $this->getOAuth2Path(true);
        $this->oauth2TokenRequestAuth = 'header';
        $this->objIdsSpecial = (false !== array_search('obj_ids_special', $exclude)) ? $this->getObjIdsSpecial() : '';
        $this->serverUrl = '';
        $this->webdavUrl = '';
        $this->proxyUrl = $this->getProxyURL(true);
        $this->webdavPath = $this->getWebDavPath(true);
    }

    /**
     * @param int $connId
     * @param string|null $type
     * @return array|string|null
     */
    public function getTokenFromDb(int $connId, string $type = null)
    {
        $result = $this->db->query("SELECT access_token, refresh_token FROM rep_robj_xcls_conn WHERE" .
            " id =" . $this->db->quote($connId, 'integer'));
        while ($record = $this->db->fetchAssoc($result)) {
            return is_null($type) ? $record : $record[$type . '_token'];
        }
        return null;
    }

    public function read(int $connId): void
    {
        $ilDB = $this->db;
        $result = $ilDB->query("SELECT * FROM rep_robj_xcls_conn WHERE id =" . $ilDB->quote($connId, 'integer'));
        while ($record = $ilDB->fetchAssoc($result)) {
            $this->setConnId($record["id"]);
            $this->setTitle($record["title"]);
            $this->setHint("" . $record["hint"]);
            $this->setAvailability((int) $record["availability"]);
            $this->setAccount($this->ilIntToBool($record["account"]));
            $this->setAccountUsername($record["account_username"]);
            $this->setAccountPassword($record["account_password"]);
            $this->setBaseDirectory($record["base_directory"]);
            $this->setBaseDirectoryAllowOverride($this->ilIntToBool($record["bd_allow_override"]));
            $this->setCollaborationAppIntegration($this->ilIntToBool($record["col_app_integration"]));
            $this->setCollaborationAppFormats($record["col_app_formats"]);
            $this->setCollaborationAppMappingField($record["col_app_mapping_field"]);
            $this->setCollaborationAppUrl($record["col_app_url"]);
            $this->setOAuth2Active($this->ilIntToBool($record["oa2_active"]));
            $this->setOauth2ClientId($record["oa2_client_id"]);
            $this->setOauth2ClientSecret($record["oa2_client_secret"]);
            $this->setOauth2Path($record["oa2_path"]);
            $this->setOauth2TokenRequestAuth($record["oa2_token_request_auth"]);
            $this->setObjIdsSpecial($record["obj_ids_special"]);
            $this->setServerUrl($record["server_url"]);
            $this->setProxyUrl($record["proxy_url"]);
            $this->setWebDavUrl($record["webdav_url"]);
            $this->setWebDavPath($record["webdav_path"]);
            $this->setServiceId($record["service_id"]);
            $this->setAccessToken($record["access_token"]);
            $this->setRefreshToken($record["refresh_token"]);
            $this->setAuthMethod($record["auth_method"]);
        }
    }

    #endregion INIT READ WRITE

    #region GETTER & SETTER

    public function getConnId(): ?int
    {
        return $this->connId;
    }

    public function setConnId(?int $connId): void
    {
        $this->connId = $connId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getHint(): string
    {
        return $this->hint;
    }

    public function setHint(string $hint): void
    {
        $this->hint = $hint;
    }

    public function getAvailability(): int
    {
        return $this->availability;
    }

    public function setAvailability(int $availability): void
    {
        $this->availability = $availability;
    }

    public function getAccount(): bool
    {
        return $this->account;
    }

    public function setAccount(bool $account): void
    {
        $this->account = $account;
    }
    
    public function getAccountUsername(): string
    {
        return $this->accountUsername;
    }

    public function setAccountUsername(string $accountUsername): void
    {
        $this->accountUsername = $accountUsername;
    }

    public function getAccountPassword(): string
    {
        return $this->accountPassword;
    }

    public function setAccountPassword(string $accountPassword): void
    {
        $this->accountPassword = $accountPassword;
    }

    public function getBaseDirectory(): string
    {
        return $this->baseDirectory;
    }

    public function setBaseDirectory(string $baseDirectory): void
    {
        $this->baseDirectory = $baseDirectory;
    }

    public function getBaseDirectoryAllowOverride(): bool
    {
        return $this->baseDirectoryAllowOverride;
    }

    public function setBaseDirectoryAllowOverride(bool $baseDirectoryAllowOverride): void
    {
        $this->baseDirectoryAllowOverride = $baseDirectoryAllowOverride;
    }

    public function getCollaborationAppIntegration(): bool
    {
        return $this->collaborationAppIntegration;
    }

    public function setCollaborationAppIntegration(bool $collaborationAppIntegration): void
    {
        $this->collaborationAppIntegration = $collaborationAppIntegration;
    }

    public function getCollaborationAppFormats(bool $return_default = false): string
    {
        $value = $this->collaborationAppFormats;
        $serviceClass = ilCloudStorageConfig::AVAILABLE_XCLS_SERVICES[$this->getServiceId()];
        return (!$value && $return_default) ? $serviceClass::getDefaultCollaborationAppFormats() : $value;
    }

    public function setCollaborationAppFormats(string $collaborationAppFormats): void
    {
        $this->collaborationAppFormats = $collaborationAppFormats;
    }

    public function getCollaborationAppMappingField(): string
    {
        return $this->collaborationAppMappingField;
    }

    public function setCollaborationAppMappingField(string $collaborationAppMappingField): void
    {
        $this->collaborationAppMappingField = $collaborationAppMappingField;
    }

    public function getCollaborationAppUrl(): string
    {
        return $this->collaborationAppUrl;
    }

    public function setCollaborationAppUrl(string $collaborationAppUrl): void
    {
        $this->collaborationAppUrl = $collaborationAppUrl;
    }

    public function getOAuth2Active(): bool
    {
        return true;
        //return $this->oauth2Active;
    }

    public function setOAuth2Active(bool $oauth2Active): void
    {
        $this->oauth2Active = true;
        //$this->oauth2Active = $oauth2Active;
    }

    public function getOAuth2ClientId(): string
    {
        return $this->oauth2ClientId;
    }

    public function setOAuth2ClientId(string $oauth2ClientId): void
    {
        $this->oauth2ClientId = $oauth2ClientId;
    }

    public function getOAuth2ClientSecret(): string
    {
        return $this->oauth2ClientSecret;
    }

    public function setOAuth2ClientSecret(string $oauth2ClientSecret): void
    {
        $this->oauth2ClientSecret = $oauth2ClientSecret;
    }

    public function getOAuth2Path(bool $return_default = false): string
    {
        $value = $this->oauth2Path;
        $serviceClass = ilCloudStorageConfig::AVAILABLE_XCLS_SERVICES[$this->getServiceId()];
        return (!$value && $return_default) ? $serviceClass::getDefaultOAuth2Path() : $value;
    }

    public function setOAuth2Path(string $oauth2Path): void
    {
        $this->oauth2Path = $oauth2Path;
    }

    public function getOAuth2TokenRequestAuth(): string
    {
        return $this->oauth2TokenRequestAuth ?? self::HEADER;
    }


    public function setOAuth2TokenRequestAuth(string $oauth2TokenRequestAuth): void
    {
        $this->oauth2TokenRequestAuth = $oauth2TokenRequestAuth;
    }

    public function getServerURL(): string
    {
        return $this->serverUrl;
    }

    public function setServerURL(string $serverUrl): void
    {
        $this->serverUrl = $serverUrl;
    }

    public function getWebDavURL(): string
    {
        return $this->webdavUrl;
    }

    public function setWebDavURL(string $webdavUrl): void
    {
        $this->webdavUrl = $webdavUrl;
    }

    public function getWebDavPath(bool $return_default = false): string
    {
        $value = $this->webdavPath;
        $serviceClass = ilCloudStorageConfig::AVAILABLE_XCLS_SERVICES[$this->getServiceId()];
        return (!$value && $return_default) ? $serviceClass::getDefaultWebDavPath() : $value;
    }

    public function setWebDavPath(string $webdavPath): void
    {
        $this->webdavPath = $webdavPath;
    }

    public function getProxyURL(bool $return_default = false): string
    {
        if ($return_default) {
            $proxy = ilProxySettings::_getInstance();
            if ($proxy->isActive()) {
                    $host = $proxy->getHost();
                    $port = $proxy->getPort();
                    if ($port) {
                        $host .= ":" . $port;
                    }
                    return $this->proxyUrl = $host;
            } 
        }
        return $this->proxyUrl;
    }

    public function setProxyURL(string $proxyUrl): void
    {
        $this->proxyUrl = $proxyUrl;
    }

    public function getFullOAuth2Path(): string
    {
        static $path;
        if (!$path) {
            $path = rtrim($this->getServerURL(), '/') . '/' . rtrim(ltrim($this->getOAuth2Path(true), '/'), '/');
        }

        return $path;
    }

    public function getFullWebDavPath(): string
    {
        $url = ($this->getWebDavURL() == '') ? $this->getServerURL() : $this->getWebDavURL();
        return rtrim($url, '/') . '/' . rtrim(ltrim($this->getWebDavPath(true), '/'), '/') . '/';
    }

    public function getFullCollaborationAppPath(string $file_id, string $file_path)
    {
        //$url = ($this->getWebDavURL() == '') ? $this->getServerURL() : $this->getWebDavURL();
        $url =  $this->getServerURL();
        $link = rtrim($url, '/') . '/' . $this->collaborationAppUrl;
        $link = str_replace('{FILE_ID}', $file_id, $link);
        $link = str_replace('{FILE_PATH}', $file_path, $link);

        return $link;
    }

    public function getMappingValueForUser(ilObjUser $user): string
    {
        $map_field = $this->collaborationAppMappingField;
        switch ($map_field) {
            case 'login':
                return $user->getLogin();
            case 'ext_account':
                return $user->getExternalAccount();
            case 'email':
                return $user->getEmail();
            case 'second_email':
                return $user->getSecondEmail();
        }
    }

    public function getCollaborationAppFormatsAsArray(): array
    {
        return array_map(
            'trim',
            explode(',', $this->getCollaborationAppFormats())
        );
    }

    public function getObjIdsSpecial(): string
    {
        return $this->objIdsSpecial;
    }
    public function setObjIdsSpecial($objIdsSpecial): void
    {
        $this->objIdsSpecial = $objIdsSpecial;
    }

    public function getServiceId(): ?string
    {
        return $this->serviceId;
    }

    public function setServiceId(string $serviceId): void
    {
        $this->serviceId = $serviceId;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    public function getTokenUser(?string $user = null)
    {
        $array = json_decode($this->getAccessToken(), 1);
        $token = !is_null($user) && isset($array[$user]) ? $array[$user] : null;
        return is_null($user) ? $array : $token;
    }

    public function setTokenUser(string $user, string $token): void
    {
        $tokenUser = $this->getTokenUser();
        $tokenUser[$user] = $token;
        $this->accessToken = json_encode($tokenUser);
    }

    public function getAuthMethod(): ?string
    {
        return $this->authMethod;
    }

    public function setAuthMethod(?string $authMethod): void
    {
        $this->authMethod = $authMethod;
    }


    public static function getServiceFromConfig(int $refId, int $connId): ilCloudStorageServiceInterface
    {
        $serviceId = ilCloudStorageConfig::getInstance($connId)->getServiceId();
        $serviceClass = ilCloudStorageConfig::AVAILABLE_XCLS_SERVICES[$serviceId];
        return new $serviceClass($refId, $connId);
    }

    #endregion GETTER & SETTER

    public static function _getCloudStorageConnOverviewUses(): array
    {
        global $DIC; /** @var Container $DIC */
        $ilDB = $DIC->database();

        // Get Conn Title
        $query = "SELECT id, title from rep_robj_xcls_conn";
        $result = $ilDB->query($query);
        $data0 = [];
        while($row = $ilDB->fetchAssoc($result)) {
            $data0[$row['id']] = $row;
        }
        // Get conn uses
        $query = "select object_reference.ref_id xclsRefId, rep_robj_xcls_data.conn_id xclsConnId, rep_robj_xcls_data.id as xclsObjId," .
                " object_data.title xclsObjTitle, not isnull(object_reference.deleted) as isInTrash, rep_robj_xcls_data.is_online
                 FROM rep_robj_xcls_data, object_data, object_reference
                 WHERE object_data.obj_id=rep_robj_xcls_data.id
                 AND object_reference.obj_id=rep_robj_xcls_data.id
                 ORDER by conn_id, xclsObjTitle
                 ";
        $result = $ilDB->query($query);
        $data = [];
        while ($row = $ilDB->fetchAssoc($result)) {
            $row['connTitle'] = $data0[$row['xclsConnId']]['title'];
            $data[$row['xclsRefId']] = $row;
        }

        // Get repo data to conn uses
        $query = "select tree.child, tree.parent parentRefId, object_data.title parentTitle
                 FROM tree, object_data, object_reference
                 WHERE object_data.obj_id=object_reference.obj_id
                 AND object_reference.ref_id = tree.parent
                 AND " . $ilDB->in('tree.child', array_keys($data), false, 'integer'); # object_reference.ref_id in (272)
        $result = $ilDB->query($query);
        $data2 = [];
        while($row = $ilDB->fetchAssoc($result)) {
            $data2[$row['child']] = $row;
        }

        // merge all together
        $returnArr = [];
        foreach ($data as $refId => $row) {
            $returnArr[] = array_merge($data[$refId], $data2[$refId]);
        } // EOF foreach ($data as $datum)

        return $returnArr;
    }

    /**
     * Get basic data array of all types (without field definitions)
     */
    public static function _getCloudStorageConnData(bool $a_extended = false, ?int $a_availability = null, string $operatorAvailability = '='): array
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = "SELECT * FROM rep_robj_xcls_conn";
        if (isset($a_availability)) {
            $query .= " WHERE availability" . $operatorAvailability . $ilDB->quote($a_availability, 'integer');
        }
        $query .= " ORDER BY title";
        $res = $ilDB->query($query);

        $data = array();
        while ($row = $ilDB->fetchAssoc($res)) {
            if ($a_extended) {
                $row['usages'] = self::_countUntrashedUsages($row['id']);
            }
            $row['conn_id'] = $row['id'];
            unset($row['id']);
            $data[$row['conn_id']] = $row;
        }
        return $data;
    }

    /**
     * Count the number of untrashed usages of a type
     */
    public static function _countUntrashedUsages(int $a_type_id): int
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = "SELECT COUNT(*) untrashed FROM rep_robj_xcls_data s"
            . " INNER JOIN object_reference r ON s.id = r.obj_id"
            . " WHERE r.deleted IS NULL "
            . " AND s.conn_id = " . $ilDB->quote($a_type_id, 'integer');

        $res = $ilDB->query($query);
        $row = $ilDB->fetchObject($res);
        return $row->untrashed;
    }

    public static function _getCloudStorageConnUsesReferences(int $connId): array
    {
        global $DIC;
        $ilDB = $DIC->database();
        $data = [];

        $query = "SELECT id obj_id FROM rep_robj_xcls_data s"
            . " INNER JOIN object_reference r ON s.id = r.obj_id"
            . " WHERE s.conn_id = " . $ilDB->quote($connId, 'integer');

        $res = $ilDB->query($query);

        while($row = $ilDB->fetchAssoc($res)) {
            $data[] = $row;
        }
        return $data;
    }

    public static function _getAvailableCloudStorageConn(bool $onlyCreate = false, ?int $conn_id = null): array
    {
        global $DIC;
        $operator = !$onlyCreate ? '<>' : '=';
        $availStatus = !$onlyCreate ? self::AVAILABILITY_NONE : self::AVAILABILITY_CREATE;
        $availItems = is_array($res = self::_getCloudStorageConnData(false, $availStatus, $operator))
            ? $res
            : [];
        $list = [];
        #echo '<pre>'; var_dump($availItems); exit;
        foreach($availItems as $key => $val) {
            // we only check configs of defined VCs for globalAssignedRoles
            // For existing xclsConfigs we do not check globalAssignedRoles before a vcConfig is updated.
            // Sn: ToDo ?
            /*
            if(in_array($val['service_id'], self::FS_RELATED_FUNCTION['globalAssignedRoles']) && (bool)$val['assigned_roles']) {
                $xclsAssignedRoles = explode(',', $val['assigned_roles'] . ',x');
                array_pop($xclsAssignedRoles);
                #exit;
                $continue = true;
                foreach ($xclsAssignedRoles as $roleId) {
                    if($DIC->rbac()->review()->isAssigned($DIC->user()->getId(), $roleId)) {
                        $continue = false;
                        break;
                    }
                }
                if($continue) {
                    continue;
                }
            }
            */
            $list[$val['conn_id']] = $val['title'];
        }
        return $list;
    }

    public static function _deleteCloudStorageConn(int $conn_id): void
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = "DELETE FROM rep_robj_xcls_conn " .
            "WHERE id = " . $ilDB->quote($conn_id, 'integer');
        $ilDB->manipulate($query);
    }

    public static function removeUnsafeChars(string $value): string
    {
        $remove = ["\n", "\r", "\t", '"', '\'', "<?", "?>"];
        $value = str_replace($remove, ' ', $value);
        foreach (["/<[^>]*>/", "%<\/[^>]*>]%", "%[\s]{2,}%"] as $regEx) {
            $value = preg_replace($regEx, ' ', $value);
        } // EOF foreach as $regEx)
        return trim($value);
    }

    public function ilBoolToInt(bool $a_val): int
    {
        if ($a_val == true) {
            return 1;
        }
        return 0;
    }
    public function ilIntToBool(int $a_val): bool
    {
        if ($a_val == 1) {
            return true;
        }
        return false;
    }

}
