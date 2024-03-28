<?php
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

    /**
     * Constructor
     *
     * @param object        parent object
     * @param string $a_parent_cmd
     * @param string $a_template_context
     */
    public function __construct(object $a_parent_obj, string $a_parent_cmd = '', string $a_template_context = '')
    {
        global $DIC;
        $this->dic = $DIC;

        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);
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

        $this->addColumn($this->dic->language()->txt('rep_robj_xcls_plugin_configuration'), 'plugin_configuration', '');
        $this->addColumn($lng->txt('repository'), 'repository', '');
        $this->addColumn($this->dic->language()->txt('rep_robj_xcls_obj_xcls'), 'obj_xcls', '');
        $this->addColumn($lng->txt('object_id'), 'obj_id', '7%');
        $this->addColumn($lng->txt('actions'), '', '5%');
        $this->addColumn($lng->txt('status'), 'status', '5%');
        $this->setEnableHeader(true);
        $this->disable('sort');
        $this->setEnableNumInfo(false);
        $this->setRowTemplate('tpl.uses_row.html', 'Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage');
    }

    /**
     * Fill a single data row.
     */
    protected function fillRow($a_set): void
    {
        $lng = $this->dic->language();
        $ilCtrl = $this->dic->ctrl();
        #var_dump($a_set); exit;
        $ilCtrl->setParameter($this->parent_obj, 'conn_id', $a_set['xclsConnId']);

        $this->tpl->setVariable('xcls_CONN_TITLE', $a_set['connTitle']);

        // Link to Container
        $this->tpl->setVariable('TXT_PARENT', $a_set['isInTrash'] ? $a_set['parentTitle'] : '
        <a href="' . $a_set['parentLink'] . '" target="_blank">' . $a_set['parentTitle'] . '</a>
        ');

        // Link to CloudStorage Object
        $this->tpl->setVariable('TXT_TITLE', $a_set['isInTrash'] ? $a_set['xclsObjTitle'] : '
        <a href="' . $a_set['link'] . '" target="_blank">' . $a_set['xclsObjTitle'] . '</a>
        ');

        // Object ID
        $this->tpl->setVariable('OBJ_ID', $a_set['xclsObjId']);

        // Action
        $linkText = $lng->txt('delete');
        $linkTitle = $this->dic->language()->txt('rep_robj_xcls_obj_xcls') . " (";
        $linkTitle .= $a_set['isInTrash'] ? $lng->txt('trash') : $lng->txt('repository');
        $linkTitle .= ")";
        $this->tpl->setVariable(
            'TXT_ACTION',
            '<a class="il_ContainerItemCommand" href="' .
            $ilCtrl->getLinkTarget($this->parent_obj, 'confirmDeleteUsesCloudStorageConn') .
            '&parent_ref_id=' . $a_set['parentRefId'] .
            '&item_ref_id=' . $a_set['xclsRefId'] .
            '&cGuiItemContent=' . rawurlencode($a_set['xclsObjTitle'] . ' &nbsp;<span class="small">(' . $a_set['connTitle'] . ')</span> ')
            . '" title="' . $linkTitle . '">' .
            $linkText . '</a>'
        );

        // Status
        $StatusHtml = !(bool)$a_set['isInTrash'] ? (bool)$a_set['is_online'] ? 'online' : 'offline' : '<img src="templates/default/images/icon_trash.svg" style="height: 16px; width: auto; margin:0 5px 4px" />';
        $this->tpl->setVariable('TXT_STATUS', '<span class="small">' . $StatusHtml . '</span>');

    }

}
