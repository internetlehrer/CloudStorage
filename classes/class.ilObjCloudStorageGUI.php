<?php

use ILIAS\DI\Container;
//use ILIAS\UI\Component\MessageBox\MessageBox;

/**
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 *
 * $Id$
 *
 * Integration into control structure:
 * - The GUI class is called by ilRepositoryGUI
 * - GUI classes used by this class are ilPermissionGUI (provides the rbac
 *   screens) and ilInfoScreenGUI (handles the info screen).
 *
 * @ilCtrl_isCalledBy ilObjCloudStorageGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls ilObjCloudStorageGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI, ilRepositorySearchGUI, ilCloudStorageOwnCloudGUI, ilObjFileUploadHandlerGUI
 *
 */
class ilObjCloudStorageGUI extends ilObjectPluginGUI
{
    public const START_TYPE = [
        'OWNCLOUD'  => 'start'
    ];

    public const INTEGER = "integer";

    public const INT = "int";

    public const BOOL = "bool";

    public const STRING = "string";

    // Sn:  read from pluginIni should only read and parse once, needs concept (see ilCloudStorageConfigGUI)
    private bool $debug = true;

    public ?ilObject $object = null;
    public ?ilCloudStorageConfig $config = null;

    public ?ilCloudStorageServiceInterface $service = null;

    public ?ilCloudStorageServiceGUIInterface $serviceGUI = null;

    // Sn: ToDo: same as service->getServiceId streamlining required
    public string $platform = '';

    public string $sessType = 'cloudstorage';

    public Container $dic;

    public ilPropertyFormGUI $form;

    public ?ilGlobalTemplate $tpl_file_tree;

    public ?ilAdvancedSelectionListGUI $selection_list = null;

    public ?ilCloudStorageFileNode $node = null;

    public function __construct(int $a_ref_id = 0, int $a_id_type = self::REPOSITORY_NODE_ID, int $a_parent_node_id = 0)
    {
        global $DIC; 

        parent::__construct($a_ref_id, $a_id_type, $a_parent_node_id);

        $this->dic = $DIC;

        $this->lng->loadLanguageModule('rep_robj_xcls');

        $this->platform = $this->object instanceof ilObjCloudStorage ? ilCloudStorageConfig::getInstance($this->object->getConnId())->getServiceId() : $this->platform; #

        $this->config = $this->object instanceof ilObjCloudStorage ? ilCloudStorageConfig::getInstance($this->object->getConnId()) : $this->config;

        // bug: object item itself should not appear in locator ilobjectactivationgui and ilconditionhandlergui: crashes on click
        // navigation is provided by a back button
        // from this point there is no control about locator items(?) therefore i set an extra parameter for contentShow: dont show anything redirect to parent
        
        if (strtolower($this->dic->ctrl()->getCmdClass()) == "ilobjectactivationgui" || strtolower($this->dic->ctrl()->getCmdClass()) == "ilconditionhandlergui") {
            $this->dic->ctrl()->setParameter($this ,"go_back", "1");
        }

        if($this->object instanceof ilObjCloudStorage) {
            assert($this->object instanceof ilObjCloudStorage);
            $serviceClass = ilCloudStorageConfig::AVAILABLE_XCLS_SERVICES[$this->platform];
            $this->service = new $serviceClass($this->object->getRefId(), $this->object->getConnId());
            $serviceGUI = $serviceClass."GUI";
            $this->serviceGUI = new $serviceGUI($this);
        }
    }

    protected function afterConstructor(): void
    {
        // anything needed after object has been constructed
    }

    final public function getType(): string
    {
        return "xcls";
    }

    public function performCommand(string $cmd): void
    {

        //assert($this->object instanceof ilObjCloudStorage);
        $this->dic->logger()->root()->debug("cmd " . $cmd);

        assert($this->object instanceof ilObjCloudStorage);
        assert($this->service instanceof ilCloudStorageServiceInterface);
        $this->dic->ui()->mainTemplate()->setAlertProperties($this->getAlertProperties());
        // OAuth2
        if ($this->config->getOAuth2Active()) {
            if (!$this->dic->http()->wrapper()->query()->has('authMode')) {
                if (!$this->object->getAuthComplete()) {
                    if ($this->checkPermissionBool("write") && $this->object->currentUserIsOwner()) {
                        $this->serviceAuth($this->object);
                    } else {
                        $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("only_owner"), true);
                        $this->redirectToRefId($this->parent_id);
                    }
                } else {
                    try {
                        $this->service->checkConnection();
                    } catch(ilCloudStorageException $e) {
                        $this->handleConnectionException($e, false);
                    }
                }
            }
        } else { // BasicAuth
            // Sn: ToDo
            $this->dic->logger()->root()->debug("BasicAuth");
        }
        
        switch ($cmd) {
            case "editProperties":
                $this->checkPermission("write");
                $this->serviceGUI->editProperties();
                break;
            case "updateProperties":
                $this->checkPermission("write");
                $this->$cmd();
                break;
            case "afterServiceAuth":
                $this->checkPermission("write");
                $this->service->afterAuthService();
                break;
            case "showContent":
                $this->checkPermission("read");
                $this->$cmd();
                break;
            case "asyncGetBlock":
                $this->checkPermission("read");
                $this->$cmd();
                break;
            case "createFolder": 
            case "asyncCreateFolder":
            case "cancelCreateFolder":
                $this->checkPermission("read");
                $this->checkPermission("folders_create");
                $this->$cmd();
                break;
            case "uploadFiles":
            case "asyncUploadFile":
            case "cancelUploadFiles":
                $this->checkPermission("read");
                $this->checkPermission("upload");
                $this->$cmd();
                break;
            case "getFile":
                $this->checkPermission("read");
                $this->checkPermission("download");
                $this->$cmd();
                break;
            case "deleteItem":
            case "asyncDeleteItem":
            case "cancelDeleteItem":
                $this->checkPermission("read");
                if ($this->checkPermissionBool("delete_files") || $this->checkPermissionBool("delete_folders")) {
                    $this->$cmd();
                }
                break;
            case "openInPlatform":
                $this->checkPermission("read");
                $this->serviceGUI->openInPlatform();
                break;
            default:
                $this->checkPermission("read");
                $this->showContent();
                break;
        }
    }

    private function handleConnectionException(ilCloudStorageException $e) {
        assert($this->object instanceof ilObjCloudStorage);
        assert($this->service instanceof ilCloudStorageServiceInterface);
        switch ($e->getCode()) {
            case ilCloudStorageException::NO_CONNECTION:
                // never ignore no_connection exception
                $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage(), true);
                $this->redirectToRefId($this->parent_id);
                break;
            case ilCloudStorageException::NOT_AUTHORIZED:
                // ignore exception on authMode
                if (!$this->dic->http()->wrapper()->query()->has('authMode')) {
                    
                    // there is no token for user
                    if (!$this->service->checkAndRefreshAuthentication()) {
                        $this->object->setAuthComplete(false);
                        $this->object->doUpdate();    
                        if ($this->checkPermissionBool("write") && $this->object->currentUserIsOwner()) {                        
                            $this->serviceAuth();
                        } else {
                            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->object->txt('only_owner'), true);
                            ilObjCloudStorageGUI::_redirectToRefId($this->parent_id);
                        }
                    } else {
                        // there is a valid token (not expired)
                        $this->dic->logger()->root()->debug("checkAndRefreshAuthentication true");
                        // check connection: maybe token is valid but auth was deleted on cloud provider
                        try {
                            $this->service->checkConnection();
                        } catch(ilCloudStorageException $e) {
                            if ($e->getCode() == ilCloudStorageException::NOT_AUTHORIZED) {
                                $this->object->setAuthComplete(false);
                                $this->object->doUpdate();
                                if ($this->checkPermissionBool("write") && $this->object->currentUserIsOwner()) {
                                    ilCloudStorageOwnCloudToken::deleteUserToken($this->object->getConnId());
                                    $this->serviceAuth();                    
                                } else {
                                    $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->object->txt('only_owner'), true);
                                    ilObjCloudStorageGUI::_redirectToRefId($this->parent_id);
                                }
                            }
                        }
                    }
                }
                break;
        }
    }

    protected function serviceAuth()
    {
        try {
            $this->dic->logger()->root()->debug("ilObjCloudStorageGUI serviceAuth");
            $this->service->authService($this->dic->ctrl()->getLinkTarget($this, "afterServiceAuth") . "&authMode=true");
        } catch (Exception $e) {
            $this->dic->logger()->root()->debug("ilObjCloudStorageGUI error serviceAuth " . $e->getMessage());
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("cld_auth_failed"), true);
            $this->redirectToRefId($this->parent_id);
        }
    }

    public function getAlertProperties(): array {
        assert($this->object instanceof ilObjCloudStorage);

        if (!$this->object->getAuthComplete()) {
            return array(["alert" => true,
                     "property" => "Status",
                     "value" => $this->txt("cld_not_authenticated_offline")]);
        }

        if (!$this->object->getOnline()) {
            return array(["alert" => true,
                     "property" => "Status",
                     "value" => $this->txt("offline")]);
        }

        return [];
    }

    // addProperty / addSection not working, addButton works (?)
    // public function addInfoItems(ilInfoScreenGUI $info): void
    // {
    //     $info->addProperty("Test", "Test");
    // }

    public function setTabs(): void
    {
        $ilTabs = $this->dic->tabs();
        $ilCtrl = $this->dic->ctrl();
        $ilAccess = $this->dic->access();
        assert($this->object instanceof ilObjCloudStorage);
        //$settings = ilCloudStorageConfig::getInstance($this->object->getConnId());
        
        // tab for the "show content" command
        if ($ilAccess->checkAccess("read", "", $this->object->getRefId())) {
            $ilTabs->addTab("content", $this->txt($this->sessType), $ilCtrl->getLinkTarget($this, "showContent"));
        }

        // standard info screen tab
        $this->addInfoTab();

        // a "properties" tab
        if ($ilAccess->checkAccess("write", "", $this->object->getRefId())) {
            $ilTabs->addTab("properties", $this->txt("properties"), $ilCtrl->getLinkTarget($this, "editProperties"));
        }

        // standard permission tab
        $this->addPermissionTab();
    }

    /*
    public function initCreationForms(string $new_type): array
    {
        $forms = [
            self::CFORM_NEW => $this->initCreateForm($new_type),
            self::CFORM_IMPORT => $this->initImportForm($new_type),
            self::CFORM_CLONE => $this->fillCloneTemplate(null, $new_type)
        ];

        return $forms;
    }
    */

    public function initCreationForms(string $new_type): array
    {
        $forms = [
            self::CFORM_NEW => $this->initCreateForm($new_type)
        ];
        return $forms;
    }
    
    public function initCreateForm($a_new_type): ilPropertyFormGUI
    {
        $availableConns = ilCloudStorageConfig::_getAvailableCloudStorageConn(true);
        if (count($availableConns) == 0) {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("no_active_conn"), true);
            //ilObjectGUI::redirectToRefId($this->parent_id);
            $this->redirectToRefId($this->parent_id);
        }
        $form = parent::initCreateForm($a_new_type);
        // CloudStorageConn selection
        $combo = new ilSelectInputGUI($this->txt("conn_id"), 'conn_id');
        $combo->setRequired(true);
        $combo->setOptions(ilCloudStorageConfig::_getAvailableCloudStorageConn(true));
        //$combo->setInfo($pl->txt('info_platform_chg_reset_data'));
        $form->addItem($combo);

        // online
        $cb = new ilCheckboxInputGUI($this->lng->txt("online"), "online");
        $form->addItem($cb);

        return $form;
    }

    /**
     * @param ilObject $newObj
     * @global $DIC
     */
    public function afterSave(ilObject $newObj): void
    {
        $this->dic->logger()->root()->debug("afterSave");
        $form = $this->initCreateForm('xcls');
        $form->checkInput();
        assert($newObj instanceof ilObjCloudStorage);
        // Sn: ToDo ?
        //$newObj->setAuthUser($DIC->user()->getEmail());
        $newObj->setOwnerId($this->dic->user()->getId());
        $newObj->createFolder((int) $form->getInput("online"), $form->getInput("conn_id"));
        $newObj->update();
        
        parent::afterSave($newObj);
    }

    /**
     * After object has been created -> jump to this command
     */
    public function getAfterCreationCmd(): string
    {
        return "editProperties";
    }

    /**
     * Get standard command
     */
    public function getStandardCmd(): string
    {
        return "showContent";
    }

    /**
     * Edit Properties. This commands uses the form class to display an input form.
     */
    public function editProperties(): void
    {
        $this->dic->tabs()->activateTab("properties");
        $this->initPropertiesForm();
        $this->getPropertiesValues();
        $this->dic->ui()->mainTemplate()->setContent($this->form->getHTML());
    }

    public function initPropertiesForm(): void
    {
        assert($this->object instanceof ilObjCloudStorage);
        assert($this->serviceGUI instanceof ilCloudStorageServiceGUIInterface);
        $this->form = new ilPropertyFormGUI();
        // title
        $ti = new ilTextInputGUI($this->lng->txt("title"), "title");
        $ti->setRequired(true);
        $ti->setMaxLength(64);
        $this->form->addItem($ti);

        // description
        $ta = new ilTextAreaInputGUI($this->lng->txt("description"), "desc");
        $this->form->addItem($ta);

        // TileImage
        $this->form = $this->dic->object()->commonSettings()->legacyForm($this->form, $this->object)->addTileImage();

        // ConnID
        $info = new ilNonEditableValueGUI($this->txt("conn_id"));
        $info->setValue(ilCloudStorageConfig::_getCloudStorageConnData()[$this->object->getConnId()]['title']);
        $this->form->addItem($info);


        // SpecialID
        $info = new ilNonEditableValueGUI($this->lng->txt("object_id"));
        $info->setValue($this->object->getId());
        $this->form->addItem($info);


        // online
        $cb = new ilCheckboxInputGUI($this->lng->txt("online"), "online");
        $this->form->addItem($cb);

        // service
        $this->serviceGUI->initPropertiesForm();

        $this->form->addCommandButton("updateProperties", $this->lng->txt("save"));
        $this->form->setTitle($this->txt("edit_properties"));
        $this->form->setFormAction($this->dic->ctrl()->getFormAction($this));
 
    }

    public function getPropertiesValues(): void
    {
        assert($this->object instanceof ilObjCloudStorage);
        assert($this->serviceGUI instanceof ilCloudStorageServiceGUIInterface);
        $values["title"] = ilStr::shortenTextExtended($this->object->getTitle(), 64, true);
        $values["desc"] = $this->object->getDescription();
        $values["online"] = $this->object->getOnline();
        $this->serviceGUI->getPropertiesValues($values);
        $this->form->setValuesByArray($values);
    }

    public function updateProperties(): void
    {
        assert($this->object instanceof ilObjCloudStorage);
        assert($this->serviceGUI instanceof ilCloudStorageServiceGUIInterface);
        $this->initPropertiesForm();
        if ($this->form->checkInput()) {
            $this->dic->object()->commonSettings()->legacyForm($this->form, $this->object)->saveTileImage();
            $this->object->setTitle(ilStr::shortenTextExtended($this->form->getInput("title"),64,true));
            $this->object->setDescription($this->form->getInput("desc"));
            $this->object->setOnline($this->form->getInput("online"));
            $this->serviceGUI->updateProperties();
            $this->object->update();
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('success', $this->dic->language()->txt("msg_obj_modified"), true);
            $this->dic->ctrl()->redirect($this, "editProperties");
        } else {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->dic->language()->txt("form_input_not_valid"), true);
            $this->dic->ctrl()->redirect($this, 'editProperties');
        }
        $this->form->setValuesByPost();
        $this->dic->ui()->mainTemplate()->setContent($this->form->getHtml());
    }

    /**
     * tree actions
     */

    public function setRootFolder(string $root_path)
    {
        assert($this->object instanceof ilObjCloudStorage);
        if ($this->object->currentUserIsOwner()) {
            $this->object->setRootFolder(ilCloudStorageUtil::normalizePath($root_path));
            $this->object->update();
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('success', $this->lng->txt('msg_obj_modified'), true);
        } else {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('cld_only_owner_has_permission_to_change_root_path'), true);
        }
        $this->dic->ctrl()->redirect($this,'editProperties');
    }

    // Sn: from class.ilCloudPluginInitGUI.php
    public function showContent()
    {
        assert($this->object instanceof ilObjCloudStorage);

        // bug dirty hack: if comming from wrong locator entry in objectactivationgui or conditionhandlergui
        // it would be better to avoid the locator entry
        if ($this->dic->http()->wrapper()->query()->has('go_back')) {
            $go_back = $this->dic->http()->wrapper()->query()->retrieve('go_back', $this->dic->refinery()->kindlyTo()->int());
            if ($go_back == 1) {
                $this->redirectToRefId($this->parent_id);
            }
        }

        $this->dic->tabs()->activateTab("content");
        try {
            //if($this->getPluginObject()->getAsyncDrawing())
            $this->dic->logger()->root()->debug("showContent");
            $this->dic->ui()->mainTemplate()->addJavaScript(ilObjCloudStorage::PLUGIN_PATH."/templates/js/ilCloudFileList.js");
            $this->dic->ui()->mainTemplate()->addJavaScript(ilObjCloudStorage::PLUGIN_PATH."/templates/js/jquery.address.js");
            $this->dic->ui()->mainTemplate()->addJavascript("./Services/UIComponent/AdvancedSelectionList/js/AdvancedSelectionList.js");
            $this->dic->ui()->mainTemplate()->addCss(ilObjCloudStorage::PLUGIN_PATH."/templates/css/cloud.css");

            // for FileUpload
            // needed scripts
            $this->dic->ui()->mainTemplate()->addJavaScript(ilObjCloudStorage::PLUGIN_PATH. "/classes/File/js/tmpl.js");
            $this->dic->ui()->mainTemplate()->addJavaScript(ilObjCloudStorage::PLUGIN_PATH. "/classes/File/js/jquery.ui.widget.js");
            $this->dic->ui()->mainTemplate()->addJavaScript(ilObjCloudStorage::PLUGIN_PATH. "/classes/File/js/jquery.iframe-transport.js");
            $this->dic->ui()->mainTemplate()->addJavaScript(ilObjCloudStorage::PLUGIN_PATH. "/classes/File/js/jquery.fileupload.js");
            $this->dic->ui()->mainTemplate()->addJavaScript(ilObjCloudStorage::PLUGIN_PATH. "/classes/File/js/jquery.ba-dotimeout.min.js");
            $this->dic->ui()->mainTemplate()->addJavaScript(ilObjCloudStorage::PLUGIN_PATH. "/classes/File/js/ilFileUpload.js", true, 3);
            // needed styles
            $this->dic->ui()->mainTemplate()->addCss(ilObjCloudStorage::PLUGIN_PATH. "/classes/File/templates/default/fileupload.css");

            include_once("./Services/YUI/classes/class.ilYuiUtil.php");
            ilYuiUtil::initConnection();
            $this->tpl_file_tree = new ilGlobalTemplate(ilObjCloudStorage::PLUGIN_PATH."/templates/tpl.cloud_file_tree.html", false, false);
            $this->tpl_file_tree->setVariable("PLEASE_WAIT", $this->txt("please_wait"));
            $this->tpl_file_tree->setVariable("PLEASE_WAIT_ALT", $this->txt("please_wait"));

            // Sn: conceptual redesign of root_id? root_id should always be integer 0 not a string 'root' (?)
            $file_tree = new ilCloudStorageFileTree(
                $this->object->getRootFolder(),
                0,
                $this->object->getId(),
                $this->object->getRefId(),
                $this->object->getConnId()
            );
            $file_tree->storeFileTreeToSession();
            $this->addToolbar($file_tree->getRootNode());
            $current_path = '/';
            $current_id = 0;
            $path = $this->getPath();
            
            if ($path !== '/') {
                $current_path = $path;
                $file_tree->updateFileTree($current_path);
                $node = $file_tree->getNodeFromPath($path);
                if (!$node) {
                    $current_path = $file_tree->getRootNode()->getPath();
                    $current_id = $file_tree->getRootNode()->getID();
                    $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("node_is_null"), false);
                } else {
                    $current_id = json_encode($node->getId());
                }
            } else {
                $current_path = $file_tree->getRootNode()->getPath();
                $current_id = $file_tree->getRootNode()->getID();
                $file_tree->updateFileTree($current_path);
            }
            // old <script type="text/javascript"> var coudFileList = new ilCloudFileList({ASYNC_GET_BLOCK}, {ASYNC_CREATE_FOLDER}, {ASYNC_UPLOAD_FILE}, {ASYNC_DELETE_ITEM}, {ROOT_ID}, {ROOT_PATH}, {CURRENT_ID}, {CURRENT_PATH}, {MAX_FILE_SIZE}); </script>
            $code = 'il.CloudFileList = new ilCloudFileList(' .
                json_encode($this->ctrl->getLinkTarget($this, "asyncGetBlock", null, true)) . ',' .
                json_encode($this->ctrl->getLinkTarget($this, "asyncCreateFolder", null, true)) . ',' .
                json_encode($this->ctrl->getLinkTarget($this, "asyncUploadFile", null, true)) . ',' .
                json_encode($this->ctrl->getLinkTarget($this, "asyncDeleteItem", null, true)) . ',' .
                json_encode($file_tree->getRootNode()->getId()) . ',' .
                json_encode($file_tree->getRootNode()->getPath()) . ',' .
                json_encode($current_id) . ',' .
                "\"" . ilCloudStorageUtil::encodeBase64Path($current_path) . "\"," .
                json_encode("Max Filesize....") . ");";
            $this->dic->ui()->mainTemplate()->addOnLoadCode($code);
            /* Sn: ToDo
            $txt_max_file_size = $lng->txt("file_notice") . " "
                . ilCloudConnector::getPluginClass($this->getGUIClass()->object->getServiceName(), $this->getGUIClass()->object->getId())
                    ->getMaxFileSize() . " MB";
            $this->tpl_file_tree->setVariable("MAX_FILE_SIZE", json_encode($txt_max_file_size));
            */
            //$this->tpl_file_tree->setVariable("MAX_FILE_SIZE", json_encode("Maximale Upload ..."));
            $this->dic->ui()->mainTemplate()->setContent($this->tpl_file_tree->get());
            $this->dic->ui()->mainTemplate()->setPermanentLink("xcls", $this->object->getRefId(), "_path__endPath");
            //$this->dic->ui()->mainTemplate()->setPermanentLink("xcls", $this->object->getRefId(), "_0");
        } catch (Exception $e) {
            if ($e->getCode() == ilCloudStorageException::AUTHENTICATION_FAILED) {
                $this->object->setAuthComplete(false);
                $this->object->doUpdate();
            }
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage(), true);
        }
        
    }

    public function asyncGetBlock(): string
    {
        $this->dic->logger()->root()->debug("asyncGetBlock");
        $response = new stdClass();
        $response->message = null;
        $response->locator = null;
        $response->content = null;
        $response->success = null;

        $this->dic->logger()->root()->debug("delete_files: " . $this->checkPermissionBool("delete_files"));
        $this->dic->logger()->root()->debug("delete_folders: " . $this->checkPermissionBool("delete_folders"));
        $this->dic->logger()->root()->debug("download: " . $this->checkPermissionBool("download"));
        $this->dic->logger()->root()->debug("files_visible: " . $this->checkPermissionBool("files_visible"));
        $this->dic->logger()->root()->debug("folders_visible: " . $this->checkPermissionBool("folders_visible"));
        try {
            $file_tree = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId());
            $path = $this->getPath();
            if ($path == '/') {
                $path = $file_tree->getRootNode()->getPath();
            }
            $file_tree->updateFileTree($path);
            $node = $file_tree->getNodeFromPath($path);
            if (!is_null($node)) {
                $response->content = $this->getFolderHtml(
                    $file_tree,
                    $node->getId(),
                    $this->checkPermissionBool("delete_files"),
                    $this->checkPermissionBool("delete_folders"),
                    $this->checkPermissionBool("download"),
                    $this->checkPermissionBool("files_visible"),
                    $this->checkPermissionBool("folders_visible")
                );
    
                $response->locator = $this->getLocatorHtml($file_tree->getNodeFromId($node->getId()));
                $response->success = true;    
            } else {
                $response->message = "node_is_null";
                $response->success = false;
            }
        } catch (Exception $e) {
            //$this->dic->logger()->root()->debug("asyncGetBlock: error: " . $e->getMessage());
            //$response->message = $e->getMessage();
            $response->success = false;
            $response->message = ilUtil::getSystemMessageHTML($e->getMessage(), "failure");
        }
        //$this->dic->logger()->root()->debug("asyncGetBlock: " . json_encode($response));
        header('Content-type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * @param $root_node
     */
    public function addToolbar(ilCloudStorageFileNode $root_node): void
    {
        $create_list_gui = new ilCloudStorageItemCreationListGUI();

        $list_gui_html = $create_list_gui->getGroupedListItemsHTML($this->checkPermissionBool("upload"), $this->checkPermissionBool("folders_create"));
        if ($list_gui_html) {
            //toolbar
            $toolbar_locator = new ilLocatorGUI();
            $toolbar_locator->addItem($this->object->getTitle(), self::getLinkToFolder($root_node));
            $this->dic->toolbar()->setId('xcld_toolbar');
            $this->dic->toolbar()->addText("<div class='xcld_locator'>" . $toolbar_locator->getHtml() . "</div>");
            $this->dic->toolbar()->addSeparator();

            $adv = new ilAdvancedSelectionListGUI();
            $adv->setListTitle($this->txt("cld_add_new_item"));

            $ilCloudStorageGroupedListGUI = $create_list_gui->getGroupedListItems($this->checkPermissionBool("upload"), $this->checkPermissionBool("folders_create"));

            if ($ilCloudStorageGroupedListGUI->hasItems()) {
                $adv->setGroupedList($ilCloudStorageGroupedListGUI);
            }

            $adv->setStyle(ilAdvancedSelectionListGUI::STYLE_EMPH);
            $this->dic->toolbar()->addText($adv->getHTML());
        }
    }

    /**
     * from ilCloudPluginFileTreeGUI
     */
    
    public function getFolderHtml(ilCloudStorageFileTree $file_tree, int $id, bool $delete_files = false, bool $delete_folder = false, bool $download = false, bool $files_visible = false, $folders_visible = false): string
    {
        $this->dic->logger()->root()->debug("getFolderHtml");
        $node = null;

        $node = $file_tree->getNodeFromId($id);
        if (!$node) {
            throw new ilCloudStorageException(ilCloudStorageException::ID_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION, $id);
        }
        $tree_tpl = new ilTemplate(ilObjCloudStorage::PLUGIN_PATH . "/templates/tpl.cloud_block.html", true, true);

        if ($files_visible || $folders_visible) {
            $tree_tpl->setVariable("NODE_ID", $node->getId());

            $block = new ilTemplate("tpl.container_list_block.html", true, true, "Services/Container/");
            
            if ($node->hasChildren()) {
                $this->dic->logger()->root()->debug("getFolderHtml 1");
                $block->setVariable("BLOCK_HEADER_CONTENT", $this->txt("content"));

                $children = $file_tree->getSortedListOfChildren($node);
                foreach ($children as $path) {
                    $child_node = $file_tree->getNodeFromPath($path);
                    if (($child_node->getIsDir() && $folders_visible) || (!$child_node->getIsDir() && $files_visible)) {
                        $block->setCurrentBlock("container_standard_row");
                        if ($child_node->getIsDir()) {
                            $block->setVariable("ROW_ID", "id=xcld_folder_" . $child_node->getId());
                        } else {
                            $block->setVariable("ROW_ID", "id=xcld_file_" . $child_node->getId());
                        }
                        $block->setVariable("BLOCK_ROW_CONTENT", $this->getItemHtml($child_node, $delete_files, $delete_folder, $download));
                        $block->parseCurrentBlock();
                    }
                }
            }
            $tree_tpl->setVariable("CONTENT", $block->get());
        } else {
            // Nothing is visible
            $tree_tpl->setVariable("CONTENT", $this->txt("file_folder_not_visible"));
        }

        return $tree_tpl->get();
    }

    public function getItemHtml(ilCloudStorageFileNode $node, bool $delete_files = false, bool $delete_folder = false, bool $download = false): string
    {
        $this->dic->logger()->root()->debug("getItemHtml");
        $item = new ilGlobalTemplate("tpl.container_list_item.html", true, true, "Services/Container");

        //$action_list_gui = ilCloudConnector::getActionListGUIClass($this->getService());
        //$item->setVariable("COMMAND_SELECTION_LIST", $action_list_gui->getSelectionListItemsHTML($delete_files, $delete_folder, $node));
        $item->setVariable("COMMAND_SELECTION_LIST", $this->getSelectionListItemsHTML($delete_files, $delete_folder, $node));

        $item->setVariable("DIV_CLASS", "ilContainerListItemOuter");
        $item->touchBlock("d_1");

        $modified = ilDatePresentation::formatDate(new ilDateTime($node->getModified(), IL_CAL_UNIX));

        $this->dic->logger()->root()->debug("modified: " . $modified);
        if ($node->getIconPath() != "") {
            $item->setVariable("SRC_ICON", $node->getIconPath());
        }

        // Folder with content
        if ($node->getIsDir()) {
            if ($node->getIconPath() == "") {
                //				$item->setVariable("SRC_ICON", "./Modules/Cloud/templates/images/icon_folder_b.png");
                $item->setVariable("SRC_ICON", self::getImagePath('icon_dcl_fold.svg'));
            }
            $item->setVariable("TXT_TITLE_LINKED", htmlspecialchars(basename($node->getPath())));
            $item->setVariable("HREF_TITLE_LINKED", $this->getLinkToFolder($node));
        } // File
        else {
            if ($node->getIconPath() == "") {
                //				$item->setVariable("SRC_ICON", "./Modules/Cloud/templates/images/icon_file_b.png");
                $item->setVariable("SRC_ICON", self::getImagePath('icon_dcl_file.svg'));
            }

            $item->setVariable(
                "TXT_DESC",
                $this->formatBytes($node->getSize()) . "&nbsp;&nbsp;&nbsp;" . $modified
            );
            if ($download) {
                $item->setVariable("TXT_TITLE_LINKED", htmlspecialchars(basename($node->getPath())));
                $item->setVariable("HREF_TITLE_LINKED", $this->dic->ctrl()->getLinkTarget($this, "getFile") . "&id=" . $node->getId());
            } else {
                $item->setVariable("TXT_TITLE", htmlspecialchars(basename($node->getPath())));
            }
        }
        return $item->get();
    }
    
    protected function formatBytes($bytes, $precision = 2): string
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, $precision) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, $precision) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, $precision) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    public function getLocatorHtml(ilCloudStorageFileNode $node) : string
    {
        static $ilLocator;
        //assert($ilLocator instanceof ilLocatorGUI);
        $file_tree = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId());
        if ($node == $file_tree->getRootNode()) {
            $ilLocator = new ilLocatorGUI();
            $ilLocator->addItem($this->object->getTitle(), $this::getLinkToFolder($node));
        } else {
            $this->getLocatorHtml($file_tree->getNodeFromId($node->getParentId()));
            $ilLocator->addItem(htmlspecialchars(basename($node->getPath())), $this->getLinkToFolder($node));
        }

        return "<DIV class='xcld_locator' id='xcld_locator_" . $node->getId() . "'>" . $ilLocator->getHTML() . "</DIV>";
    }

    public static function getLinkToFolder(ilCloudStorageFileNode $node): string
    {
        return "#/open_folder?id_parent=" . $node->getParentId() . "&current_id=" . $node->getId() . "&current_path=" . self::_urlencode($node->getPath());
    }

    public function asyncUploadFile(): void
    {
        $this->dic->tabs()->activateTab("content");
        iljQueryUtil::initjQuery();

        echo $this->getUploadFormHTML();

        $options = new stdClass();
        $options->dropZone = "#ilFileUploadDropZone_1";
        $options->fileInput = "#ilFileUploadInput_1";
        $options->submitButton = "uploadFiles";
        $options->cancelButton = "cancelUploadFiles";
        $options->dropArea = "#ilFileUploadDropArea_1";
        $options->fileList = "#ilFileUploadList_1";
        $options->fileSelectButton = "#ilFileUploadFileSelect_1";
        echo "<script language='javascript' type='text/javascript'>var fileUpload1 = new ilFileUpload(1, " . json_encode($options) . ");</script>";
        
        $_SESSION["cld_folder_id"] = $_POST["folder_id"];

        exit;
    }

    public function getUploadFormHTML(): string
    {
        // keep for later refactoring

        // $file_upload = new ilObjFileUploadDropzone(
        //      $this->object->getRefId()
        // );
        // try {
        //     $file_upload_html = $file_upload->getDropzoneHtml();
        //     $this->dic->logger()->root()->debug($file_upload_html);
        //     return $file_upload_html;
        // } catch(Exception $e) {
        //     $this->dic->logger()->root()->debug($e->getMessage());
        //     return $e->getMessage();
        // }

        $form = new ilPropertyFormGUI();
        $form->setId("upload");
        $form->setMultipart(true);
        $form->setHideLabels();

        $file = new ilDragDropFileInputGUI($this->txt("cld_upload_files"), "upload_files");
        $file->setRequired(true);
        $form->addItem($file);

        $form->addCommandButton("uploadFiles", $this->lng->txt("upload"));
        $form->addCommandButton("cancelUploadFiles", $this->lng->txt("cancel"));

        $form->setTableWidth("100%");
        $form->setTitle($this->lng->txt("upload_files_title"));
        //        $this->form->setTitleIcon(ilUtil::getImagePath('icon_file.gif'), $lng->txt('obj_file'));
        $form->setTitleIcon(self::getImagePath('icon_dcl_file.svg'), $this->lng->txt('obj_file'));

        $form->setTitle($this->lng->txt("upload_files"));
        $form->setFormAction($this->dic->ctrl()->getFormAction($this, "uploadFiles"));
        $form->setTarget("cld_blank_target");
        return $form->getHTML();
    }


    public function cancelUploadFiles()
    {
        echo "<script language='javascript' type='text/javascript'>window.parent.il.CloudFileList.afterUpload('cancel');</script>";
        exit;
    }


    /**
     * Update properties
     */

    public function uploadFiles()
    {
        $this->dic->logger()->root()->debug("uploadFiles");
        $response = new stdClass();
        $response->error = null;
        $response->debug = null;

        // Sn: ToDo
        $ret = $this->checkFileInput();
        $this->dic->logger()->root()->debug("uploadFiles ret" . $ret);
        if ($ret == "") {
            try {
                // Sn: ToDo  gucken wie man den kram mit dem wrapper kindlyTo array transformiert :-/
                // das ginge auch falls man $_FILES nicht nutzen kann:
                // $uploadedFiles = $this->dic->http()->request()->getUploadedFiles();
                // $files = $this->dic->http()->request()->withUploadedFiles($uploadedFiles)->getParsedBody();
                // ich spare mir den abstrakteren formular checkInput roundtrip
                // ich müsste sonst $_POST variablen setzen, das darf man nicht mehr
                // deswegen greif ich hier direkt auf $_FILES zu
                // mache manuellen check
                $fileresult = $this->handleFileUpload($_FILES['upload_files']);
                //$this->dic->logger()->root()->debug("uploadFiles fileresult: " . var_export($fileresult,true));
                if ($fileresult) {
                    $response = (object) array_merge((array) $response, (array) $fileresult);
                    //$this->dic->logger()->root()->debug("uploadFiles response: " . var_export($response,true));
                }
            } catch (ilException $e) {
                $this->dic->logger()->root()->debug("uploadFiles error 1: " . $e->getMessage());
                $response->error = $e->getMessage();
            }
        } else {
            // alert in GUI possible?
            $error = new ilCloudStorageException(ilCloudStorageException::UPLOAD_FAILED);
            $this->dic->logger()->root()->debug("uploadFiles error 2: " . $error->getMessage());
            $response->error = $error->getMessage();
        }

        // send response object (don't use 'application/json' as IE wants to download it!)
        header('Vary: Accept');
        header('Content-type: text/plain');
        echo json_encode($response);
        exit;
    }


    public function handleFileUpload($file_upload)
    {
        $this->dic->logger()->root()->debug("handleFileUpload");

        $response = new stdClass();
        $response->fileName = $_POST["title"];
        $response->fileSize = intval($file_upload["size"]);
        $response->fileType = $file_upload["type"];
        $response->fileUnzipped = $_POST["extract"];
        $response->error = null;
    
        $file_tree = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId());

        if ($_POST["extract"]) {
            $this->dic->logger()->root()->debug("extract");
            $newdir = ilFileUtils::ilTempnam();

            $this->dic->logger()->root()->debug("handleFileUpload tempdir " . $newdir);
            ilFileUtils::makeDir($newdir);
            try {
                ilFileUtils::processZipFile($newdir, $file_upload["tmp_name"], $_POST["keep_structure"]);
            } catch (Exception $e) {
                $this->dic->logger()->root()->debug("handleFileUpload error " . $e->getMessage());
                $response->error = $e->getMessage();
                ilFileUtils::delDir($newdir);
                exit;
            }

            try {
                $this->uploadDirectory($newdir, $_SESSION["cld_folder_id"], $file_tree, $_POST["keep_structure"]);
            } catch (Exception $e) {
                $response->error = $e->getMessage();
                ilFileUtils::delDir($newdir);
                exit;
            }

            ilFileUtils::delDir($newdir);

            return $response;
        } else {
            $file_tree->uploadFileToService($_SESSION["cld_folder_id"], $file_upload["tmp_name"], $_POST["title"]);
            return $response;
        }
    }

    protected function uploadDirectory($dir, $parent_id, $file_tree, $keep_structure = true): void
    {
        $this->dic->logger()->root()->debug("uploadDirectory " . $dir . "," . $parent_id);
        $dirlist = opendir($dir);
        while (false !== ($file = readdir($dirlist))) {
            if (!is_file($dir . "/" . $file) && !is_dir($dir . "/" . $file)) {
                throw new ilCloudStorageException($this->dic->language()->txt("filenames_not_supported"), ilFileUtilsException::$BROKEN_FILE);
            }
            if ($file != '.' && $file != '..') {
                $newpath = $dir . '/' . $file;
                if (is_dir($newpath)) {
                    if ($keep_structure) {
                        $newnode = $file_tree->addFolderToService($parent_id, basename($newpath));
                        $this->uploadDirectory($newpath, $newnode->getId(), $file_tree);
                    } else {
                        $this->uploadDirectory($newpath, $parent_id, $file_tree, false);
                    }
                } else {
                    $file_tree->uploadFileToService($parent_id, $newpath, basename($newpath));
                }
            }
        }
        closedir($dirlist);
    }

    protected static function _urlencode(string $str): string
    {
        return str_replace('%2F', '/', rawurlencode($str));
    }

    /**
     * from ilCloudPluginActionListGUI
     */

    public function getSelectionListItemsHTML(bool $delete_item, bool $delete_folder, ilCloudStorageFileNode $node): string
    {
        $this->dic->logger()->root()->debug("getSelectionListItemsHTML");

        if (($delete_item && !$node->getIsDir()) || ($delete_folder && $node->getIsDir()) || $this->serviceGUI->checkHasAction($node)) {
            //include_once("./Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php");
            $selection_list = new ilAdvancedSelectionListGUI();
            $selection_list->setId("id_action_list_" . $node->getId());
            $selection_list->setListTitle($this->lng->txt("actions"));
            $selection_list->setItemLinkClass("xsmall");

            $this->serviceGUI->addItemsBefore($node, $selection_list);
            if (($delete_item && !$node->getIsDir()) || ($delete_folder && $node->getIsDir())) {
                $selection_list->addItem($this->lng->txt("delete"), "delete_item", "javascript:il.CloudFileList.deleteItem('" . $node->getId()
                . "');");
            }
            $this->serviceGUI->addItemsAfter($node, $selection_list);
            return $selection_list->getHtml();
        } else {
            return "";
        }
    }

    public function asyncDeleteItem()
    {
        $response = new stdClass();
        $response->success = null;
        $response->message = null;
        $response->content = null;
        $file_tree = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId());
        try {
            $node = $file_tree->getNodeFromId($_POST["id"]);
            if (!$node) {
                throw new ilCloudStorageException(ilCloudStorageException::ID_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION);
            } else {
                $is_dir = $node->getIsDir();
            }
            $path = $node->getPath();
            $id = $node->getId();
            if (!$is_dir) {
                $path = rtrim($path, "/");
            }
            $response->content = "<div id = 'cld_delete_item' >";
            if ($is_dir) {
                $response->content .= ilUtil::getSystemMessageHTML($this->txt("cld_confirm_delete_folder"), "question");
            } else {
                $response->content .= ilUtil::getSystemMessageHTML($this->txt("cld_confirm_delete_file"), "question");
            }
            $response->content .= $this->getDeleteItemConfirmationHTML($is_dir, $path, $id);
            $response->content .= "</div >";
            $response->success = true;
        } catch (Exception $e) {
            $response->message = ilUtil::getSystemMessageHTML($e->getMessage(), "failure");
        }
        header('Content-type: application/json');
        echo json_encode($response);
        exit;
    }


    public function getDeleteItemConfirmationHTML(bool $is_dir, string $path, int $id): string
    {
        
        $gui = new ilConfirmationTableGUI(true);
        $gui->setFormName("frm_cld_delete_item");

        // this does not exist anymore, but required. Now hardcoded in ilCloudFileList.js
        // $gui->getTemplateObject()->setVariable("ACTIONTARGET", "cld_blank_target");

        $gui->addCommandButton('deleteItem', $this->dic->language()->txt('confirm'));
        $gui->addCommandButton('cancelDeleteItem', $this->dic->language()->txt('cancel'));
        $gui->setFormAction($this->dic->ctrl()->getFormAction($this));
        
        if ($is_dir) {
            $item[] = array(
                "var" => 'id',
                "id" => $id,
                "text" => htmlspecialchars(basename($path)),
                "img" => self::getImagePath('icon_dcl_fold.svg'),
                "alt" => "alt",
            );
        } else {
            $item[] = array(
                "var" => 'id',
                "id" => $id,
                "text" => htmlspecialchars(basename($path)),
                "img" => self::getImagePath('icon_dcl_file.svg'),
                "alt" => "alt",
            );
        }
        $gui->setData($item);
        //$this->dic->logger()->root()->debug($gui->getHTML());
        return $gui->getHTML();
    }

    public function deleteItem()
    {
        $this->dic->logger()->root()->debug("deleteItem");
        $response = new stdClass();
        $response->success = null;
        $response->message = null;
        try {
            $file_tree = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId());
            $node = $file_tree->getNodeFromId($_POST["id"]);
            $file_tree->deleteFromService($node->getId());
            $response->message = ilUtil::getSystemMessageHTML($this->txt("cld_file_deleted"), "success");
            $response->success = true;
        } catch (Exception $e) {
            $this->dic->logger()->root()->debug("deleteItem error " . $e->getMessage());
            $response->message = ilUtil::getSystemMessageHTML($e->getMessage(), "failure");
        }
        echo "<script language='javascript' type='text/javascript'>window.parent.il.CloudFileList.afterDeleteItem(" . json_encode($response) . ");</script>";
        exit;
    }

    public function cancelDeleteItem()
    {
        $response = new stdClass();
        $response->status = "cancel";
        echo "<script language='javascript' type='text/javascript'>window.parent.il.CloudFileList.afterDeleteItem(" . json_encode($response) . ");</script>";
        exit;
    }
    //from ilCloudPluginCreateFolderGUI adopted
    
    public function asyncCreateFolder(): void
    {
        $response = new stdClass();
        $response->success = null;
        $response->error = null;
        $response->message = null;

        try {
            $response->content = $this->getCreateFolderHTML();
            $response->success = true;
        } catch (Exception $e) {
            //Sn: ToDo
            $response->message = ilUtil::getSystemMessageHTML($e->getMessage(), "failure");
            //$response->message = json_encode($e->getMessage());
        }
        header('Content-type: application/json');
        echo json_encode($response);
        exit;
    }

    public function getCreateFolderHTML(): string
    {
        $form = new ilPropertyFormGUI();
        $form->setId("cld_create_folder");

        $name = new ilTextInputGUI($this->txt("cld_folder_name"), "folder_name");
        $name->setRequired(true);
        $form->addItem($name);

        // folder id
        $id = new ilHiddenInputGUI("parent_folder_id");
        $id->setValue($_POST["id"]);
        $form->addItem($id);

        $form->addCommandButton("createFolder", $this->txt("cld_create_folder"));
        $form->addCommandButton("cancelCreateFolder", $this->lng->txt("cancel"));

        $form->setTitle($this->txt("cld_create_folder"));
        $form->setFormAction($this->dic->ctrl()->getFormAction($this));
        $form->setTarget("cld_blank_target");
        return $form->getHTML();
    }

    public function createFolder()
    {
        $response = new stdClass();
        $response->success = null;
        $response->message = null;
        $response->folder_id = null;
        try {
            $response->status = "done";
            $file_tree = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId());
            //Sn: ToDo remove POST and exit
            $new_node = $file_tree->addFolderToService($_POST["parent_folder_id"], $_POST["folder_name"]);
            $response->folder_id = $new_node->getId();
            $response->folder_path = $new_node->getPath();
            $response->success = true;
            $response->message = ilUtil::getSystemMessageHTML($this->txt("cld_folder_created"), "success");
            // Sn: ToDo
            // $response->message = json_encode($this->lng->txt("cld_folder_created"));
        } catch (Exception $e) {
            $response->message = ilUtil::getSystemMessageHTML($e->getMessage(), "failure");
            //$response->message = json_encode($e->getMessage());
        }
        echo "<script language='javascript' type='text/javascript'>window.parent.il.CloudFileList.afterCreateFolder(" . json_encode($response) . ");</script>";
        exit;
    }

    public function cancelCreateFolder()
    {
        $response = new stdClass();
        $response->status = "cancel";

        echo "<script language='javascript' type='text/javascript'>window.parent.il.CloudFileList.afterCreateFolder(" . json_encode($response) . ");</script>";
        exit;
    }

    public function getFile(): void
    {
        $this->dic->logger()->root()->debug("getFile");
        try {
            $file_tree = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId());
            $id = $this->dic->http()->wrapper()->query()->retrieve('id', $this->dic->refinery()->kindlyTo()->string());
            $file_tree->downloadFromService($id);
        } catch (Exception $e) {
            $this->dic->tabs()->activateTab("content");
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage(), true);
        }
    }

    /**
     * utils
     */

    public function checkFileInput(): string {
        if (!is_array($_FILES['upload_files'])) {
            $this->lng->txt("form_msg_file_size_exceeds");
            return false;
        }
        
        // empty file, could be a folder
        if ($_FILES['upload_files'] < 1) {
            //$this->setAlert($lng->txt("error_upload_was_zero_bytes"));
            return $this->lng->txt("error_upload_was_zero_bytes");
        }
        return "";
    }
    
    // new function for retrieving images first lookup in skin image folder then plugin image folder
    // never lookup in core default skin
    public static function getImagePath(string $img): string {
        // ToDo: Caching in Session?
        global $DIC;
        $styleDefinition = $DIC["styleDefinition"];
        $currentStyle = $DIC["styleDefinition"]::getCurrentStyle();
        
        if ($currentStyle != "delos") {
            // if current skin is not delos first lookup for icons in custom skin
            $currentSkinPath = rtrim($styleDefinition->getSystemStylesConf()->getCustomizingSkinPath(), "/");
            $styleImagePath = $currentSkinPath . "/" . $currentStyle . "/" . $styleDefinition->getImageDirectory($currentStyle) . "/" . $img;
            if (!file_exists($styleImagePath)) {
                $styleImagePath = ilObjCloudStorage::PLUGIN_PATH."/templates/images/".$img;    
            }
        } else {
            // always get plugin default images if current skin is delos
            $styleImagePath = ilObjCloudStorage::PLUGIN_PATH."/templates/images/".$img;
        }
        return $styleImagePath;
    }

    public static function _redirectToRefId(int $ref_id, string $cmd = ""): void
    {
        global $DIC;
        $obj_type = ilObject::_lookupType($ref_id, true);
        $class_name = $DIC['objDefinition']->getClassName($obj_type);
        $class = strtolower("ilObj" . $class_name . "GUI");
        $DIC->ctrl()->setParameterByClass("ilrepositorygui", "ref_id", $ref_id);
        $DIC->ctrl()->redirectByClass(array("ilrepositorygui", $class), $cmd);
    }

    // keep for furhter usage
    // public static function exit(): void {
    //     global $DIC;
    //     $DIC->http()->saveResponse($DIC->http()->response());
    //     $DIC->http()->sendResponse();
    //     $DIC->http()->close();
    // }
    
    /**
     * @return int|string|bool
     */
    public static function getParam(string $param, string $type) {
        global $DIC;
        if ($DIC->http()->wrapper()->query()->has($param)) {
            switch ($type) {
                case self::INT:
                case self::INTEGER:
                    return  $DIC->http()->wrapper()->query()->retrieve($param, $DIC->refinery()->kindlyTo()->int());
                    break;
                case self::STRING:
                    return  $DIC->http()->wrapper()->query()->retrieve($param, $DIC->refinery()->kindlyTo()->string());
                    break;
                case self::BOOL:
                    return  $DIC->http()->wrapper()->query()->retrieve($param, $DIC->refinery()->kindlyTo()->bool());
                    break;
                default:
                    return false;
            }
        }
        return false;
    }

    public static function _goto(array $a_target): void
    {
        global $DIC;
        $main_tpl = $DIC->ui()->mainTemplate();

        $ilCtrl = $DIC->ctrl();
        $ilAccess = $DIC->access();
        $lng = $DIC->language();
        $class_name = $a_target[1];
        $content = explode("_", $a_target[0]);
        $path = '/';
        $ref_id = (int) $content[0];
        
        if (in_array("path", $content) && in_array("endPath", $content)) {
            // remove ref_id, "path" und "endPath"
            unset($content[0]);
            unset($content[1]);
            array_pop($content);
            // reconstruct and set path
            $path = implode('_', $content);
        }
        
        if ($ilAccess->checkAccess("read", "", $ref_id)) {
            $ilCtrl->setTargetScript('ilias.php');
            $ilCtrl->setParameterByClass($class_name, "ref_id", $ref_id);
            $ilCtrl->setParameterByClass($class_name, "path", $path);
            $ilCtrl->redirectByClass(["ilobjplugindispatchgui", $class_name]);
        } elseif ($ilAccess->checkAccess("visible", "", $ref_id)) {
            $ilCtrl->setTargetScript('ilias.php');
            $ilCtrl->setParameterByClass($class_name, "ref_id", $ref_id);
            $ilCtrl->redirectByClass(["ilobjplugindispatchgui", $class_name], "infoScreen");
        } elseif ($ilAccess->checkAccess("read", "", ROOT_FOLDER_ID)) {
            $main_tpl->setOnScreenMessage('failure', sprintf(
                $lng->txt("msg_no_perm_read_item"),
                ilObject::_lookupTitle(ilObject::_lookupObjId($ref_id))
            ));
            ilObjectGUI::_gotoRepositoryRoot();
        }
    }

    public function getPath(): string {
        $path = '';
        if ($this->dic->http()->wrapper()->query()->has("path")) {
            try {
                $path = $this->dic->http()->wrapper()->query()->retrieve("path", $this->dic->refinery()->kindlyTo()->string());
                if (preg_match('/^BASE64/', $path)) { // new perm_link
                    $path = ilCloudStorageUtil::decodeBase64Path(preg_replace('/^BASE64/', "", $path));
                } else { // old perm_link
                    $path = urldecode($path);
                }
            } catch (Exception $e) {
                $this->dic->logger()->root()->log("ERROR: " . $e->getMessage());
                $path = '';
            }
        } else {
            if ($this->dic->http()->wrapper()->post()->has("path") && $path == '') {
                $path = $this->dic->http()->wrapper()->post()->retrieve("path", $this->dic->refinery()->kindlyTo()->string());
            }
        }
        return ilCloudStorageUtil::normalizePath($path);
    }
}
