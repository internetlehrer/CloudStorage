<?php

declare(strict_types=1);

/**
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 * GPLv2, see LICENSE
 */

use ILIAS\DI\Container;

/**
 * CloudStorage plugin: fs types table GUI
 *
 * @author Uwe Kohnle <kohnle@internetlehrer-gmbh.de>
 * @version $Id$
 */
class ilCloudStorageConnTableGUI extends ilTable2GUI
{
    private bool $webex = false;

    private Container $dic;


    /**
     * Constructor
     * @param object        parent object
     * @param string        parent command
     * @throws ilPluginException
     */
    public function __construct($a_parent_obj, $a_parent_cmd = '', $a_template_context = '')
    {
        // this uses the cached plugin object
        //		$this->plugin_object = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'CloudStorage');

        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);

        global $DIC; /** @var Container $DIC */
        $this->dic = $DIC;
    }

    /**
     * Init the table with some configuration
     *
     *
     * @access public
     * @param $a_parent_obj
     */
    public function init($a_parent_obj)
    {
        $this->addColumn($this->dic->language()->txt('id'), 'type_id', '10%');
        $this->addColumn($this->dic->language()->txt('rep_robj_xcls_conf_title'), 'title', '30%');
        $this->addColumn($this->dic->language()->txt('rep_robj_xcls_conf_availability'), 'availability', '20%');
        $this->addColumn($this->dic->language()->txt('rep_robj_xcls_untrashed_usages'), 'usages', '10%');
        $this->addColumn($this->dic->language()->txt('actions'), '', '20%');
        $this->setEnableHeader(true);
        $this->setFormAction($this->dic->ctrl()->getFormAction($a_parent_obj));
        $this->addCommandButton('createCloudStorageConn', $this->dic->language()->txt('rep_robj_xcls_create_type'));
        // ToDo: check
        // $this->addCommandButton('viewLogs', $lng->txt('rep_robj_xxcf_view_logs'));
        
        $this->setRowTemplate(ilObjCloudStorage::PLUGIN_PATH . "/templates/tpl.types_row.html");
        $this->getMyDataFromDb();
    }

    /**
     * Get data and put it into an array
     */
    public function getMyDataFromDb()
    {
        //todo?
        //    	$this->plugin_object->includeClass('class.ilCloudStorageConfig.php');
        // get types data with usage info
        $data = ilCloudStorageConfig::_getCloudStorageConnData(true);
        $this->setDefaultOrderField('conn_id');
        $this->setDefaultOrderDirection('asc');
        $this->setData($data);
    }

    /**
     * Fill a single data row.
     */
    protected function fillRow($a_set): void
    {
        $ilCtrl = $this->dic->ctrl();

        $ilCtrl->setParameter($this->parent_obj, 'conn_id', $a_set['conn_id']);

        $this->tpl->setVariable('TXT_ID', $a_set['conn_id']);
        $this->tpl->setVariable('TXT_TITLE', $a_set['title']);
        $this->tpl->setVariable('TXT_AVAILABILITY', $this->dic->language()->txt('rep_robj_xcls_conf_availability_' . $a_set['availability']));
        $this->tpl->setVariable('TXT_USAGES', (int) $a_set['usages']);

        $this->tpl->setVariable('CSS_HIDE_INTEGRATION', 'hidden');
        
        $this->tpl->setVariable('TXT_EDIT', $this->dic->language()->txt('edit'));
        $this->tpl->setVariable('LINK_EDIT', $ilCtrl->getLinkTarget($this->parent_obj, 'editCloudStorageConn'));

        if ($a_set['usages'] == 0) {
            $this->tpl->setVariable('TXT_DELETE', $this->dic->language()->txt('delete'));
            $this->tpl->setVariable('LINK_DELETE', $ilCtrl->getLinkTarget($this->parent_obj, 'deleteCloudStorageConn'));
        }
    }
}
