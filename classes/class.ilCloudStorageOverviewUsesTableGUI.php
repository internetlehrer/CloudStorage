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
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 * @version $Id$
 */
class ilCloudStorageOverviewUsesTableGUI extends ilTable2GUI
{
    private Container $dic;

    public ?array $filter;
    /**
     * Constructor
     *
     * @param object        parent object
     * @param string $a_parent_cmd
     * @param string $a_template_context
     */
    public function __construct(object $a_parent_obj, string $a_parent_cmd = '')
    {
        global $DIC;
        $this->dic = $DIC;
        $this->filter = [];
        parent::__construct($a_parent_obj, $a_parent_cmd);
        $this->setDefaultFilterVisiblity(true);
        $this->setDisableFilterHiding(true);
        $this->setFormAction($DIC->ctrl()->getFormAction($a_parent_obj, $a_parent_cmd));
        $this->setRowTemplate(ilObjCloudStorage::PLUGIN_PATH . "/templates/tpl.uses_row.html");
    }

    /**
     * Init the table with some configuration
     *
     *
     * @access public
     * @param $a_parent_obj
     * @param array|null $rows
     */
    public function init($a_parent_obj, ?array $rows = null)
    {
        //global $ilCtrl, $lng;
        global $DIC; /** @var Container $DIC */
        $ilCtrl = $DIC->ctrl();
        $lng = $DIC->language();

        $this->addColumn($this->dic->language()->txt('rep_robj_xcls_conn_id'), 'connTitle', '');
        $this->addColumn($lng->txt('rep_robj_xcls_repository_object'), 'parentTitle', '');
        $this->addColumn($this->dic->language()->txt('rep_robj_xcls_obj_xcls'), 'xclsObjTitle', '');
        $this->addColumn($lng->txt('object_id'), 'xclsObjId', '7%');
        $this->addColumn($this->dic->language()->txt('rep_robj_xcls_status'), 'isInTrash', '10%');
        $this->addColumn($this->dic->language()->txt('rep_robj_xcls_auth_status'), 'auth_complete', '10%');
        $this->addColumn($lng->txt('actions'), '', '5%');

        //$this->setFilterCommand('applyFilter');
        //$this->setEnableHeader(true);
        
        //$this->disable('sort');
        //$this->setEnableNumInfo(false);
        
        $this->initFilter();

        switch ($this->getParentCmd()) {
            case "applyFilter":
                $this->applyFilter();
                break;
            case "resetFilter":
                $this->_resetFilter();
                break;
        }
    }

    public function initFilter(): void
    {
     
        // cloud connections
        $conns = ilCloudStorageConfig::_getAvailableCloudStorageConn();
        $conns["-1"] = "";
        asort($conns);
        $title = new ilSelectInputGUI($this->dic->language()->txt('rep_robj_xcls_conn_id'), 'connTitle');
        $title->setOptions($conns);
        $this->addFilterItem($title);
        $title->readFromSession();
        $this->filter['connTitle'] = $title->getValue();

        $rep = new ilTextInputGUI($this->txt('repository_object'), 'parentTitle');
        $rep->setMaxLength(64);
        $rep->setSize(20);
        $this->addFilterItem($rep);
        $rep->readFromSession();
        $this->filter['parentTitle'] = $rep->getValue();

        $obj = new ilTextInputGUI($this->txt('obj_xcls'), 'xclsObjTitle');
        $obj->setMaxLength(64);
        $obj->setSize(20);
        $this->addFilterItem($obj);
        $obj->readFromSession();
        $this->filter['xclsObjTitle'] = $obj->getValue();

        $statusArr = array(
            "-1" => "",
            "1" => $this->txt('online'),
            "2" => $this->txt('offline'),
            "3" => $this->txt('in_trash'),
        );
        ksort($statusArr);
        $status = new ilSelectInputGUI($this->txt('status'), 'isInTrash');
        $status->setOptions($statusArr);
        $this->addFilterItem($status);
        $status->readFromSession();
        $this->filter['isInTrash'] = (string) $status->getValue();

        $auths = array(
            "-1" => "",
            "1" => $this->txt('authenticated'),
            "2" => $this->txt('not_authenticated')
        );
        $auth = new ilSelectInputGUI($this->txt('auth_status'), 'auth_complete');
        $auth->setOptions($auths);
        $this->addFilterItem($auth);
        $auth->readFromSession();
        $this->filter['auth_complete'] = (string) $auth->getValue();

        $showTrash = new ilCheckboxInputGUI($this->dic->language()->txt('rep_robj_xcls_show_trash'), 'showTrash');
        $this->addFilterItem($showTrash);
        $showTrash->readFromSession();

        if ($this->filter['isInTrash'] != "-1") {
            if ($this->filter['isInTrash'] == "3") {
                $showTrash->setChecked(true);
            } else {
                $showTrash->setChecked(false);
            }
        }
        
        $this->filter['showTrash'] = $showTrash->getChecked();
    }

    public function applyFilter(): void
    {
        $this->resetOffset();
        $this->writeFilterToSession();
        $this->dic->ctrl()->redirectByClass(ilCloudStorageConfigGUI::class, "overviewUses");
    }

    public function _resetFilter(): void
    {
        $this->resetOffset();
        $this->resetFilter();
        $this->dic->ctrl()->redirectByClass(ilCloudStorageConfigGUI::class, "overviewUses");
    }
    /**
     * Fill a single data row.
     */
    protected function fillRow($a_set): void
    {
        $lng = $this->dic->language();
        $ilCtrl = $this->dic->ctrl();

        $pathAlt = $lng->txt('repository');
        $nodeArr = $this->dic['tree']->getPathFull((int) $a_set['parentRefId']);
        array_shift($nodeArr);
        foreach ($nodeArr as $node) {
            $pathAlt = $pathAlt . " > " . $node['title'];
        }
        
        #var_dump($a_set); exit;
        $ilCtrl->setParameter($this->parent_obj, 'conn_id', $a_set['xclsConnId']);

        $this->tpl->setVariable('XCLS_CONN_TITLE', $a_set['connTitle']);

        // Link to Container
        $this->tpl->setVariable('TXT_PARENT', $a_set['isInTrash'] ? '<span title="' . $pathAlt . '">' . $a_set['parentTitle'] . '</span>' : '
        <a href="' . $a_set['parentLink'] . '" target="_blank" title="' . $pathAlt . '">' . $a_set['parentTitle'] . '</a>
        ');

        // Link to CloudStorage Object
        $this->tpl->setVariable('TXT_TITLE', $a_set['isInTrash'] ? $a_set['xclsObjTitle'] : '
        <a href="' . $a_set['link'] . '" target="_blank">' . $a_set['xclsObjTitle'] . '</a>
        ');

        // Object ID
        $this->tpl->setVariable('OBJ_ID', $a_set['xclsObjId']);

        // Status
        if ((bool)$a_set['isInTrash']) {
            $StatusHtml = $this->txt('in_trash');
        } else {
            $StatusHtml = ((bool) $a_set['is_online']) ? $this->txt('online') : $this->txt('offline');
        }
        
        $this->tpl->setVariable('TXT_OBJ_STATUS', '<span class="small">' . $StatusHtml . '</span>');
        
        // Auth Status
        $AuthStatusHtml = (bool)$a_set['auth_complete'] ? $this->txt('authenticated') : $this->txt('not_authenticated');
        $this->tpl->setVariable('TXT_AUTH_STATUS', '<span class="small">' . $AuthStatusHtml . '</span>');

        // Action
        $linkText = ((bool)$a_set['isInTrash']) ? $this->dic->language()->txt('rep_robj_xcls_purge') : $lng->txt('delete');
        $linkTitle = $this->dic->language()->txt('rep_robj_xcls_obj_xcls') . " (";
        $linkTitle .= $a_set['isInTrash'] ? $lng->txt('trash') : $lng->txt('repository');
        $linkTitle .= ")";
        $purge = ((bool)$a_set['isInTrash']) ? "&purge=1" : "";
        $this->tpl->setVariable(
            'TXT_ACTION',
            '<a class="il_ContainerItemCommand" href="' .
            $ilCtrl->getLinkTarget($this->parent_obj, 'confirmDeleteUsesCloudStorageConn') .
            '&parent_ref_id=' . $a_set['parentRefId'] .
            '&item_ref_id=' . $a_set['xclsRefId'] .
            $purge . 
            '&cGuiItemContent=' . rawurlencode($a_set['xclsObjTitle'] . ' &nbsp;<span class="small">(' . $a_set['connTitle'] . ')</span> ')
            . '" title="' . $linkTitle . '">' .
            $linkText . '</a>'
        );

    }
    
    private function txt(string $text): string
    {
        return $this->parent_obj->txt($text);
    }
}
