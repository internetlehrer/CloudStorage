<?php
/**
* Access/Condition checking for CloudStorage object
*
* @author  Stefan Schneider <eqsoft4@gmail.com>
* @version $Id$
*/

use ILIAS\DI\Container;

class ilObjCloudStorageAccess extends ilObjectPluginAccess
{
    /**
    * Checks wether a user may invoke a command or not
    * (this method is called by ilAccessHandler::checkAccess)
    * Please do not check any preconditions handled by
    * ilConditionHandler here. Also don't do usual RBAC checks.
    */

    protected static $access_cache = array();

    protected static $connection_check_cache = array();

    public static $connection_status = 0;

    public function _checkAccess(string $a_cmd, string $a_permission, int $a_ref_id, int $a_obj_id, ?int $a_user_id = null): bool
    {
        global $ilUser, $ilAccess, $DIC;

        if ($a_user_id == null) {
            $a_user_id = $ilUser->getId();
        }
        switch ($a_permission) {
            case "visible":
                if (!ilObjCloudStorageAccess::checkOnline($a_obj_id) && !$ilAccess->checkAccessOfUser($a_user_id, "write", "", $a_ref_id)) {
                    return false;
                }
                break;
            case "read":
                if (!ilObjCloudStorageAccess::checkConnAvailability($a_obj_id)) {
                    return false;
                }
                $this->checkConnectionForSameOwnerAndConnection($a_ref_id);
                if (!ilObjCloudStorageAccess::checkAuthStatus($a_obj_id) && !$ilAccess->checkAccessOfUser($a_user_id, "write", "", $a_ref_id)) {
                    return false;
                }
                break;
            case "write":
                if (!ilObjCloudStorageAccess::checkConnAvailability($a_obj_id)) {
                    return false;
                }
                break;
        }

        return true;
    }

    private function checkConnectionForSameOwnerAndConnection(int $a_ref_id): void {
        if (!array_key_exists($a_ref_id, self::$connection_check_cache)) {
            $object = new ilObjCloudStorage($a_ref_id);
            $obj_ids = $object->getAllWithSameOwnerAndConnection();
            $service = ilCloudStorageConfig::getServiceFromConfig($a_ref_id, $object->getConnId());
            assert($service instanceof ilCloudStorageServiceInterface);
            if (!$service->checkAndRefreshAuthentication()) {
                foreach ($obj_ids as $obj_id) {
                    $ref_ids = ilObject::_getAllReferences($obj_id);
                    foreach ($ref_ids as $ref_id) {
                        self::$connection_check_cache[$ref_id] = self::$connection_status;
                        $obj = new ilObjCloudStorage($ref_id);
                        $obj->setAuthComplete(false);
                        $obj->update();
                    }
                }
            } else {
                try {
                    $service->checkConnection();
                    foreach ($obj_ids as $obj_id) {
                        $ref_ids = ilObject::_getAllReferences($obj_id);
                        foreach ($ref_ids as $ref_id) {
                            self::$connection_check_cache[$ref_id] = 0;
                            $obj = new ilObjCloudStorage($ref_id);
                            $obj->setAuthComplete(true);
                            $obj->update();
                        }
                    }
                } catch(ilCloudStorageException $e) {
                    self::$connection_status = $e->getCode();
                    // authorization failed even though auth_complete = true
                    if ($object->getAuthComplete() && $e->getCode() == ilCloudStorageException::NOT_AUTHORIZED) {
                        foreach ($obj_ids as $obj_id) {
                            $ref_ids = ilObject::_getAllReferences($obj_id);
                            foreach ($ref_ids as $ref_id) {
                                self::$connection_check_cache[$ref_id] = self::$connection_status;
                                $obj = new ilObjCloudStorage($ref_id);
                                $obj->setAuthComplete(false);
                                $obj->update();
                            }
                        }
                    } else {
                        foreach ($obj_ids as $obj_id) {
                            $ref_ids = ilObject::_getAllReferences($obj_id);
                            foreach ($ref_ids as $ref_id) {
                                self::$connection_check_cache[$ref_id] = self::$connection_status;
                            }
                        }
                    }
                }
            }
        }
    }

    public static function checkOnline(int $a_id): bool
    {
        global $DIC;
        $ilDB = $DIC->database();

        $set = $ilDB->query(
            "SELECT is_online FROM rep_robj_xcls_data " .
            " WHERE id = " . $ilDB->quote($a_id, "integer")
        );
        $rec = $ilDB->fetchAssoc($set);
        return (bool) $rec["is_online"];
    }

    public static function checkConnAvailability(int $obj_id): bool
    {
        global $DIC;
        $ilDB = $DIC->database();

        $set = $ilDB->query(
            "SELECT conn_id FROM rep_robj_xcls_data  " .
            " WHERE id = " . $ilDB->quote($obj_id, "integer")
        );
        $data = $ilDB->fetchObject($set);

        $set = $ilDB->query(
            "SELECT availability FROM rep_robj_xcls_conn " .
            " WHERE id = " . $ilDB->quote($data->conn_id, "integer")
        );
        $conn  = $ilDB->fetchObject($set);
        //var_dump([(int)ilCloudStorageConfig::AVAILABILITY_NONE !== (int)$conn->availability, (int)$conn->availability]); exit;
        return (int)ilCloudStorageConfig::AVAILABILITY_NONE !== (int)$conn->availability;
    }

    public static function checkAuthStatus(int $a_id): bool
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        if (!isset(self::$access_cache[$a_id]["auth_status"])) {
            $set = $ilDB->query("SELECT auth_complete FROM rep_robj_xcls_data " . " WHERE id = " . $ilDB->quote($a_id, "integer"));
            $rec = $ilDB->fetchAssoc($set);
            self::$access_cache[$a_id]["auth_status"] = (bool) $rec["auth_complete"];
        }

        return self::$access_cache[$a_id]["auth_status"];
    }

}
