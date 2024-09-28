<?php

declare(strict_types=1);

use ILIAS\DI\Container;

/**
 * CloudStorage configuration user interface class
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 * @version $Id$
 *
 * @ilCtrl_Calls ilCloudStorageConfigGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_IsCalledBy ilCloudStorageConfigGUI: ilObjComponentSettingsGUI
 */
class ilCloudStorageConfigGUI extends ilPluginConfigGUI
{
    private ?Container $dic = null;
    private ?ilLanguage $lng = null;
    private ?ilCloudStorageConfig $object = null;
    private ?ilPropertyFormGUI $form = null;
    private array $pluginIni = [];
    private array $fsTypesAvailable = [];
    private string $fsDefaultType = "";
    private bool $debug = false;
    
    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->lng = $this->dic->language();
        $this->pluginIni = ilObjCloudStorage::setPluginIniSet();
        $ftypes = $this->pluginIni["fs_types_available"];
        if (!is_array($ftypes)) {
            $ftypes = [$ftypes];
        }
        $this->fsTypesAvailable = $ftypes;
        if (!empty($this->pluginIni["fs_default_type"])) {
            $this->fsDefaultType = $this->pluginIni["fs_default_type"];
        } else {
            $this->fsDefaultType = $this->fsTypesAvailable[0];
        }
        $this->debug = (bool) $this->pluginIni["debug"];
    }

    public function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case 'migrateOld':
            case 'deleteOld':
            case 'migrateOldConfirmed':
            case 'deleteOldConfirmed':
                $this->initTabs();
                $this->$cmd();
                break;
            case 'createCloudStorageConn':
                $this->initTabs();
                $this->$cmd();
                break;
            case 'editCloudStorageConn':
            case 'deleteCloudStorageConn':
                if ($this->hasConnId()) {
                    $connId = $this->getConnId();
                    $this->object = new ilCloudStorageConfig($connId);
                    $this->dic->ctrl()->setParameter($this, 'conn_id', $connId);
                } else {
                    $this->object = new ilCloudStorageConfig();
                }
                $this->initTabs('edit_type');
                $this->$cmd();
                break;
            case "save":
                $this->initTabs('edit_type');
                $this->$cmd();
                break;
            case "configure":
                $this->initTabs();
                $this->$cmd();
                break;
            case "overviewUses":
            case "applyFilter":
            case "resetFilter":
                $this->initTabs();
                $this->initOverviewUsesTableGUI($cmd);
                break;
            default:
                $this->initTabs();
                if (!$cmd) {
                    $cmd = "configure";
                }
                $this->$cmd();
                break;
        }
    }

    protected function initTabs(string $a_mode = ""): void
    {
        $ilCtrl = $this->dic->ctrl();
        $ilTabs = $this->dic->tabs();
        $lng = $this->dic->language();

        switch ($a_mode) {
            case "edit_type":
                $ilTabs->clearTargets();
                $ilTabs->setBackTarget(
                    $this->plugin_object->txt('configure'),
                    $ilCtrl->getLinkTarget($this, 'configure')
                );
                break;

            default:
                $ilTabs->addTab(
                    "configure",
                    $this->plugin_object->txt('configure'),
                    $ilCtrl->getLinkTarget($this, 'configure')
                );
                $ilTabs->addTab(
                    "overview_uses",
                    $this->plugin_object->txt('overview_uses'),
                    $ilCtrl->getLinkTarget($this, 'overviewUses')
                );
        }
    }

    public function configure(): void
    {
        $tpl = $this->dic->ui()->mainTemplate();
        $ilTabs = $this->dic->tabs();
        $ilTabs->activateTab('configure');
        $table_gui = new ilCloudStorageConnTableGUI($this, 'configure');
        $table_gui->init($this);
        $html = $table_gui->getHTML();
        $tpl->setContent($html);
    }
    
    private function createCloudStorageConn(): void
    {
        $tpl = $this->dic->ui()->mainTemplate();
        if (count($this->fsTypesAvailable) > 1) {
            $this->form = new ilPropertyFormGUI();
            $this->form->setTitle($this->plugin_object->txt("plugin_configuration"));
            $this->form->setId('plugin_configuration');
            $this->form->setFormAction($this->dic->ctrl()->getFormAction($this));
            $this->initSelectionForm();
            $tpl->setContent($this->form->getHTML());
        } else {
            $service_id = array_search($this->fsTypesAvailable[0], ilCloudStorageConfig::AVAILABLE_FS_CONN);
            $this->object = ilCloudStorageConfig::getInstance();
            $this->object->setServiceId($service_id);
            $this->object->setDefaultValues();
            $this->initConfigurationForm('configureNewCloudStorageConn');
            $this->getValues();
            $tpl->setContent($this->form->getHTML());
        }
    }

    private function initSelectionForm(): void {
        $rg = new ilRadioGroupInputGUI($this->plugin_object->txt("service_type_title"), "service_id");
        $rg->setRequired(true);
        foreach ($this->fsTypesAvailable as $service_type) {
            $ro = new ilRadioOption($service_type, array_search($service_type, ilCloudStorageConfig::AVAILABLE_FS_CONN));
            $rg->addOption($ro);
        }
        $rg->setValue(array_search($this->fsDefaultType, ilCloudStorageConfig::AVAILABLE_FS_CONN));
        $this->form->addItem($rg);
        $this->form->addCommandButton("editCloudStorageConn", $this->plugin_object->txt("add_type"));
        $this->form->addCommandButton("configure", $this->lng->txt("cancel"));
    }

    private function editCloudStorageConn(): void
    {
        $this->dic->tabs()->activateTab('configure');
        $this->initConfigurationForm();
        $this->getValues();
        $this->dic->ui()->mainTemplate()->setContent($this->form->getHTML());
    }

    private function deleteCloudStorageConn(): void
    {
        $this->object = ilCloudStorageConfig::getInstance($this->dic->http()->wrapper()->query()->retrieve('conn_id', $this->dic->refinery()->kindlyTo()->int()));

        $gui = new ilConfirmationGUI();
        $gui->setFormAction($this->dic->ctrl()->getFormAction($this));
        $gui->setHeaderText($this->dic->language()->txt('rep_robj_xcls_delete_conn'));
        $gui->addItem('conn_id', (string) $this->object->getConnId(), $this->object->getTitle());
        $gui->setConfirm($this->dic->language()->txt('rep_robj_xcls_delete'), 'deleteCloudStorageConnConfirmed');
        $gui->setCancel($this->dic->language()->txt('cancel'), 'configure');

        $this->dic->ui()->mainTemplate()->setContent($gui->getHTML());
    }

    private function deleteCloudStorageConnConfirmed(): void
    {
        $this->object = ilCloudStorageConfig::getInstance($this->dic->http()->wrapper()->query()->retrieve('conn_id', $this->dic->refinery()->kindlyTo()->int()));

        ilCloudStorageConfig::_deleteCloudStorageConn($this->dic->http()->wrapper()->query()->retrieve('conn_id', $this->dic->refinery()->kindlyTo()->int()));
        $this->dic->ui()->mainTemplate()->setOnScreenMessage('success', $this->dic->language()->txt('rep_robj_xcls_conn_deleted'), true);
        $this->dic->ctrl()->redirect($this, 'configure');
    }

    private function initConfigurationFormByPlatform(string $service_id): ilPropertyFormGUI
    {
        $pl = $this->getPluginObject();
        
        $sh = new ilFormSectionHeaderGUI();
        $sh->setTitle($pl->txt('service_type_title') . " : " . ilCloudStorageConfig::AVAILABLE_FS_CONN[$service_id]);
        $this->form->addItem($sh);
        $hi = new ilHiddenInputGUI("service_id");
        $hi->setValue($service_id);
        $this->form->addItem($hi);

        $ti = new ilTextInputGUI($pl->txt("title"), "title");
        $ti->setRequired(true);
        $ti->setMaxLength(256);
        $ti->setSize(60);
        $ti->setInfo($pl->txt("info_title"));
        $this->form->addItem($ti);
        
        // availability

        $rg = new ilRadioGroupInputGUI($this->plugin_object->txt("conf_availability"), "availability");
        $rg->setRequired(true);
        $rg->setInfo($this->plugin_object->txt('info_availability'));
        $ros = array(
            ilCloudStorageConfig::AVAILABILITY_CREATE => array(
                'text' => $this->plugin_object->txt('conf_availability_' . ilCloudStorageConfig::AVAILABILITY_CREATE),
                'info' => $this->plugin_object->txt('conf_availability_info_' . ilCloudStorageConfig::AVAILABILITY_CREATE)
            ),
            ilCloudStorageConfig::AVAILABILITY_EXISTING => array(
                'text' => $this->plugin_object->txt('conf_availability_' . ilCloudStorageConfig::AVAILABILITY_EXISTING),
                'info' => $this->plugin_object->txt('conf_availability_info_' . ilCloudStorageConfig::AVAILABILITY_EXISTING)
            ),
            ilCloudStorageConfig::AVAILABILITY_NONE => array(
                'text' => $this->plugin_object->txt('conf_availability_' . ilCloudStorageConfig::AVAILABILITY_NONE),
                'info' => $this->plugin_object->txt('conf_availability_info_' . ilCloudStorageConfig::AVAILABILITY_NONE)
            )
        );
        foreach ($ros as $key => $value) {
            $ro = new ilRadioOption($value['text'], (string) $key, $value['info']);
            $rg->addOption($ro);
        }
        $this->form->addItem($rg);

        // Hint TextArea
        //$ti = new ilTextInputGUI($pl->txt("hint"), "hint");
        //$ti->setInfo($pl->txt("info_hint"));
        //$this->form->addItem($ti);

        $hi = new ilHiddenInputGUI("hint");
        $hi->setValue("not supported");
        $this->form->addItem($hi);

        // Platform specific form items
        include_once __DIR__ . "/" . ilCloudStorageConfig::AVAILABLE_FS_CONN[$service_id] . '/config.php';

        return $this->form;
    }

    public function initConfigurationForm(): ilPropertyFormGUI
    {
        
        $pl = $this->getPluginObject();

        $this->form = new ilPropertyFormGUI();

        $this->form->setTitle($pl->txt("plugin_configuration"));
        $this->form->setId('plugin_configuration');
        $this->form->setFormAction($this->dic->ctrl()->getFormAction($this));

        if ($this->hasConnId()) {
            $this->form->addCommandButton("save", $this->lng->txt("save"));
        } else {
            $this->form->addCommandButton("save", $this->lng->txt("create"));
        }
        $this->form->addCommandButton("configure", $this->lng->txt("cancel"));


        ############################################################################################################################
        
        $plattform = null;
        if (null === $this->object->getServiceId()) {
            if($this->dic->http()->wrapper()->post()->has('service_id')) {
                $plattform = $this->dic->http()->wrapper()->post()->retrieve('service_id', $this->dic->refinery()->kindlyTo()->string());
            // } else {
            //     $plattform = $this->dic->ctrl()->getParameterArray($this)['configureNewCloudStorageConn'];
            } else {
                // error
            }
            $this->object->setServiceId($plattform);
            $this->object->setDefaultValues();
        }
        $this->form = $this->initConfigurationFormByPlatform($this->object->getServiceId());

        ############################################################################################################################
        $defField = function ($name, $value) {
            $field = new ilHiddenInputGUI($name);
            if ($value == null) {
                $value = '';
            }
            $field->setValue((string) $value);
            return $field;
        };

        $formFieldItems = $this->form->getInputItemsRecursive();
        $formHasField = [];

        foreach ($formFieldItems as $key => $item) {
            $formHasField[] = $item->getPostVar();
        }
        #echo '<pre>'; var_dump($this->getDefaultFieldAndValues($this->object->getServiceId())); exit;
        foreach ($this->getDefaultFieldAndValues() as $name => $value) {
            if(false === array_search($name, $formHasField)) {
                $this->form->addItem($defField($name, $value));
            }
        }
        // ToDo Sn: check if $this->form can be replaced with $form
        return $this->form;
    }

    public function getValues()
    {
        $values["conn_id"]                  = $this->object->getConnId();
        $values["title"]                    = $this->object->getTitle();
        $values["hint"]                     = $this->object->getHint();
        $values["availability"]          = $this->object->getAvailability();
        $values['account']                  = $this->object->getAccount();
        $values['account_username']         = $this->object->getAccountUsername();
        $values['account_password']         = $this->object->getAccountPassword();
        $values['base_directory']           = $this->object->getBaseDirectory();
        $values['bd_allow_override']        = $this->object->getBaseDirectoryAllowOverride();
        $values['col_app_integration']     = $this->object->getCollaborationAppIntegration();
        $values['col_app_formats']         = $this->object->getCollaborationAppFormats();
        $values['col_app_mapping_field']   = $this->object->getCollaborationAppMappingField();
        $values['col_app_url']             = $this->object->getCollaborationAppUrl();
        $values['oa2_client_id']            = $this->object->getOAuth2ClientId();
        $values['oa2_client_secret']        = $this->object->getOAuth2ClientSecret();
        $values['oa2_path']                 = $this->object->getOAuth2Path();
        $values['oa2_token_request_auth']   = $this->object->getOAuth2TokenRequestAuth();
        $values['server_url']               = $this->object->getServerURL();
        $values['proxy_url']               = $this->object->getProxyURL();
        $values['webdav_url']               = $this->object->getWebDavURL();
        $values['obj_ids_special']			= $this->object->getObjIdsSpecial();
        $values['service_id']			    = $this->object->getServiceId();
        $values['webdav_path']              = $this->object->getWebDavPath();
        $values['access_token']             = $this->object->getAccessToken();
        $values['refresh_token']            = $this->object->getRefreshToken();
        $values['auth_method']              = $this->object->getAuthMethod();
        $values["frmObjIdsSpecial"]         = $this->object->getObjIdsSpecial();
        $values["service_id"]              = $this->object->getServiceId();
        $this->form->setValuesByArray($values);
    }

    public function save(bool $redirect = true): void
    {
        
        $tpl = $this->dic->ui()->mainTemplate();

        $pl = $this->getPluginObject();

        if ($this->hasConnId()) {
            $this->object = ilCloudStorageConfig::getInstance($this->getConnId());
        } else {
            $this->object = ilCloudStorageConfig::getInstance();
        }
        
        $form = $this->initConfigurationForm();

        if ($form->checkInput()) {

            $this->object->setConnId(!!(bool)($connId = (int) $form->getInput("conn_id")) ? $connId : null);
            $this->object->setTitle($form->getInput("title"));
            $this->object->setHint((string)$this->object->removeUnsafeChars($form->getInput("hint")));
            $this->object->setAvailability((int) $form->getInput("availability"));
            $this->object->setAccount((bool) ($form->getInput("account")));
            $this->object->setAccountUsername($form->getInput("account_username"));
            $this->object->setAccountPassword($form->getInput("account_password"));
            $this->object->setBaseDirectory($form->getInput("base_directory"));
            $this->object->setBaseDirectoryAllowOverride((bool) $form->getInput("bd_allow_override"));
            $this->object->setCollaborationAppIntegration((bool) $form->getInput("col_app_integration"));
            $this->object->setCollaborationAppFormats($form->getInput("col_app_formats"));
            $this->object->setCollaborationAppMappingField($form->getInput("col_app_mapping_field"));
            $this->object->setCollaborationAppUrl($form->getInput("col_app_url"));
            $this->object->setAuthMethod($form->getInput("auth_method"));
            $this->object->setOauth2ClientId(trim($form->getInput("oa2_client_id")));
            $this->object->setOauth2ClientSecret(trim($form->getInput("oa2_client_secret")));
            $this->object->setOauth2Path($form->getInput("oa2_path"));
            $this->object->setOauth2TokenRequestAuth($form->getInput("oa2_token_request_auth"));
            $this->object->setServerUrl($form->getInput("server_url"));
            $this->object->setProxyUrl($form->getInput("proxy_url"));
            $this->object->setWebDavUrl($form->getInput("webdav_url"));
            $this->object->setWebDavPath($form->getInput("webdav_path"));
            $this->object->setServiceId($form->getInput("service_id"));
            $this->object->save((bool)$form->getInput("conn_id"));

            if($redirect) {
                $this->dic->ui()->mainTemplate()->setOnScreenMessage('success', $pl->txt("saving_invoked"), true);
                $this->dic->ctrl()->redirect($this, "configure");
            } else {
                $this->dic->ctrl()->setParameter($this, 'conn_id', $this->dic->http()->wrapper()->query()->retrieve('conn_id', $this->dic->refinery()->kindlyTo()->int()));
                $this->dic->ctrl()->redirect($this, 'editCloudStorageConn');
            }
        }

        $form->setValuesByPost();
        $tpl->setContent($form->getHtml());
    }

    public function getDefaultFieldAndValues(): array
    {
        $values = [];
        $values['conn_id']                  = $this->object->getConnId();
        $values['title']                    = $this->object->getTitle();
        $values['availability']          = $this->object->getAvailability();
        $values['hint']                     = $this->object->getHint();
        $values['account']                  = $this->object->getAccount();
        $values['account_username']         = $this->object->getAccountUsername();
        $values['account_password']         = $this->object->getAccountPassword();
        $values['base_directory']           = $this->object->getBaseDirectory();
        $values['bd_allow_override']        = $this->object->getBaseDirectoryAllowOverride();
        $values['col_app_integration']     = $this->object->getCollaborationAppIntegration();
        $values['col_app_formats']         = $this->object->getCollaborationAppFormats();
        $values['col_app_mapping_field']   = $this->object->getCollaborationAppMappingField();
        $values['col_app_url']             = $this->object->getCollaborationAppUrl();
        $values['oa2_client_id']            = $this->object->getOAuth2ClientId();
        $values['oa2_client_secret']        = $this->object->getOAuth2ClientSecret();
        $values['oa2_path']                 = $this->object->getOAuth2Path();
        $values['oa2_token_request_auth']   = $this->object->getOAuth2TokenRequestAuth();
        $values['server_url']               = $this->object->getServerURL();
        $values['obj_ids_special']			= $this->object->getObjIdsSpecial();
        $values['service_id']			    = $this->object->getServiceId();
        $values['webdav_path']              = $this->object->getWebDavPath();
        $values['access_token']             = $this->object->getAccessToken();
        $values['refresh_token']            = $this->object->getRefreshToken();
        $values['auth_method']              = $this->object->getAuthMethod();
        $values["frmObjIdsSpecial"]         = $this->object->getObjIdsSpecial();
        $values["service_id"]              = '';
        return $values;
    }

    public function initOverviewUsesTableGUI(string $cmd, $html = true): void
    {
        $tpl = $this->dic->ui()->mainTemplate();
        $ilTabs = $this->dic->tabs();

        $ilTabs->activateTab('overview_uses');
        
        $table_gui = new ilCloudStorageOverviewUsesTableGUI($this, $cmd);
        $table_gui->init($this);
        $rows = ilCloudStorageConfig::_getCloudStorageConnOverviewUses($table_gui->filter);
        foreach ($rows as $key => $row) {
            if((bool)$row['isInTrash']) {
                if(ilObject::_isInTrash($row['xclsRefId'])) {
                    $row['parentRefId'] = $row['xclsRefId'];
                } else {
                    $row['isInTrash'] = 0;
                }
            }
            $rows[$key]['parentLink'] = ilLink::_getLink($row['parentRefId']);
            $rows[$key]['link'] = ilLink::_getLink($row['xclsRefId']);
        } // EOF foreach ($rows as $key => $row)
        $table_gui->setData($rows);
        #var_dump($rows); exit;
        $tpl->setContent($table_gui->getHTML());

        if(!$html) {
            #$table_gui->downloadCsv();
        }
    }

    public function confirmDeleteUsesCloudStorageConn(): void
    {
        global $DIC;

        $DIC->tabs()->activateTab('overview_uses');

        $item_ref_id = 0;
        $itemType = '';
        if ($this->dic->http()->wrapper()->query()->has('item_ref_id')) {
            $item_ref_id = $this->dic->http()->wrapper()->query()->retrieve('item_ref_id', $this->dic->refinery()->kindlyTo()->int());
            $itemType = ilObject::_lookupType($item_ref_id, true);
        }
        $parent_ref_id = 0;
        if ($this->dic->http()->wrapper()->query()->has('parent_ref_id')) {
            $parent_ref_id = $this->dic->http()->wrapper()->query()->retrieve('parent_ref_id', $this->dic->refinery()->kindlyTo()->int());
        }

        if($item_ref_id === 0 || $parent_ref_id === 0 || $itemType !== $this->getPluginObject()->getId()) {
            $this->returnFailure($this->dic->language()->txt('select_one'));
        }

        $c_gui = new ilConfirmationGUI();

        $header = ($this->dic->http()->wrapper()->query()->has('purge')) ? $DIC->language()->txt("rep_robj_xcls_purge_confirm") : $DIC->language()->txt("rep_robj_xcls_info_delete_folder");
        
        // set confirm/cancel commands
        $c_gui->setFormAction($DIC->ctrl()->getFormAction($this, "overviewUses"));
        $c_gui->setHeaderText($header);
        $c_gui->setCancel($DIC->language()->txt("cancel"), "overviewUses");
        $c_gui->setConfirm($DIC->language()->txt("confirm"), "deleteUsesCloudStorageConn");

        // add items to delete
        //include_once('Modules/Course/classes/class.ilCourseFile.php');
        $cGuiItemContent = $DIC->http()->wrapper()->query()->retrieve('cGuiItemContent', $DIC->refinery()->kindlyTo()->string());
        $c_gui->addItem("item_ref_id", (string) $item_ref_id, $cGuiItemContent);
        $c_gui->addHiddenItem('parent_ref_id', (string) $parent_ref_id);
        $DIC->ui()->mainTemplate()->setContent($c_gui->getHTML());

    }

    public function deleteUsesCloudStorageConn(): void
    {
        $item_ref_id = 0;
        $itemType = '';
        if ($this->dic->http()->wrapper()->post()->has('item_ref_id')) {
            $item_ref_id = $this->dic->http()->wrapper()->post()->retrieve('item_ref_id', $this->dic->refinery()->kindlyTo()->int());
            $itemType = ilObject::_lookupType($item_ref_id, true);
        }
        $parent_ref_id = 0;
        if ($this->dic->http()->wrapper()->post()->has('parent_ref_id')) {
            $parent_ref_id = $this->dic->http()->wrapper()->post()->retrieve('parent_ref_id', $this->dic->refinery()->kindlyTo()->int());
        }

        if($item_ref_id === 0 || $parent_ref_id === 0 || $itemType !== $this->getPluginObject()->getId()) {
            $this->returnFailure($this->dic->language()->txt('select_one'));
        }

        try {
            if(!$this->dic->settings()->get('enable_trash')) {
                ilRepUtil::deleteObjects($item_ref_id, [$item_ref_id]);
            } elseif(ilObject::_isInTrash($item_ref_id)) {
                ilRepUtil::removeObjectsFromSystem([$item_ref_id]);
            } else {
                ilRepUtil::deleteObjects($parent_ref_id, [$item_ref_id]);
            }
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('success', $this->dic->language()->txt('deleted'));
        } catch (ilRepositoryException $e) {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->dic->language()->txt('not_deleted'));
        }

        $this->dic->ctrl()->redirect($this, 'overviewUses');
    }

    private function returnFailure(string $txt = 'error', bool $redirect = true, string $gui = 'overviewUses'): void
    {
        $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $txt);
        $this->dic->ctrl()->redirect($this, $gui);
    }

    private function hasConnId(): bool {
        return $this->dic->http()->wrapper()->query()->has('conn_id');
    }

    private function getConnId(): int {
        return $this->dic->http()->wrapper()->query()->retrieve('conn_id', $this->dic->refinery()->kindlyTo()->int());
    }

    public function txt(string $text): string
    {
        return $this->plugin_object->txt($text);
    }
}
