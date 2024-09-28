<?php

declare(strict_types=1);

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilCloudStoragePluginConfigException
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 * @version $Id:
 * @extends ilCloudStorageException
 */
class ilCloudStoragePluginConfigException extends ilCloudStorageException
{
    const TABLE_DOES_NOT_EXIST = 100001;
    const ENTRY_DOES_NOT_EXIST = 100002;
    const NO_VALID_GET_OR_SET_FUNCTION = 100003;
    const PLUGIN_NOT_PROPERLY_CONFIGURED = 100004;

    protected function assignMessageToCode(): void
    {
        global $DIC;
        $lng = $DIC['lng'];
        switch ($this->code) {
            case self::TABLE_DOES_NOT_EXIST:
                $this->message = $lng->txt("cld_config_table_does_not_exist") . " " . $this->add_info;
                break;
            case self::ENTRY_DOES_NOT_EXIST:
                $this->message = $lng->txt("cld_config_entry_does_not_exist") . " " . $this->add_info;
                break;
            case self::NO_VALID_GET_OR_SET_FUNCTION:
                $this->message = $lng->txt("cld_config_no_valid_get_or_set_function") . " " . $this->add_info;
                break;
            case self::PLUGIN_NOT_PROPERLY_CONFIGURED:
                $this->message = $lng->txt("cld_plugin_not_properly_configured") . " " . $this->add_info;
                break;
            default:
                $this->message = $lng->txt("cld_config_unknown_exception") . " " . $this->add_info;
                break;
        }
    }
}
