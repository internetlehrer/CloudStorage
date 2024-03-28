<?php
/**
* Application class for CloudStorage repository object.
*
* @author  Stefan Schneider <eqsoft4@gmail.com>
*
* @version $Id$
*/

use ILIAS\DI\Container;

class ilObjCloudStorage extends ilObjectPlugin
{
    public const TABLE_XCLS_OBJECT = 'rep_robj_xcls_data';
    public const PLUGIN_PATH = './Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage';
    public const INI_FILENAME = 'plugin';

    protected ilCloudStorageConfig $settings;

    public static ?ilCloudStorageConfig $SETTINGS = null;

    protected array $pluginIniSet = [];

    /** @var bool|ilObject $parentObj */
    protected $parentObj;

    /** @var bool|ilObjCourse $course */
    protected $course;

    /** @var bool|ilObjGroup $group */
    protected $group;

    /** @var bool|ilObjCategory $category */
    protected $category;

    protected ilObjSession $ilObjSession;

    private Container $dic;

    private bool $online = false;

    private int $connId;

    private string $rootFolder = '';

    private string $rootId = '';

    private string $baseUri = '';

    private string $username = '';

    private string $password = '';

    private bool $authComplete = false;

    private int $ownerId = -1;

    private static ?self $instance = null;

    /**
     * Constructor
     *
     * @access    public
     * @param int $a_ref_id
     */
    public function __construct(int $a_ref_id = 0)
    {
        global $DIC;

        parent::__construct($a_ref_id);

        $this->dic = $DIC;
        $this->db = $this->dic->database();
        /*
        if (null === $this->pluginIniSet) {
            $this->setPluginIniSet();
        }
        */
    }

    /**
    * Get type.
    */
    protected function initType(): void
    {
        $this->setType("xcls");
    }

    /**
    * Create object
    */

    public function doCreate(bool $clone_mode = false): void
    {
    }

    // Sn: ToDo createFsObj ?
    public function createFolder(int $online, int $conn_id)
    {
        $ilDB = $this->db;
        $this->setOnline($this->ilIntToBool((int) $online));
        $this->setConnId($conn_id);
        if ($this->getRootFolder() == '') {
            $this->setRootFolder(ilCloudStorageConfig::getInstance($conn_id)->getBaseDirectory());
        }
        //$settings = $this->setDefaultsByPluginConfig($conn_id, true);
        //var_dump($settings) ; exit;
        $a_data=array(
            'id'					    => array('integer', $this->getId()),
            'is_online'				    => array('integer', $this->ilBoolToInt($this->getOnline())),
            'conn_id'                   => array('integer', $this->getConnId()),
            'root_folder'               => array('text', $this->getRootFolder()),
            'root_id'                   => array('text', $this->getRootId()),
            'base_uri'                  => array('text', $this->getBaseUri()),
            'username'                  => array('text', $this->getUsername()),
            'password'                  => array('text', $this->getPassword()),
            'auth_complete'             => array('integer', $this->ilBoolToInt($this->getAuthComplete())),
            'owner_id'                  => array('integer', $this->getOwnerId())
        );
        $ilDB->insert('rep_robj_xcls_data', $a_data);
    }

    /**
    * Read data from db
    */
    public function doRead(): void
    {
        $ilDB = $this->db;

        $result = $ilDB->query("SELECT * FROM rep_robj_xcls_data WHERE id = " . $ilDB->quote($this->getId(), "integer"));
        while ($record = $ilDB->fetchAssoc($result)) {
            //$settings = new ilCloudStorageConfig($record["conn_id"]);
            $this->setOnline($this->ilIntToBool($record["is_online"]));
            $this->setConnId((int)$record["conn_id"]);
            $this->setRootFolder($record["root_folder"]);
            $this->setRootId($record["root_id"]);
            $this->setBaseUri($record["base_uri"]);
            $this->setUsername($record["username"]);
            $this->setPassword($record["password"]);
            $this->setAuthComplete($this->ilIntToBool($record["auth_complete"]));
            $this->setOwnerId((int)$record["owner_id"]);
        }
    }

    /**
    * Update data
    */
    public function doUpdate(): void
    {
        $ilDB = $this->db;
        $a_data=array(
            'is_online'				    => array('integer', $this->ilBoolToInt($this->getOnline())),
            'conn_id'				    => array('integer', $this->getConnId()),
            'root_folder'               => array('text', $this->getRootFolder()),
            'root_id'                   => array('text', $this->getRootId()),
            'base_uri'                  => array('text', $this->getBaseUri()),
            'username'                  => array('text', $this->getUsername()),
            'password'                  => array('text', $this->getPassword()),
            'auth_complete'             => array('integer', $this->ilBoolToInt($this->getAuthComplete())),
            'owner_id'				    => array('integer', $this->getOwnerId())
            
        );
        $ilDB->update('rep_robj_xcls_data', $a_data, array('id' => array('integer', $this->getId())));
    }

    public function updateConnId(int $connId): void
    {
        $ilDB = $this->db;

        $this->setConnId($connId);
        $data = [
            'conn_id' => ['integer', $this->connId]
        ];
        $ilDB->update('rep_robj_xcls_data', $data, array('id' => array('integer', $this->getId())));
    }

    /**
    * Delete data from db
    */
    public function doDelete(): void
    {
        $ilDB = $this->db;
        $ilDB->manipulate("DELETE FROM rep_robj_xcls_data WHERE id = " . $ilDB->quote($this->getId(), "integer"));
    }

    protected function doCloneObject(ilObject2 $new_obj, int $a_target_id, ?int $a_copy_id = 0): void
    {
        $this->doClone($new_obj, $a_target_id, $a_copy_id);
    }

    /**
     * Do Cloning
     */
    public function doClone(ilObject2 $new_obj, int $a_target_id, ?int $a_copy_id)
    {
        $ilDB = $this->db;
        $a_data=array(
            'id'					    => array('integer', $new_obj->getId()), // $a_target_id
            'is_online'				    => array('integer', $this->ilBoolToInt($this->getOnline())),
            'conn_id'				    => array('integer', $this->getConnId()),
            'root_folder'               => array('text', $this->getRootFolder()),
            'root_id'                   => array('text', $this->getRootId()),
            'base_uri'                  => array('text', $this->getBaseUri()),
            'username'                  => array('text', $this->getUsername()),
            'password'                  => array('text', $this->getPassword()),
            'auth_complete'             => array('integer', $this->ilBoolToInt($this->getAuthComplete())),
            'owner_id'				    => array('integer', $this->getOwnerId())
        );
        $ilDB->insert('rep_robj_xcls_data', $a_data);
    }

    public function getCloudStorageObjUser(?int $refId = null, ?int $userId = null): array
    {
        $db = $this->dic->database();
        $result = [];
        $userId = $db->quote($userId ?? $this->dic->user()->getId(), 'integer');
        $refId = $db->quote($refId ?? 0, 'integer');

        $sql = 'SELECT rel_data FROM rep_robj_xcls_obj_user WHERE ref_id = ' . $refId .
            ' AND user_id = ' . $userId;
        $query = $db->query($sql);
        while($row = $db->fetchAssoc($query)) {
            $result = json_decode($row['rel_data'], true);
        }
        return $result;
    }

    /**
     * @param array $param
     * @param int|null $refId
     * @return bool
     */
    public function setCloudStorageObjUser(array $param, ?int $refId = null): bool
    {
        $db = $this->dic->database();
        $oldParam = $this->getCloudStorageObjUser($refId);
        $newParam = [
            'rel_data' => ['string', json_encode(array_replace($oldParam, $param))]
        ];
        $where = [
            'ref_id' => ['integer', $refId ?? 0],
            'user_id' => ['integer' , $this->dic->user()->getId()]
        ];
        $result = false;
        if(!(bool)sizeof($oldParam)) {
            $result = $db->insert('rep_robj_xcls_obj_user', array_merge($where, $newParam));
        } else {
            $result = $db->update('rep_robj_xcls_obj_user', $newParam, $where);
        }
        return (bool)$result;
    }

    //
    // Set/Get Methods for our CloudStorage properties
    //

    public function getOnline(): bool
    {
        return $this->online;
    }

    public function setOnline(bool $setOnline): void
    {
        $this->online = $setOnline;
    }

    public function getConnId(): ?int
    {
        return $this->connId;
    }

    public function setConnId(int $connId): void
    {
        $this->connId = $connId;
    }

    public function getRootFolder(): string
    {
        return $this->rootFolder;
    }

    public function setRootFolder(string $a_rootFolder): void
    {
        $this->rootFolder = $a_rootFolder;
    }

    public function getRootId(): string
    {
        return $this->rootId;
    }

    public function setRootId(string $rootId): void
    {
        $this->rootId = $rootId;
    }
    
    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function setBaseUri(string $baseUri): void
    {
        $this->baseUri = $baseUri;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getAuthComplete(): bool
    {
        return $this->authComplete;
    }

    public function setAuthComplete(bool $authComplete): void
    {
        $this->authComplete = $authComplete;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function setOwnerId(int $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function currentUserIsOwner(): bool
    {
        return $this->dic->user()->getId() == $this->getOwnerId();
    }

    public function getAllWithSameOwner(): array
    {
        $res = $this->dic->database()->query('SELECT id 
					FROM object_data od 
					INNER JOIN ' . self::TABLE_XCLS_OBJECT . ' oc ON od.obj_id = oc.id 
					WHERE owner = ' . $this->getOwnerId());
        $ids = array();
        while ($rec = $this->dic->database()->fetchAssoc($res)) {
            $ids[] = $rec['id'];
        }

        return $ids;
    }

    public function getAllWithSameOwnerAndConnection(): array
    {
        $res = $this->dic->database()->query('SELECT id
					FROM rep_robj_xcls_data WHERE owner_id = ' . $this->getOwnerId() .
                    ' AND conn_id = ' . $this->getConnId());
        $ids = array();
        while ($rec = $this->dic->database()->fetchAssoc($res)) {
            $ids[] = $rec['id'];
        }
        return $ids;
    }

    public static function getConnTitleFromObjId(int $objId): string {
        global $DIC;
        $res = $DIC->database()->query('SELECT conn_id
					FROM rep_robj_xcls_data WHERE id = ' . $objId);

        while ($rec = $DIC->database()->fetchAssoc($res)) {
            return ilCloudStorageConfig::_getCloudStorageConnData()[$rec['conn_id']]['title'];
        }
        return "";
    }

    public function ilBoolToInt(bool $a_val): int
    {
        if ($a_val) {
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

    public function getUserForEmail(string $email, int $index = 0): ?int
    {
        if(sizeof($account = ilObjUser::_getLocalAccountsForEmail($email)) && isset(array_keys($account)[$index])) {
            return array_keys($account)[$index];
        }
        return null;
    }

    private function setDefaultsByPluginConfig(?int $connId, bool $getObject = false): ?ilCloudStorageConfig
    {
        $settings = new ilCloudStorageConfig($connId);
        if($getObject) {
            return $settings;
        }
        return null;
    }

    public static function getCloudStorageConnTitleAndTypeByObjId(int $objId): ?stdClass
    {
        global $DIC;
        $db = $DIC->database();

        $query = "SELECT rep_robj_xcls_conn.title title, rep_robj_xcls_conn.service_id type FROM rep_robj_xcls_data, rep_robj_xcls_conn WHERE rep_robj_xcls_conn.id = rep_robj_xcls_data.conn_id AND rep_robj_xcls_data.id = " . $db->quote($objId, 'integer');
        $result = $db->query($query);
        return $db->fetchObject($result);
    }

    public static function formatBytes(string $bytes): string
    {
        $unit = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($unit) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $unit[$pow];
    }

    public static function getProtokol(): string
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'HTTPS' : 'HTTP';
    }

    public static function getHttpPath(): string
    {
        $http_path = ILIAS_HTTP_PATH;
        if (substr($http_path, -1, 1) != '/') {
            $http_path = $http_path . '/';
        }
        if (strpos($http_path, 'Customizing') > 0) {
            $http_path = strstr($http_path, 'Customizing', true);
        }
        return $http_path;
    }

    ####################################################################################################################
    #### GENERAL OBJECT SETTINGS
    ####################################################################################################################

    /**
     * @return bool
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     */
    protected function isInCourseOrGroup(): bool
    {
        if(!$this->parentObj) {
            $this->setParentObj();
        }

        if(!$this->category) {
            return true;
        }

        return false;
    }

    /**
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     */
    protected function setParentObj(): void
    {
        global $DIC;

        $path = array_reverse($DIC->repositoryTree()->getPathFull($this->getRefId()));
        $keys = array_keys($path);
        $parent = $path[$keys[1]];

        $this->parentObj = ilObjectFactory::getInstanceByRefId($parent['ref_id']);
        switch(true) {
            case 'crs' === $parent['type'] && !$this->course:
                $this->course = $this->parentObj;
                break;
            case 'grp' === $parent['type'] && !$this->group:
                $this->group = $this->parentObj;
                break;
            case 'cat' === $parent['type'] && !$this->category:
                $this->category = $this->parentObj;
                break;
        }

        // check for ilObjSession
        if(false !== array_search($parent['type'], ['crs', 'grp'])) {
            $events = ilEventItems::getEventsForItemOrderedByStartingTime($this->getRefId());
            if((bool)sizeof($events)) {
                $now = date('U');
                //var_dump($now);
                foreach($events as $eventId => $eventStart) {
                    if(!$this->ilObjSession) {
                        /** @var ilObjSession $tmpSessObj */
                        $tmpSessObj = ilObjectFactory::getInstanceByObjId($eventId);

                        $dTplId = ilDidacticTemplateObjSettings::lookupTemplateId($tmpSessObj->getRefId());

                        if(!(bool)$dTplId && $now >= $eventStart) {
                            $event = ilSessionAppointment::_lookupAppointment($eventId);
                            $end = (bool)$event['fullday']
                                ? $eventStart + 60 * 60 *24
                                : $event['end'];
                            if($now < $end) {
                                $this->ilObjSession = $tmpSessObj; // ilObjectFactory::getInstanceByObjId($eventId);
                            }
                        }
                    }
                }
            }

        }

        //echo $this->ilObjSession->; exit;
    }

    ####################################################################################################################
    #### USER SETTINGS
    ####################################################################################################################

    /**
     * @return bool
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     */
    protected function isAdminOrTutor(): bool
    {

        if($this->isInCourseOrGroup()) {
            $userLiaRoles = $this->dic->rbac()->review()->assignedRoles($this->dic->user()->getId());
            if(!!$this->course) {
                $found = array_search($this->course->getDefaultAdminRole(), $userLiaRoles);
                $found = false !== $found ? true : array_search($this->course->getDefaultTutorRole(), $userLiaRoles);
                return false !== $found;
            }
            if(!!$this->group) {
                $found = array_search($this->group->getDefaultAdminRole(), $userLiaRoles);
                return false !== $found;
            }
        }
        return false;
    }


    ####################################################################################################################
    #### PUBLIC GETTERS & SETTERS
    ####################################################################################################################

    /**
     * @param string $value
     * @return string|null
     */
    public function getPluginIniSet(string $value = 'max_concurrent_users'): ?string
    {
        return isset($this->pluginIniSet[$value]) ? $this->pluginIniSet[$value] : null;
    }

    /**
     * @return bool
     */
    public function isUserAdmin(): bool
    {
        $userLiaRoles = $this->dic->rbac()->review()->assignedRoles($this->dic->user()->getId());
        if(null !== $this->course) {
            $found = array_search($this->course->getDefaultAdminRole(), $userLiaRoles);
        } else {
            $found = $this->dic->access()->checkAccessOfUser($this->dic->user()->getId(), 'write', 'showContent', $this->getRefId());
        }
        return false !== $found;
    }

    public static function setPluginIniSet(?ilCloudStorageConfig $settings = null): array
    {
        global $DIC;

        $settings = $settings ?? self::$SETTINGS;

        // Plugin wide ini settings (plugin.ini)
        $set1 = self::parseIniFile(self::INI_FILENAME);

        // Host specific ini settings (lms.example.com.ini)
        $set2 = self::parseIniFile($DIC->http()->request()->getUri());

        // xmvc_conn specific ini settings (bbb.example.com.ini)
        $set3 = !is_null($settings) ? self::parseIniFile($settings->getServerURL()) : [];

        return array_replace($set1, $set2, $set3);
    }

    public static function parseIniFile(?string $uriOrName = null): array
    {
        $returnParam = [];
        if(is_null($uriOrName)) {
            return $returnParam;
        }
        // ascii filename
        $iniPathFile = self::PLUGIN_PATH . '/' . $uriOrName . '.ini';

        // check filename from uri
        $regEx = "%^(https|http)://([^/\?]+)%";
        if((bool)preg_match($regEx, $uriOrName, $match)) {
            $iniPathFile = self::PLUGIN_PATH . '/' . array_pop($match) . '.ini';
        }

        if(!file_exists($iniPathFile)) {
            return $returnParam;
        }

        $iniContent = file_get_contents($iniPathFile);
        foreach(explode("\n", $iniContent) as $line) {
            if(substr_count($line, '=')) {
                list($key, $value) = explode('=', $line);
                if(substr_count($value, ',')) {
                    foreach (explode(',', $value) as $arrVal) {
                        $returnParam[trim($key)][] = trim($arrVal);
                    }
                } else {
                    $returnParam[trim($key)] = trim($value);
                }
            }
        }
        return $returnParam;
    }

    // Migration

    public static function migrationSetup(): void {
        
        $oldObjects = self::getOldCloudObjectReferences();
        
        // if no old cloud objects create new type and add rbac operations
        if (count($oldObjects) == 0) {
            include_once('./Services/Migration/DBUpdate_3560/classes/class.ilDBUpdateNewObjectType.php');
            $xcls_type_id = ilDBUpdateNewObjectType::addNewType('xcls', 'Cloud Folder');

            $rbac_ops = array(
                ilDBUpdateNewObjectType::RBAC_OP_EDIT_PERMISSIONS,
                ilDBUpdateNewObjectType::RBAC_OP_VISIBLE,
                ilDBUpdateNewObjectType::RBAC_OP_READ,
                ilDBUpdateNewObjectType::RBAC_OP_WRITE,
                ilDBUpdateNewObjectType::RBAC_OP_DELETE
            );
            ilDBUpdateNewObjectType::addRBACOperations($xcls_type_id, $rbac_ops);

            $parent_types = array('root', 'cat', 'crs', 'fold', 'grp');
            ilDBUpdateNewObjectType::addRBACCreate('create_xcls', 'Create Cloud Folder', $parent_types);

            $ops_id = ilDBUpdateNewObjectType::addCustomRBACOperation('upload', 'Upload Items', 'object', 3240);
            ilDBUpdateNewObjectType::addRBACOperation($xcls_type_id, $ops_id);
            $ops_id = ilDBUpdateNewObjectType::addCustomRBACOperation('delete_files', 'Delete Files', 'object', 3260);
            ilDBUpdateNewObjectType::addRBACOperation($xcls_type_id, $ops_id);
            $ops_id = ilDBUpdateNewObjectType::addCustomRBACOperation('delete_folders', 'Delete Folders', 'object', 3270);
            ilDBUpdateNewObjectType::addRBACOperation($xcls_type_id, $ops_id);
            $ops_id = ilDBUpdateNewObjectType::addCustomRBACOperation('download', 'Download Items', 'object', 3230);
            ilDBUpdateNewObjectType::addRBACOperation($xcls_type_id, $ops_id);
            $ops_id = ilDBUpdateNewObjectType::addCustomRBACOperation('files_visible', 'Files are visible', 'object', 3210);
            ilDBUpdateNewObjectType::addRBACOperation($xcls_type_id, $ops_id);
            $ops_id = ilDBUpdateNewObjectType::addCustomRBACOperation('folders_visible', 'Folders are visible', 'object', 3220);
            ilDBUpdateNewObjectType::addRBACOperation($xcls_type_id, $ops_id);
            $ops_id = ilDBUpdateNewObjectType::addCustomRBACOperation('folders_create', 'Folders may be created', 'object', 3250);
            ilDBUpdateNewObjectType::addRBACOperation($xcls_type_id, $ops_id);
            $ops_id = ilDBUpdateNewObjectType::addCustomRBACOperation('edit_in_online_editor', 'edit in online editor', 'object', 280);
            ilDBUpdateNewObjectType::addRBACOperation($xcls_type_id, $ops_id);

        } else {
            // this should be handled by migration:
            // 1. if old objects will be migrated by gui: old cloud type and rbac operations are used
            // 2. if old objects will be deleted   by gui: this function will be called again and type and rbac operations will be created
        }
    }

    public static function getOldCloudObjectReferences(): array
    {
        global $DIC;
        $ret = [];
        $query = $DIC->database()->query("
                    SELECT ref_id 
                    FROM object_data, object_reference 
                    WHERE object_data.type = 'cld' AND object_data.obj_id = object_reference.obj_id");
        //$result = $DIC->database()->fetchObject($query);
        while ($result = $DIC->database()->fetchAssoc($query)) {
            $ret[] = $result;
        }
        return $ret;
    }

    public static function getOldCloudObjectIds(): array
    {
        global $DIC;
        $ret = [];
        $query = $DIC->database()->query("
                    SELECT obj_id 
                    FROM object_data, object_reference 
                    WHERE object_data.type = 'cld' AND object_data.obj_id = object_reference.obj_id");
        //$result = $DIC->database()->fetchObject($query);
        while ($result = $DIC->database()->fetchAssoc($query)) {
            $ret[] = $result;
        }
        return $ret;
    }

    public static function getOldCloudConn(): array
    {
        global $DIC;
        $ret = [];
        $query = $DIC->database()->query("SELECT * FROM cld_cldh_owncld_config");
        //$result = $DIC->database()->fetchObject($query);
        while ($result = $DIC->database()->fetchAssoc($query)) {
            $ret[$result['config_key']] = $result['config_value'];
        }
        return $ret;
    }

    public static function getOldCloudTypeId(): int
    {
        global $DIC;
        $ret = -1;
        $query = $DIC->database()->query("SELECT obj_id FROM object_data WHERE type = 'typ' AND title = 'cld'");
        //$result = $DIC->database()->fetchObject($query);
        while ($result = $DIC->database()->fetchAssoc($query)) {
            $ret = $result['obj_id'];
        }
        return $ret;
    }

    public static function deleteNewCloudTypeTitle(): void
    {
        global $DIC;
        $DIC->database()->manipulate("DELETE FROM object_data WHERE type = 'typ' AND title = 'xcls'");
    }

    public static function disableOldCloudObject(): void
    {
        global $DIC;
        $DIC->database()->manipulate("UPDATE settings SET value = 1 WHERE keyword = 'obj_dis_creation_cld'");
    }

    public static function mapNewCloudTypeTitle(): void
    {
        global $DIC;
        $DIC->database()->manipulate("UPDATE object_data SET title = 'xcls' WHERE type = 'typ' AND title = 'cld'");
    }


    public static function mapNewCloudTypes(): void
    {
        global $DIC;
        $DIC->database()->manipulate("UPDATE object_data SET type = 'xcls' WHERE type = 'cld'");
    }

    public static function remapOldCloudTypes(): void
    {
        global $DIC;
        $DIC->database()->manipulate("UPDATE object_data SET type = 'cld' WHERE type = 'xcls'");
    }

    public static function mapCloudObjects(int $connId): void
    {
        global $DIC;
        $query = $DIC->database()->query("SELECT * FROM il_cld_data");
        while ($rec = $DIC->database()->fetchAssoc($query)) {
            $DIC->database()->manipulatef(
                'INSERT INTO rep_robj_xcls_data (id, is_online, conn_id, root_folder, root_id, base_uri, username, password, owner_id, auth_complete)
                 VALUES(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)',
                array('integer','integer', 'integer', 'text', 'text', 'text', 'text', 'text', 'integer', 'integer'),
                array($rec["id"], $rec["is_online"], $connId, $rec["root_folder"], '', '', '', '', $rec["owner_id"], $rec["auth_complete"])
            );
        }
    }

    public static function mapObjectDataDel(): void
    {
        global $DIC;
        $DIC->database()->manipulate("UPDATE object_data_del SET type = 'xcls' WHERE type = 'cld'");
    }

    public static function mapObjectSubObj(): void
    {
        global $DIC;
        $DIC->database()->manipulate("UPDATE il_object_subobj SET subobj = 'xcls' WHERE subobj = 'cld'");
    }

    public static function mapCreateRBACOperation(): void {
        global $DIC;
        $DIC->database()->manipulate("DELETE FROM rbac_operations WHERE operation = 'create_xcls'");
        $DIC->database()->manipulate("UPDATE rbac_operations SET operation = 'create_xcls' WHERE operation = 'create_cld'");
    }

    public static function mapRBACTemplates(): void
    {
        global $DIC;
        $DIC->database()->manipulate("UPDATE rbac_templates SET type = 'xcls' WHERE type = 'cld'");
    }

    public static function mapCloudTokens(int $conn_id): void
    {
        global $DIC;
        $query = $DIC->database()->query("SELECT * FROM cld_cldh_owncld_token");
        while ($rec = $DIC->database()->fetchAssoc($query)) {
            $DIC->database()->manipulatef(
                'INSERT INTO rep_robj_xcls_ocld_tk (conn_id, user_id, access_token, refresh_token, valid_through)
                 VALUES(%s, %s, %s, %s, %s)',
                array('integer','integer', 'text', 'text', 'integer'),
                array($conn_id, $rec["user_id"], $rec["access_token"], $rec["refresh_token"], $rec["valid_through"])
            );
        }
    }

    public static function mapAllOther(): void
    {
        // obj_type
        $tables = array(
            "adv_md_record_objs",
            "adv_md_substitutions",
            "cal_shared",
            "didactic_tpl_sa",
            "history",
            "il_cert_template",
            "il_cert_user_cert",
            "il_object_sub_type",
            "il_rating",
            "il_tag",
            "like_data",
            "note",
            "note_settings",
            "obj_stat",
            "obj_stat_log",
            "obj_stat_tmp",
            "orgu_obj_type_settings",
            "search_command_queue",
            "ut_lp_settings"
        );
        foreach ($tables as $table) {
            self::mapTable($table,"obj_type");
        }

        // target_type
        $tables = array(
            "conditions",
            "int_link"
        );
        foreach ($tables as $table) {
            self::mapTable($table,"target_type");
        }
    }


    public static function mapTable(string $table, string $field) {
        global $DIC;
        if ($DIC->database()->tableExists($table)) {
            $DIC->database()->manipulate("UPDATE " . $table . " SET " . $field . " = 'xcls' WHERE " . $field . " = 'cld'");
        }
    }
    /*
    obj_type:

    adv_md_record_objs
    adv_md_substitutions
    cal_shared
    didactic_tpl_sa
    history
    il_cert_template
    il_cert_user_cert
    il_meta_annotation
    il_meta_classification
    il_meta_contribute
    il_meta_description
    il_meta_educational
    il_meta_entity
    il_meta_format
    il_meta_general
    il_meta_identifier
    il_meta_identifier_
    il_meta_keyword
    il_meta_language
    il_meta_lifecycle
    il_meta_location
    il_meta_meta_data
    il_meta_relation
    il_meta_requirement
    il_meta_rights
    il_meta_tar
    il_meta_taxon
    il_meta_taxon_path
    il_meta_technical

    il_object_sub_type
    il_rating
    il_tag
    like_data
    note
    note_settings
    obj_stat
    obj_stat_log
    obj_stat_tmp
    orgu_obj_type_settings
    search_command_queue
    ut_lp_settings
    */
}
