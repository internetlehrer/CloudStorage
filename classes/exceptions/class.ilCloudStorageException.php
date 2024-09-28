<?php

declare(strict_types=1);

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilCloudStorageException
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 * @version $Id:
 * @extends ilException
 */
class ilCloudStorageException extends Exception
{
    const UNKNONW_EXCEPTION = -1;
    const NO_CONNECTION = 1000;
    const NO_SERVICE_ACTIVE = 1001;
    const NO_SERVICE_SELECTED = 1002;
    const SERVICE_NOT_ACTIVE = 1003;
    const SERVICE_CLASS_FILE_NOT_FOUND = 1004;
    const PLUGIN_HOOK_COULD_NOT_BE_INSTANTIATED = 1005;
    const FOLDER_NOT_EXISTING_ON_SERVICE = 1101;
    const FILE_NOT_EXISTING_ON_SERVICE = 1102;
    const FOLDER_ALREADY_EXISTING_ON_SERVICE = 1103;
    const RESSOURCE_NOT_EXISTING_OR_RENAMED = 1104;
    const ROOT_FOLDER_NOT_EXISTING_OR_RENAMED = 1105;
    const AUTHENTICATION_FAILED = 2001;
    const NOT_AUTHORIZED = 2002;
    const DELETE_FAILED = 2101;
    const DOWNLOAD_FAILED = 2201;
    const FOLDER_CREATION_FAILED = 2301;
    const UPLOAD_FAILED = 2401;
    const UPLOAD_FAILED_MAX_FILESIZE = 2402;
    const ADD_ITEMS_FROM_SERVICE_FAILED = 2501;
    const INVALID_INPUT = 3001;
    const ALLOWED_CHARACTERS = 3002;
    const PATH_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION = 4001;
    const ID_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION = 4002;
    const ID_ALREADY_EXISTS_IN_FILE_TREE_IN_SESSION = 4003;
    const PERMISSION_DENIED = 5001;
    const PERMISSION_TO_CHANGE_ROOT_FOLDER_DENIED = 5002;
    
    protected $message;
    protected $code;
    protected $add_info;
    
    public function __construct(int $exception_code, string $exception_info = "")
    {
        $this->code = $exception_code;
        $this->add_info = $exception_info;
        $this->assignMessageToCode();
        parent::__construct($this->message, $this->code);
    }

    protected function assignMessageToCode(): void
    {
        global $DIC;
        $lng = $DIC['lng'];
        switch ($this->code) {
            case self::NO_CONNECTION:
                $this->message = $lng->txt("rep_robj_xcls_cld_no_connection");
                break; 
            case self::NO_SERVICE_ACTIVE:
                $this->message = $lng->txt("rep_robj_xcls_cld_no_service_active");
                break;
            case self::NO_SERVICE_SELECTED:
                $this->message = $lng->txt("rep_robj_xcls_cld_no_service_selected");
                break;
            case self::SERVICE_NOT_ACTIVE:
                $this->message = $lng->txt("rep_robj_xcls_cld_service_not_active");
                break;
            case self::SERVICE_CLASS_FILE_NOT_FOUND:
                $this->message = $lng->txt("rep_robj_xcls_cld_service_class_file_not_found");
                break;
            case self::FOLDER_NOT_EXISTING_ON_SERVICE:
                $this->message = $lng->txt("rep_robj_xcls_cld_folder_not_existing_on_service");
                break;
            case self::FOLDER_ALREADY_EXISTING_ON_SERVICE:
                $this->message = $lng->txt("rep_robj_xcls_cld_folder_already_existing_on_service");
                break;
            case self::RESSOURCE_NOT_EXISTING_OR_RENAMED:
                $this->message = $lng->txt("rep_robj_xcls_cld_ressource_not_existing_or_renamed");
                break;
            case self::ROOT_FOLDER_NOT_EXISTING_OR_RENAMED:
                $this->message = $lng->txt("rep_robj_xcls_cld_root_folder_not_existing_or_renamed");
                break;
            case self::FILE_NOT_EXISTING_ON_SERVICE:
                $this->message = $lng->txt("rep_robj_xcls_cld_file_not_existing_on_service");
                break;
            case self::AUTHENTICATION_FAILED:
                $this->message = $lng->txt("rep_robj_xcls_cld_authentication_failed");
                break;
            case self::NOT_AUTHORIZED:
                $this->message = $lng->txt("rep_robj_xcls_cld_not_authorized");
                break;
            case self::DELETE_FAILED:
                $this->message = $lng->txt("rep_robj_xcls_cld_delete_failed");
                break;
            case self::ADD_ITEMS_FROM_SERVICE_FAILED:
                $this->message = $lng->txt("rep_robj_xcls_cld_add_items_from_service_failed");
                break;
            case self::DOWNLOAD_FAILED:
                $this->message = $lng->txt("rep_robj_xcls_cld_add_download_failed");
                break;
            case self::FOLDER_CREATION_FAILED:
                $this->message = $lng->txt("rep_robj_xcls_cld_folder_creation_failed");
                break;
            case self::UPLOAD_FAILED:
                $this->message = $lng->txt("rep_robj_xcls_cld_upload_failed");
                break;
            case self::UPLOAD_FAILED_MAX_FILESIZE:
                $this->message = $lng->txt("rep_robj_xcls_cld_upload_failed_max_filesize");
                break;
            case self::INVALID_INPUT:
                $this->message = $lng->txt("rep_robj_xcls_cld_invalid_input");
                break;
            case self::ALLOWED_CHARACTERS:
                $this->message = $lng->txt("rep_robj_xcls_cld_invalid_input_allowed");
                break;
            case self::PATH_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION:
                $this->message = $lng->txt("rep_robj_xcls_cld_path_does_not_exist_in_file_tree_in_session");
                break;
            case self::ID_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION:
                $this->message = $lng->txt("rep_robj_xcls_cld_id_does_not_exist_in_file_tree_in_session");
                break;
            case self::ID_ALREADY_EXISTS_IN_FILE_TREE_IN_SESSION:
                $this->message = $lng->txt("rep_robj_xcls_cld_id_already_exists_in_file_tree_in_session");
                break;
            case self::PLUGIN_HOOK_COULD_NOT_BE_INSTANTIATED:
                $this->message = $lng->txt("rep_robj_xcls_cld_plugin_hook_could_not_be_instantiated");
                break;
            case self::PERMISSION_DENIED:
                $this->message = $lng->txt("rep_robj_xcls_cld_permission_denied");
                break;
            case self::PERMISSION_TO_CHANGE_ROOT_FOLDER_DENIED:
                $this->message = $lng->txt("rep_robj_xcls_cld_permission_to_change_root_folder_denied");
                break;
            default:
                $this->message = $lng->txt("rep_robj_xcls_cld_unknown_exception");
                break;
        }
        $this->message .= ($this->add_info ? ": " : "") . $this->add_info;
    }

    public function __toString()
    {
        return get_class($this) . " '{$this->message}' in {$this->file}({$this->line})\n"
            . "{$this->getTraceAsString()}";
    }
}
