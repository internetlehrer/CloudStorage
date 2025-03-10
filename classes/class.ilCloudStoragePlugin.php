<?php

use ILIAS\DI\Container;

/**
* CloudStorage repository object plugin
*
* @author  Stefan Schneider <eqsoft4@gmail.com>
* @version $Id$
*
*/

class ilCloudStoragePlugin extends ilRepositoryObjectPlugin
{
    public const ID = 'xcls';

    public function __construct()
    {
        global $DIC;
        $this->db = $DIC->database();
        parent::__construct($this->db, $DIC["component.repository"], self::ID);
    }

    public function getPluginName(): string
    {
        return "CloudStorage";
    }

    protected function uninstallCustom(): void
    {
        global $DIC;
        $ilDB = $DIC->database();

        if ($ilDB->tableExists('rep_robj_xcls_data')) {
            $ilDB->dropTable('rep_robj_xcls_data');
        }
        if ($ilDB->tableExists('rep_robj_xcls_conn')) {
            $ilDB->dropTable('rep_robj_xcls_conn');
        }

        if ($ilDB->tableExists('rep_robj_xcls_ocld_tk')) {
            $ilDB->dropTable('rep_robj_xcls_ocld_tk');
        }
    }

    /**
     * @inheritdoc
     */
    public function allowCopy(): bool
    {
        return false;
    }

}
