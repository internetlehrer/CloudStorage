<?php

declare(strict_types=1);

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
        $set2 = self::parseIniFile($DIC->http()->request()->getUri()->__toString());

        $DIC->http()->request()->getUri();

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

    // nothing to do in ILIAS 9
    public static function migrationSetup(): void {
        return;
    }
}
