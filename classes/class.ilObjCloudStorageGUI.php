<?php

//declare(strict_types=1);

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
 * @ilCtrl_Calls ilObjCloudStorageGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI, ilRepositorySearchGUI, ilCloudStorageOwnCloudGUI, ilCloudStorageWebDavGUI, ilObjFileUploadHandlerGUI
 *
 */
class ilObjCloudStorageGUI extends ilObjectPluginGUI
{
    //public bool $new_model = false; // only temp

    public const INTEGER = "integer";

    public const INT = "int";

    public const BOOL = "bool";

    public const STRING = "string";

    public const CFORM_FOLDER_EXISTING = 98;

    public const CFORM_FOLDER_NEW = 99;

    // it might be a better solution to force setCreationMode(true)?
    private const COMMAND_MODE_PRE_CREATION = 0;
    private const COMMAND_MODE_OBJECT = 1;

    // from old serviceGUI
    const CMD_OPEN_IN_PLATFORM = 'openInPlatform';
    
    const ITEM_ID = 'item_id';
    
    const ITEM_PATH = 'item_path';

    private ?bool $open_in_platform_active = null;

    private int $commandMode = self::COMMAND_MODE_OBJECT;

    // Sn:  read from pluginIni should only read and parse once, needs concept (see ilCloudStorageConfigGUI)
    private bool $debug = true;

    public ?ilObject $object = null;
    public ?ilCloudStorageConfig $config = null;

    public ?ilCloudStorageGenericService $service = null;

    //public ?ilCloudStorageServiceGUIInterface $serviceGUI = null;

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
        $this->lng->loadLanguageModule('file');

        $this->platform = $this->object instanceof ilObjCloudStorage ? ilCloudStorageConfig::getInstance($this->object->getConnId())->getServiceId() : $this->platform; #

        $this->dic->logger()->root()->debug("service: " . $this->platform);

        $this->config = $this->object instanceof ilObjCloudStorage ? ilCloudStorageConfig::getInstance($this->object->getConnId()) : $this->config;

        // bug: object item itself should not appear in locator ilobjectactivationgui and ilconditionhandlergui: crashes on click
        // navigation is provided by a back button
        // from this point there is no control about locator items(?) therefore i set an extra parameter for contentShow: dont show anything redirect to parent
        
        if (strtolower($this->dic->ctrl()->getCmdClass()) == "ilobjectactivationgui" || strtolower($this->dic->ctrl()->getCmdClass()) == "ilconditionhandlergui") {
            $this->dic->ctrl()->setParameter($this ,"go_back", "1");
        }

        if($this->object instanceof ilObjCloudStorage) {
            $this->commandMode = self::COMMAND_MODE_OBJECT;
            assert($this->object instanceof ilObjCloudStorage);
            $this->dic->logger()->root()->debug("platform id: " . $this->platform);
            $this->dic->logger()->root()->debug("service class: " . ilCloudStorageConfig::AVAILABLE_XCLS_SERVICES[$this->platform]);
            $serviceClass = ilCloudStorageConfig::AVAILABLE_XCLS_SERVICES[$this->platform];
            $this->service = new $serviceClass($this->object->getRefId(), $this->object->getConnId());
        } else {
            $this->commandMode = self::COMMAND_MODE_PRE_CREATION;
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
        $this->dic->logger()->root()->debug("cmd " . $cmd);

        if ($this->commandMode == self::COMMAND_MODE_OBJECT) {
            assert($this->object instanceof ilObjCloudStorage);
            assert($this->service instanceof ilCloudStorageGenericService);
            $this->dic->ui()->mainTemplate()->setAlertProperties($this->getAlertProperties());
            switch ($this->config->getAuthMethod()) {
                case $this->config::AUTH_METHOD_OAUTH2:
                    if (!$this->dic->http()->wrapper()->query()->has('auth_mode')) {
                        if (!$this->object->getAuthComplete()) {
                            if ($this->checkPermissionBool("write") && $this->object->currentUserIsOwner()) {
                                $this->serviceAuth();
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
                break;
                case $this->config::AUTH_METHOD_BASIC:
                    if (!$this->dic->http()->wrapper()->query()->has('auth_mode')) {
                        if (!$this->object->getAuthComplete()) {
                            if ($this->checkPermissionBool("write") && $this->object->currentUserIsOwner()) {
                                $this->dic->logger()->root()->debug("needs serviceAuth");
                                $this->serviceAuth();
                            } else {
                                $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("only_owner"), true);
                                $this->redirectToRefId($this->parent_id);
                            }
                        } else {
                            try {
                                $this->service->checkConnection();
                                $root_folder = $this->object->getRootFolder();
                                $this->dic->logger()->root()->debug("root_folder: " . $root_folder);
                            } catch(ilCloudStorageException $e) {
                                $this->handleConnectionException($e, false);
                            }
                        }
                    }
                    break;
                default:
                    $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', 'unsupported auth_method:' . $this->config->getAuthMethod(), true);
            }
        }

        switch ($cmd) {
            case "cancelCreation":
                $this->checkPermission("write");
                $this->cancelCreation();
                break;
            case "processAuth":    
            case "processConnectionSelection":
                $this->$cmd();
                break;
            case "editProperties":
                $this->checkPermission("write");
                $this->editProperties();
                break;
            case "updateProperties":
                $this->checkPermission("write");
                $this->$cmd();
                break;
            case "afterServiceAuth":
                $this->checkPermission("write");
                $this->afterServiceAuth();
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
                $this->openInPlatform();
                break;
            default:
                $this->checkPermission("read");
                $this->showContent();
                break;
        }
    }

    private function handleConnectionException(ilCloudStorageException $e) {

        assert($this->object instanceof ilObjCloudStorage);
        assert($this->service instanceof ilCloudStorageGenericService);
        switch ($e->getCode()) {
            case ilCloudStorageException::NO_CONNECTION:
                // never ignore no_connection exception
                $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage(), true);
                $this->redirectToRefId($this->parent_id);
                break;
            case ilCloudStorageException::NOT_AUTHORIZED:
                // ignore exception on auth_mode
                switch ($this->config->getAuthMethod()) {
                    case $this->config::AUTH_METHOD_OAUTH2:
                        if (!$this->dic->http()->wrapper()->query()->has('auth_mode')) {
                            // there is no token for user
                            if (!ilCloudStorageOAuth2::checkAndRefreshAuthentication($this->object->getOwnerId(), $this->config)) {
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
                                            ilCloudStorageOAuth2::deleteUserToken($this->object->getConnId());
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
                    case $this->config::AUTH_METHOD_BASIC:
                        $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage(), true);
                        $this->redirectToRefId($this->parent_id);
                        break;
                    default:
                        $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage(), true);
                        $this->redirectToRefId($this->parent_id);
                        break;
                }   
                break;
        }
    }

    // required? Maybe processAuth could be sufficient
    protected function serviceAuth(int $conn_id = -1)
    {
        try {
            $cmd = $this->dic->ctrl()->getCmd();
            $this->dic->ctrl()->setParameter($this, 'auth_mode', 'true');
            $this->dic->ctrl()->setParameter($this, 'conn_id', (string) $conn_id);
            $this->dic->ctrl()->setParameter($this, 'last_cmd', $cmd);
            $this->dic->logger()->root()->debug("ilObjCloudStorageGUI serviceAuth: " . $cmd);
            $url = $this->dic->ctrl()->getLinkTarget($this, "processAuth");
            $this->dic->logger()->root()->debug("redirectToUrl " . $url);
            $this->dic->ctrl()->redirectToURL($url);
        } catch (Exception $e) {
            $this->dic->logger()->root()->error("ilObjCloudStorageGUI error serviceAuth " . $e->getMessage());
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("cld_auth_failed"), true);
            $this->redirectToRefId($this->parent_id);
        }
    }

    protected function afterServiceAuth()
    {
        try {
            $this->dic->logger()->root()->debug("ilObjCloudStorageGUI afterServiceAuth");
            $conn_id = $this->getConnId();
            $ref_id = $this->getRefId();
            $last_cmd = ilCloudStorageUtil::getStringParam('last_cmd');
            $user_id = $this->dic->user()->getId();
            if ($conn_id != -1) {
                $config = ilCloudStorageConfig::getInstance($conn_id);
            } else {
                $config = $this->config;
            }

            switch ($config->getAuthMethod()) {
                case $config::AUTH_METHOD_OAUTH2:
                    $access_token = unserialize(ilSession::get(ilCloudStorageOAuth2::SESSION_AUTH_BEARER));
                    assert($access_token instanceof \League\OAuth2\Client\Token\AccessToken);
                    $token = ilCloudStorageOAuth2::getUserToken($config->getConnId(), $user_id); // ToDo security user id compare
                    $token->storeUserToken($access_token, $config->getConnId());
                    ilSession::clear(ilCloudStorageOAuth2::SESSION_CALLBACK_URL);
                    ilSession::clear(ilCloudStorageOAuth2::SESSION_CONN_ID);
                    ilSession::clear(ilCloudStorageOAuth2::SESSION_AUTH_BEARER);
                    
                    //$this->getToken()->storeUserToken($token,$this->object->getConnId());
                    break;
                case $config::AUTH_METHOD_BASIC:
                    // Nothing to do
            }
            
            if ($conn_id != -1) { //from creation process
                $this->redirectToCreate($ref_id, $conn_id);
            } else { //from editProperties or showContent
                $this->dic->ctrl()->setParameter($this, 'auth_mode', '');
                
                if (!$this->service->folderExists($config->getBaseDirectory())) {
                    $this->dic->logger()->root()->debug("root_folder not exists...create");
                    $this->service->createFolder($config->getBaseDirectory());
                }
                $this->dic->ctrl()->setParameter($this, 'cmd', $last_cmd);
                assert($this->object instanceof ilObjCloudStorage);
                foreach ($this->object->getAllWithSameOwnerAndConnection() as $obj_id) {
                    $ref_ids = ilObject::_getAllReferences($obj_id);
                    foreach ($ref_ids as $ref_id) {
                        $obj = new ilObjCloudStorage($ref_id);
                        $obj->setAuthComplete(true);
                        $obj->update();
                    }
                }
                $this->dic->ctrl()->redirect($this);
            }
            //        break;
            // }
        } catch (Exception $e) {
            $this->dic->logger()->root()->debug("ilObjCloudStorageGUI error afterServiceAuth " . $e->getMessage());
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("cld_auth_failed"), true);
            $this->redirectToRefId($this->parent_id);
        }
    }

    public function processConnectionSelection(): void
    {
        $request = $this->dic->http()->request();
        $refinery = $this->dic->refinery();
        $factory = $this->dic->ui()->factory();
        $renderer = $this->dic->ui()->renderer();
        
        $ref_id = $this->getRefId();
        $user_id = $this->dic->user()->getId();

        $not_empty = $refinery->custom()->constraint(function ($v) {
            return ($v == '') ? false : true;
        }, $this->txt("must_not_empty"));

        $availableConns = ilCloudStorageConfig::_getAvailableCloudStorageConn(true);
        $radio = $factory->input()->field()->radio($this->txt('conn_id'), "");
            
        foreach ($availableConns as $key => $value) {
            $config = ilCloudStorageConfig::getInstance($key);
            $conn_title = $config->getTitle();
            $radio = $radio->withOption((string) $key, $conn_title);
        }

        $radio = $radio->withRequired(true, $not_empty);

        $form = $factory->input()->container()->form()->standard('#', ['conn_id' => $radio]);
        $form = $form->withSubmitLabel($this->txt("select_type"));
        
        //$hidden = $factory->input()->field()->hidden()->withValue("just_for_layout");

        if ($request->getMethod() == "POST") {
            $form = $form->withRequest($request);
            $result = $form->getData();
            if ($result) {
                $this->redirectToCreate($ref_id, (int) $result['conn_id']);
            } else {
                $this->dic->logger()->root()->debug("no result");
            }
            $panel = $factory->panel()->standard(
                $this->txt("select_type"),
                $form
            );
            //$this->dic->ui()->mainTemplate()->setLeftContent($renderer->render([$hidden]));
            //$this->dic->ui()->mainTemplate()->setRightContent($renderer->render([$hidden]));
            $this->dic->ui()->mainTemplate()->setContent($renderer->render([$panel]));
        } else {
            $panel = $factory->panel()->standard(
                $this->txt("select_type"),
                $form
            );
            $this->dic->tabs()->clearTargets();
            $this->dic->ctrl()->setParameterByClass('ilrepositorygui', 'ref_id', $this->parent_id);
            $this->dic->tabs()->setBackTarget($this->txt('back'), $this->dic->ctrl()->getLinkTargetByClass('ilrepositorygui'));
            //$this->dic->ui()->mainTemplate()->setLeftContent($renderer->render([$hidden]));
            //$this->dic->ui()->mainTemplate()->setRightContent($renderer->render([$hidden]));
            $this->dic->ui()->mainTemplate()->setContent($renderer->render([$panel]));
        }
    }

    public function processAuth(): void
    {
        $request = $this->dic->http()->request();
        $factory = $this->dic->ui()->factory();
        $renderer = $this->dic->ui()->renderer();
        
        $conn_id = $this->getConnId();
        $ref_id = $this->getRefId();

        if ($conn_id == -1) {
            $config = $this->config;
        } else {
            $config = ilCloudStorageConfig::getInstance($conn_id);
        }
        $conn_id = $config->getConnId();

        $this->dic->ctrl()->saveParameter($this, 'cmd' );
        $this->dic->ctrl()->saveParameter($this, 'conn_id');
        $this->dic->ctrl()->saveParameter($this,'ref_id');
        $this->dic->ctrl()->saveParameter($this,'last_cmd');

        switch ($config->getAuthMethod()) {
            case ilCloudStorageConfig::AUTH_METHOD_OAUTH2:
                $ret = $this->processOAuth2($request, $config);
            break;
            case ilCloudStorageConfig::AUTH_METHOD_BASIC:
                $ret = $this->processBasicAuth($request, $config);
            break;
        }
        
        if (!is_array($ret)) 
        {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("cloud_provider_not_online"), true);
            $this->redirectToRefId($this->parent_id);
            return;
        }

        $panel = $factory->panel()->standard(
            $this->txt("authentication_required"),
            $ret
        );
        
        $this->dic->tabs()->clearTargets();
        $this->dic->ctrl()->setParameterByClass('ilrepositorygui', 'ref_id', $this->parent_id);
        $this->dic->tabs()->setBackTarget($this->txt('back'), $this->dic->ctrl()->getLinkTargetByClass('ilrepositorygui'));
        //$hidden = $factory->input()->field()->hidden()->withValue("just_for_layout");
        //$this->dic->ui()->mainTemplate()->setLeftContent($renderer->render([$hidden]));
        //$this->dic->ui()->mainTemplate()->setRightContent($renderer->render([$hidden]));
        $this->dic->ui()->mainTemplate()->setContent($renderer->render([$panel]));
    }

    public function processOAuth2(
        \Psr\Http\Message\ServerRequestInterface $request,
        ilCloudStorageConfig $config): ?array
    {   
        $user_id = $this->dic->user()->getId();

        $factory = $this->dic->ui()->factory();

        //$group = $factory->input()->field()->group([]);
        //$hidden = $factory->input()->field()->hidden()->withValue("just_for_layout");
        $this->dic->ctrl()->setParameterByClass('ilObjCloudStorageGUI', "login", "oauth2");
        $this->dic->ctrl()->setParameterByClass('ilObjCloudStorageGUI', "auth_mode", "true");

        $connItem = $factory->item()->standard($config->getTitle());
        $connItem = $connItem->withDescription($this->txt("oauth2_login_info"));

        $button = $factory->button()->primary($this->txt("auth_login"), $this->dic->ctrl()->getLinkTargetByClass("ilObjCloudStorageGUI"));
        
        $login = ilCloudStorageUtil::getStringParam("login");
        if ($login == 'oauth2') {
            ilCloudStorageOAuth2::Authenticate($user_id, $config);
            return null;
        } else {
            $this->dic->logger()->root()->debug("no login");
        }
        return [$connItem, $button];
    }

    public function processBasicAuth(
        \Psr\Http\Message\ServerRequestInterface $request,
        ilCloudStorageConfig $config): ?array
    {   
        // build form
        $factory = $this->dic->ui()->factory();
        $refinery = $this->dic->refinery();
        $user_id = $this->dic->user()->getId();
        $conn_id = $config->getConnId();

        $connItem = $factory->item()->standard($config->getTitle());
        $connItem = $connItem->withDescription($this->txt("bauth_login_info"));

        $not_empty = $refinery->custom()->constraint(function ($v) {
            return ($v == '') ? false : true;
        }, $this->txt("must_not_empty"));

        $username = $factory->input()->field()->text($this->txt("account_username"))
            ->withMaxLength(24)
            ->withRequired(true, $not_empty);

        // ToDo: field or section length?
        $password = $factory->input()->field()->password($this->txt("account_password"))
            ->withRevelation(true)
            ->withRequired(true, $not_empty);

        $section = $factory->input()->field()->section(['username' => $username, 'password' => $password], sprintf($this->txt('login_to_service'), $config->getTitle()));

        //$form = $factory->input()->container()->form()->standard("#", ['username' => $username, 'password' => $password]);
        $form = $factory->input()->container()->form()->standard("#", [$section]);
        $form = $form->withSubmitLabel($this->txt("auth_login"));
        if ($request->getMethod() == "POST") {
            $form = $form->withRequest($request);
            $result = $form->getData();
            if ($result) {
                $username = $result[0]['username'];
                $password = ilCloudStorageUtil::encrypt($result[0]['password']->toString());
                $account = ilCloudStorageBasicAuth::getUserAccount($conn_id, $user_id);
                $account->storeUserAccount($username, $password, $conn_id); // ToDo: static function for no persistent connection check
                try {
                    ilCloudStorageBasicAuth::checkConnection($conn_id, $user_id, $config);
                    $this->dic->ui()->mainTemplate()->setOnScreenMessage('success', $this->txt('successful_authenticated'), true);
                    $this->afterServiceAuth();
                    return null;
                } catch (ilCloudStorageException $e) {
                    ilCloudStorageBasicAuth::deleteUserAccount($conn_id, $user_id); // ToDo: static function for no persistent connection check
                    $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage(), false);
                }
            } else {
                $this->dic->logger()->root()->debug("no result");
                //$this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("must_not_empty"), false);   
                //$this->dic->ui()->mainTemplate()->setContent($renderer->render([$form]));
            }
        }
        return [$connItem, $form];
    }

    public function getRefId(): int {
        return ($this->dic->http()->wrapper()->query()->has("ref_id")) ? (int) $this->dic->http()->wrapper()->query()->retrieve('ref_id', $this->dic->refinery()->kindlyTo()->int()): 0;
    }

    public function getConnId(): int {
       return ($this->dic->http()->wrapper()->query()->has('conn_id')) ? (int) $this->dic->http()->wrapper()->query()->retrieve('conn_id', $this->dic->refinery()->kindlyTo()->string()): -1;
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
        if ($this->commandMode == self::COMMAND_MODE_PRE_CREATION) {
            return;
        }
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

    public function initCreationForms(string $new_type): array
    {
        $forms = [
            self::CFORM_FOLDER_EXISTING => $this->initCreateFormExisting($new_type),
            self::CFORM_FOLDER_NEW => $this->initCreateFormNew($new_type)
        ];
        return $forms;
    }
    
    // for both creation modes
    private function _initCreateForm(string $a_new_type): ?array
    {
        // check if conns are available
        $availableConns = ilCloudStorageConfig::_getAvailableCloudStorageConn(true);
        if (count($availableConns) == 0) {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("no_active_conn"), true);
            $this->redirectToRefId($this->parent_id);
        }
        if (count($availableConns) > 1) {
            $this->dic->logger()->root()->debug("multiple connections");
            $conn_id = ilCloudStorageUtil::getIntParam('conn_id');
            $this->dic->logger()->root()->debug("conn_id:" . $conn_id);
            if ($conn_id == -1) {
                $this->dic->ctrl()->setParameter($this, 'auth_mode', true);
                //$url = $this->dic->ctrl()->getLinkTarget($this, "processConnectionSelection") . "&auth_mode=true";
                $url = $this->dic->ctrl()->getLinkTarget($this, "processConnectionSelection");
                $this->dic->ctrl()->redirectToURL($url);
                return null;
            }
        } else {
            foreach ($availableConns as $key => $value) {
                $conn_id = $key;
            }
        }
        $config = ilCloudStorageConfig::getInstance($conn_id);
        
        // check if user is already authenticated with that connection
        $user_id = $this->dic->user()->getId();
        $hasAccount = ilObjCloudStorageAccess::hasAccount($config->getConnId(), $user_id);
        $this->dic->logger()->root()->debug("hasAccount: " . (string) $hasAccount);
        
        $connection = false;
        
        if ($hasAccount) {
            // check if connection to webdav server is valid
            switch ($config->getAuthMethod()) {
                case $config::AUTH_METHOD_OAUTH2:
                    $connection = ilCloudStorageOAuth2::checkAndRefreshAuthentication($user_id, $config);
                    break;
                case $config::AUTH_METHOD_BASIC:
                    try {
                        //$account = ilCloudStorageBasicAuth::getUserAccount($conn_id, $user_id);
                        ilCloudStorageBasicAuth::checkConnection($conn_id, $user_id, $config);
                        $connection = true;
                    } catch(ilCloudStorageException $e) {
                        $connection = false;
                    }
                    break;
            }
        }
        // needs authentication or re-authentication
        if (!$connection) {
            $this->serviceAuth($config->getConnId());
            return null;
        }
        // a custom PropertyForm might be a better solution
        $form = parent::initCreateForm($a_new_type);
        $form->setMode("subform");
        
        $serviceInfoText = $this->txt('conn_id') . ": " . $config->getTitle();
        $serviceInfo = new ilNonEditableValueGUI($serviceInfoText);
        $form->addItem($serviceInfo);

        // delete title and description from parent
        $form->removeItemByPostVar("title");
        $form->removeItemByPostVar("desc");

        $hiddenTitle = new ilHiddenInputGUI("title");
        $hiddenTitle->setValue($this->txt("cld_add"));
        $form->addItem($hiddenTitle);
        
        $hiddenConnId = new ilHiddenInputGUI("conn_id");
        $hiddenConnId->setValue((string) $config->getConnId());

        $hiddenOnline = new ilHiddenInputGUI("online");
        $hiddenOnline->setValue("1");
        $form->addItem($hiddenOnline);

        $form->addItem($hiddenConnId);

        return [$form, $config];
    }

    public function initCreateFormExisting($a_new_type): ?ilPropertyFormGUI
    {
        $this->dic->ctrl()->setParameterByClass(get_class($this), "action", "choose_root");
        $ret = $this->_initCreateForm($a_new_type);
        if (is_null($ret)) {
            return null;
        }
        list($form, $config) = $ret;
        $form->setTitle($this->txt("create_existing_folder"));
        $cmdBtns = $form->getCommandButtons();
        $form->clearCommandButtons();
        // rename save command
        $form->addCommandButton('save', $this->txt('obj_xcls_select'), '');
        // copy cancel btn
        $form->addCommandButton($cmdBtns[1]['cmd'], $cmdBtns[1]['text'], '');
        return $form;
    }

    public function initCreateFormNew($a_new_type): ?ilPropertyFormGUI
    {
        $this->dic->ctrl()->setParameterByClass(get_class($this), "action", "new_folder");
        $ret = $this->_initCreateForm($a_new_type);
        if (is_null($ret)) {
            return null;
        }
        list($form, $config) = $ret;
        assert($config instanceof ilCloudStorageConfig);
        $form->setTitle($this->txt("xcls_new"));

        $cmdBtns = $form->getCommandButtons();
        $form->clearCommandButtons();
        // rename save command
        $form->addCommandButton('save', $this->txt('xcls_new'), '');
        // copy cancel btn
        $form->addCommandButton($cmdBtns[1]['cmd'], $cmdBtns[1]['text'], '');
        $ti = new ilTextInputGUI($this->txt("obj_xcls"), "new_folder");
        $ti->setInfo(sprintf($this->txt('new_folder_add_info'), $config->getBaseDirectory()));
        $ti->setRequired(true);
        $ti->setMaxLength(64);
        $ti->setSize(64);
        $form->addItem($ti);
        return $form;
        /*
        $availableConns = ilCloudStorageConfig::_getAvailableCloudStorageConn(true);
        if (count($availableConns) == 0) {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("no_active_conn"), true);
            //ilObjectGUI::redirectToRefId($this->parent_id);
            $this->redirectToRefId($this->parent_id);
        }
        if (count($availableConns) == 1) {
            $this->dic->logger()->root()->debug("only one cloud connection is available");
            foreach ($availableConns as $key => $value) {
                $conn_id = $key;
                $config = ilCloudStorageConfig::getInstance($conn_id);
            }
            // check if user is already authenticated with that connection
            $user_id = $this->dic->user()->getId();
            $hasAccount = ilObjCloudStorageAccess::hasAccount($config->getConnId(), $user_id);
            $this->dic->logger()->root()->debug("hasAccount: " . (string) $hasAccount);
            
            $connection = false;
            
            if ($hasAccount) {
                // check if connection to webdav server is valid
                switch ($config->getAuthMethod()) {
                    case $config::AUTH_METHOD_OAUTH2:
                        // ToDo
                        break;
                    case $config::AUTH_METHOD_BASIC:
                        try {
                            //$account = ilCloudStorageBasicAuth::getUserAccount($conn_id, $user_id);
                            ilCloudStorageBasicAuth::checkConnection($conn_id, $user_id, $config);
                            $connection = true;
                        } catch(ilCloudStorageException $e) {
                            $connection = false;
                        }
                        break;
                }
            }
            // needs authentication or re-authentication
            if (!$connection) {
                $this->serviceAuth($config->getConnId());
                return null;
            }
            
            
        }
        */
        /*
        $form = parent::initCreateForm($a_new_type);
        // CloudStorageConn RadioButtons

        $rg = new ilRadioGroupInputGUI($this->txt("conn_id"),'conn_id');
        $rg->setRequired(true);
        foreach (ilCloudStorageConfig::_getAvailableCloudStorageConn(true) as $key => $value) {
            $ro = new ilRadioOption($value, $key);
            $config = ilCloudStorageConfig::getInstance($key);
            if ($config->getAuthMethod() == ilCloudStorageConfig::AUTH_METHOD_BASIC) {
                $ti = new ilTextInputGUI($this->txt("account_username"), "username_{$key}");
                $ti->setRequired(true);
                $ti->setMaxLength(1024);
                $ti->setSize(60);
                $ro->addSubItem($ti);

                $pi = new ilPasswordInputGUI($this->txt("account_password"), "password_{$key}");
                $pi->setRequired(true);
                $pi->setMaxLength(1024);
                $pi->setSize(60);
                $ro->addSubItem($pi);
            }
            $rg->addOption($ro);
        }
        $form->addItem($rg);

        // online
        $cb = new ilCheckboxInputGUI($this->lng->txt("online"), "online");
        $form->addItem($cb);

        return $form;
        */
        return null;
    }

    public function cancelCreation(): void {
        $this->dic->logger()->root()->debug("ilObjCloudStorageGUI cancelCreation");
        $this->dic->logger()->root()->debug("object refId: " . $this->object->getRefId());
        assert($this->object instanceof ilObjCloudStorage);
        $objId = $this->object->getConnId();
        $this->object->delete();
        $this->redirectToCreate($this->parent_id, $objId);
    }
    /**
     * @param ilObject $newObj
     * @global $DIC
     */
    public function afterSave(ilObject $newObj): void
    {
        $this->dic->logger()->root()->debug("ilObjCloudStorageGUI afterSave");
        
        $this->dic->ctrl()->saveParameterByClass(get_class($this), "action");
        
        $connId = ilCloudStorageUtil::getIntPost('conn_id');
        $online = ilCloudStorageUtil::getIntPost('online');
        $config = ilCloudStorageConfig::getInstance($connId);
        
        $this->dic->logger()->root()->debug("ilObjCloudStorageGUI afterSave connId: " . $connId);
        
        $action = ilCloudStorageUtil::getStringParam('action');
        
        $this->dic->logger()->root()->debug("ilObjCloudStorageGUI action: " . $action);
        
        assert($newObj instanceof ilObjCloudStorage);
        if ($action == 'new_folder') {
            $newFolder = ilCloudStorageUtil::getStringPost('new_folder');
            $this->dic->logger()->root()->debug("ilObjCloudStorageGUI new_folder: " . $newFolder);
            if ($newFolder == '') {
                $newObj->delete();
                $this->tpl->setOnScreenMessage('failure', $this->txt("must_not_empty"), true);
                $this->redirectToCreate($this->parent_id, $connId);
                return;
            } else {
                $newFolder = str_replace("/","_", trim($newFolder,"/"));
                $newObj->setTitle($newFolder);
                $newObj->setRootFolder($config->getBaseDirectory() . "/" . $newFolder);
            }
        }
        $newObj->setOwnerId($this->dic->user()->getId());
        $newObj->setAuthComplete(true);
        //$newObj->createFolder((int) $form->getInput("online"), $connId);
        $newObj->createFolder($online, $connId);
        $newObj->update();
        // always send a message
        //$this->tpl->setOnScreenMessage('success', $this->lng->txt("object_added"), true);
        //$this->dic->ctrl()->setParameterByClass(get_class($this), "create_mode", ilCloudStorageUtil::getStringParam('create_mode'));
        $this->parentAfterSave($newObj);
    }

    // this is for suppressing the object "object created" screen message
    private function parentAfterSave(ilObjCloudStorage $new_object) {
        $this->dic->ctrl()->setTargetScript('ilias.php');
        $this->dic->ctrl()->setParameterByClass(get_class($this), "ref_id", $new_object->getRefId());
        $this->dic->ctrl()->redirectByClass(["ilobjplugindispatchgui", get_class($this)], $this->getAfterCreationCmd());
    }

    /**
     * After object has been created -> jump to this command
     */
    public function getAfterCreationCmd(): string
    {
        //return "editProperties";
        return "showContent";
    }

    /**
     * Get standard command
     */
    public function getStandardCmd(): string
    {
        return "showContent";
    }

    public function handleAfterCreation(string $source): bool {
        assert($this->object instanceof ilObjCloudStorage);
        assert($this->service instanceof ilCloudStorageGenericService);
        $action = ilCloudStorageUtil::getStringParam('action');
        $this->dic->logger()->root()->debug("{$source} action: " . $action);
        switch ($action) {
            case "choose_root":
                if ($this->object->currentUserIsOwner()) {
                    $this->showTreeView(true);
                    return true;
                } else {
                    $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->object->txt('cld_only_owner_has_permission_to_change_root_path'), true);
                    $this->dic->ctrl()->redirect($this, $source);
                    return true;
                }
                break;
            case "new_folder":
                if ($this->object->currentUserIsOwner()) {
                    $this->dic->logger()->root()->debug("{$source} root_folder: " . $this->object->getRootFolder());
                    if ($this->service->folderExists($this->object->getRootFolder())) {
                        $this->object->delete();
                        $this->tpl->setOnScreenMessage('failure', $this->txt("cld_folder_already_existing_on_service") . ": " . $this->object->getRootFolder(), false);
                        $this->redirectToCreate($this->parent_id, $this->object->getConnId());
                        return true;
                        // ToDo
                    } else {
                        try {
                            $this->service->createFolder($this->object->getRootFolder());
                        } catch (Exception $e) {
                            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $e->getMessage());
                        }
                    }
                } else {
                    $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->object->txt('cld_only_owner_has_permission_to_change_root_path'), true);
                    $this->dic->ctrl()->redirect($this, $source);
                    return true;
                }
                break;
        }
        return false;
    }

    /**
     * Edit Properties. This commands uses the form class to display an input form.
     */
    public function editProperties(): void
    {
        $this->dic->logger()->root()->debug("editProperties()");
        $this->dic->tabs()->activateTab("properties");
        assert($this->object instanceof ilObjCloudStorage);
        assert($this->service instanceof ilCloudStorageGenericService);
        $root_path = ilCloudStorageUtil::getStringParam('root_path');
        if ($root_path != '') {
            $this->dic->logger()->root()->debug("editProperties() root_path: " . $root_path);
            $this->setRootFolder($root_path);
            return;
        } else {
            $exit = $this->handleAfterCreation('editProperties'); // required only if switched getAfterCreationCmd to editProperties
            if ($exit) {
                return;
            }
        }
        $this->initPropertiesForm();
        $this->getPropertiesValues();
        $this->dic->ui()->mainTemplate()->setContent($this->form->getHTML());
    }

    public function initPropertiesForm(): void
    {
        assert($this->object instanceof ilObjCloudStorage);
        //assert($this->serviceGUI instanceof ilCloudStorageServiceGUIInterface);
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

        //$modal = new il
        
        // service // ToDo
        // $this->serviceGUI->initPropertiesForm();
        $folder = new ilTextInputGUI($this->object->txt("root_folder"), "root_folder");
        if (!$this->object->currentUserIsOwner()) {
            $folder->setDisabled(true);
            $folder->setInfo($this->object->txt("cld_only_owner_has_permission_to_change_root_path"));
        } else {
            $this->dic->ctrl()->setParameter($this, 'action', "choose_root");
            $folder->setInfo("<a href='{$this->dic->ctrl()->getLinkTarget($this, 'editProperties')}'>" . $this->object->txt("link_choose_root") . "</a>");
            $this->dic->ctrl()->setParameter($this, 'action', "");
        }
        $root_folder = ($this->object->getRootFolder() == "") ? $this->config->getBaseDirectory() : $this->object->getRootFolder();
        $folder->setValue($root_folder);
        $folder->setMaxLength(255);
        $folder->setSize(50);
        $this->form->addItem($folder);

        /*
        switch ($this->config->getAuthMethod()) {
            case $this->config::AUTH_METHOD_OAUTH2:
                // Noting ToDo
                
                $n = new ilNonEditableValueGUI($this->object->txt('info_token_expires'));

                $validThrough = $this->service->getToken()->getValidThrough();
                $this->dic->logger()->root()->debug("accesstoken valid through: " . date('d.m.Y - H:i:s', $validThrough));

                $created = strtotime('-'. $this->service->getAccessTokenExpiration(),  $validThrough);
                $this->dic->logger()->root()->debug("accesstoken created: " . date('d.m.Y - H:i:s', $created));

                $refreshValidThrough = strtotime('+'.$this->service->getRefreshTokenExpiration(), $created);
                $this->dic->logger()->root()->debug("refresh token valid through: " . date('d.m.Y - H:i:s', $refreshValidThrough));

                $n->setValue(date('d.m.Y - H:i:s', $refreshValidThrough));
                $this->form->addItem($n);
                
                break;
            case $this->config::AUTH_METHOD_BASIC:
                // Nothing ToDo
                break;
            default:
                //ToDo
        }
        */

        $this->form->addCommandButton("updateProperties", $this->lng->txt("save"));
        $this->form->setTitle($this->txt("edit_properties"));
        $this->form->setFormAction($this->dic->ctrl()->getFormAction($this));
 
    }

    public function getPropertiesValues(): void
    {
        global $DIC;
        assert($this->object instanceof ilObjCloudStorage);
        //assert($this->serviceGUI instanceof ilCloudStorageServiceGUIInterface);
        $values["title"] = ilStr::shortenTextExtended($this->object->getTitle(), 64, true);
        $values["desc"] = $this->object->getDescription();
        $values["online"] = $this->object->getOnline();
        $root_folder = ($this->object->getRootFolder() == "") ? $this->config->getBaseDirectory() : $this->object->getRootFolder();
        $values['root_folder'] = $root_folder;
        //$this->serviceGUI->getPropertiesValues($values);
        $this->form->setValuesByArray($values);
    }

    public function updateProperties(): void
    {
        assert($this->object instanceof ilObjCloudStorage);
        //assert($this->serviceGUI instanceof ilCloudStorageServiceGUIInterface);
        $this->initPropertiesForm();
        $oldRootFolder = $this->object->getRootFolder();
        $oldTitle = $this->object->getTitle();
        if ($this->form->checkInput()) {
            $this->dic->object()->commonSettings()->legacyForm($this->form, $this->object)->saveTileImage();
            $rootFolder = $this->form->getInput("root_folder");
            $title = ilStr::shortenTextExtended($this->form->getInput("title"),64,true);
            // only re-new title if root folder changed and title not edited
            if (($oldRootFolder != $rootFolder) && ($oldTitle == $title)) {
                if ($rootFolder == "/") {
                    $this->object->setTitle($this->getRootName());
                } else {
                    $this->object->setTitle(basename($rootFolder));
                }
            } else {
                $this->object->setTitle($title);
            }
            $this->object->setDescription($this->form->getInput("desc"));
            $this->object->setOnline($this->form->getInput("online"));
            //$this->serviceGUI->updateProperties();
            $root_folder = ($this->form->getInput("root_folder") == "") ? $this->config->getBaseDirectory() : $this->form->getInput("root_folder");
            $this->object->setRootFolder($root_folder);
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
            if ($root_path == "/") {
                $this->object->setTitle($this->getRootName());
            } else {
                $this->object->setTitle(basename($this->object->getRootFolder()));
            }
            $this->clearParams();
            $this->object->update();
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('success', $this->lng->txt('msg_obj_modified'), true);
        } else {
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('cld_only_owner_has_permission_to_change_root_path'), true);
        }
        $this->dic->ctrl()->redirect($this,'showContent');
    }

    private function clearParams() {
        $this->dic->ctrl()->setParameter($this, 'action', '');
        $this->dic->ctrl()->setParameter($this, 'root_path', '');
        //unset($_SESSION['xcls_create_folder_action']);
    }

    // Sn: from class.ilCloudPluginInitGUI.php
    public function showContent()
    {
        assert($this->object instanceof ilObjCloudStorage);
        // bug dirty hack: if comming from wrong locator entry in objectactivationgui or conditionhandlergui
        // it would be better to avoid the locator entry
        $exit = $this->handleAfterCreation('showContent');
        if ($exit) {
            return;
        }
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
                    $current_id = $file_tree->getRootNode()->getId();
                    $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt("node_is_null"), false);
                } else {
                    $current_id = $node->getId();
                }
            } else {
                $current_path = $file_tree->getRootNode()->getPath();
                $current_id = $file_tree->getRootNode()->getId();
                $file_tree->updateFileTree($current_path);
            }
            // old <script type="text/javascript"> var coudFileList = new ilCloudFileList({ASYNC_GET_BLOCK}, {ASYNC_CREATE_FOLDER}, {ASYNC_UPLOAD_FILE}, {ASYNC_DELETE_ITEM}, {ROOT_ID}, {ROOT_PATH}, {CURRENT_ID}, {CURRENT_PATH}, {MAX_FILE_SIZE}); </script>
            $code = 'il.CloudFileList = new ilCloudFileList(' .
                json_encode($this->ctrl->getLinkTarget($this, "asyncGetBlock", null, true)) . ',' .
                json_encode($this->ctrl->getLinkTarget($this, "asyncCreateFolder", null, true)) . ',' .
                json_encode($this->ctrl->getLinkTarget($this, "asyncUploadFile", null, true)) . ',' .
                json_encode($this->ctrl->getLinkTarget($this, "asyncDeleteItem", null, true)) . ',' .
                $file_tree->getRootNode()->getId() . ',' .
                json_encode($file_tree->getRootNode()->getPath()) . ',' .
                $current_id . ',' .
                "\"" . ilCloudStorageUtil::encodeBase64Path($current_path) . "\"," .
                json_encode("Max Filesize....") . ");";
            $this->dic->ui()->mainTemplate()->addOnLoadCode($code);
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

    public function showTreeView(bool $afterCreation = false): void
    {
        $this->dic->logger()->root()->debug("showTreeView");
        assert($this->object instanceof ilObjCloudStorage);
        assert($this->service instanceof ilCloudStorageGenericService);
        $isAsync = false;
        if ($this->dic->http()->wrapper()->query()->has("cmdMode")) {
            if ($this->dic->http()->wrapper()->query()->retrieve("cmdMode",  $this->dic->refinery()->to()->string()) == "asynch") {
                $isAsync = true;
            }
        }
        if (!$isAsync) {
            $this->dic->logger()->root()->debug("showTreeView not async");
            
            //$client = $this->service->getClient();
            if ($this->service->hasConnection()) {
                $this->dic->logger()->root()->debug("connection");
                $tree = new ilCloudStorageTree($this->service);
                $tree_gui = new ilCloudStorageTreeGUI('tree_expl', $this, 'editProperties', $tree);
                $this->dic->tabs()->clearTargets();
                if (!$afterCreation) {
                    $this->dic->tabs()->setBackTarget($this->object->txt('back'), $this->dic->ctrl()->getLinkTarget($this, 'editProperties'));
                } else {
                    $this->dic->tabs()->setBackTarget($this->object->txt('back'), $this->dic->ctrl()->getLinkTarget($this, 'cancelCreation'));
                }
                
                $this->dic->ui()->mainTemplate()->setOnScreenMessage('info', $this->object->txt('choose_root'), true);
                $this->dic->ctrl()->setParameter($this, 'action', 'choose_root');
                $this->dic->ui()->mainTemplate()->setContent($tree_gui->getHTML());
                
            } else {
                $this->dic->logger()->root()->debug("no connection");
                $this->dic->ctrl()->redirect($this, 'editProperties');
            }
            
        } else {
            $this->dic->logger()->root()->debug("showTreeView async");
            
            //$client = $this->service->getClient();
            if ($this->service->hasConnection()) {
                $this->dic->logger()->root()->debug("showTreeView async hasConnection");
                $tree = new ilCloudStorageTree($this->service);
                $tree_gui = new ilCloudStorageTreeGUI('tree_expl', $this, 'editProperties', $tree);
                $tree_gui->handleCommand();
                return;
            }
            
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
            $folderName = htmlspecialchars(basename($node->getPath()));
            $item->setVariable("TXT_TITLE_LINKED", $folderName);
            $item->setVariable("HREF_TITLE_LINKED", $this->getLinkToFolder($node) . "\" . title=\"" . $folderName);
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
            $fileName = htmlspecialchars(basename($node->getPath()));
            if ($download) {
                $item->setVariable("TXT_TITLE_LINKED", $fileName);
                $item->setVariable("HREF_TITLE_LINKED", $this->dic->ctrl()->getLinkTarget($this, "getFile") . "&id=" . $node->getId() . "\" . title=\"" . $fileName);
            } else {
                $item->setVariable("TXT_TITLE", "<span title=\"" . $fileName . "\">" . $fileName . "</span>");
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
        return "#/open_folder?id_parent=" . $node->getParentId() . "&current_id=" . $node->getId() . "&current_path=" . self::_urlencode($node->getPath()) . "\" title=\"" . htmlspecialchars(basename($node->getPath()));
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
            $newdir = ilFileUtilsLegacy::ilTempnam();

            $this->dic->logger()->root()->debug("handleFileUpload tempdir " . $newdir);
            ilFileUtilsLegacy::makeDir($newdir);
            try {
                ilFileUtilsLegacy::processZipFile($newdir, $file_upload["tmp_name"], $_POST["keep_structure"]);
            } catch (Exception $e) {
                $this->dic->logger()->root()->debug("handleFileUpload error " . $e->getMessage());
                $response->error = $e->getMessage();
                ilFileUtilsLegacy::delDir($newdir);
                exit;
            }

            try {
                $this->uploadDirectory($newdir, $_SESSION["cld_folder_id"], $file_tree, $_POST["keep_structure"]);
            } catch (Exception $e) {
                $response->error = $e->getMessage();
                ilFileUtilsLegacy::delDir($newdir);
                exit;
            }

            ilFileUtilsLegacy::delDir($newdir);

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

        if (($delete_item && !$node->getIsDir()) || ($delete_folder && $node->getIsDir()) || $this->checkHasAction($node)) {
            //include_once("./Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php");
            $selection_list = new ilAdvancedSelectionListGUI();
            $selection_list->setId("id_action_list_" . $node->getId());
            $selection_list->setListTitle($this->lng->txt("actions"));
            $selection_list->setItemLinkClass("xsmall");

            $this->addItemsBefore($node, $selection_list);
            if (($delete_item && !$node->getIsDir()) || ($delete_folder && $node->getIsDir())) {
                $selection_list->addItem($this->lng->txt("delete"), "delete_item", "javascript:il.CloudFileList.deleteItem('" . $node->getId()
                . "');");
            }
            $this->addItemsAfter($node, $selection_list);
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

    public function redirectToCreate(int $ref_id, int $conn_id): void
    {
        $this->dic->ctrl()->setParameterByClass('ilRepositoryGUI', 'cmd', 'create');
        $this->dic->ctrl()->setParameterByClass('ilRepositoryGUI','new_type', 'xcls');
        $this->dic->ctrl()->setParameterByClass('ilRepositoryGUI','ref_id', (string) $ref_id);
        $this->dic->ctrl()->setParameterByClass('ilRepositoryGUI','conn_id', (string) $conn_id);
        $this->dic->ctrl()->redirectByClass('ilRepositoryGUI');
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
            unset($content[2]);
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
                $this->dic->logger()->root()->error("ERROR: " . $e->getMessage());
                $path = '';
            }
        } else {
            if ($this->dic->http()->wrapper()->post()->has("path") && $path == '') {
                $path = $this->dic->http()->wrapper()->post()->retrieve("path", $this->dic->refinery()->kindlyTo()->string());
            }
        }
        return ilCloudStorageUtil::normalizePath($path);
    }

    public function getRootName(): string 
    {
        return $this->txt('root_folder_name');
    }

    // old serviceGUI
    public function addItemsBefore(ilCloudStorageFileNode $node, ilAdvancedSelectionListGUI &$selection_list): void {}

    public function addItemsAfter(ilCloudStorageFileNode $node, ilAdvancedSelectionListGUI &$selection_list): void
    {
        if ($this->checkHasAction($node)) {
            $this->dic->ctrl()->setParameter($this, self::ITEM_ID, $node->getId());
            $this->dic->ctrl()->setParameter($this, self::ITEM_PATH, urlencode($node->getPath()));
            $selection_list->addItem(
                $this->txt('open_in_platform'),
                '',
                $this->dic->ctrl()->getLinkTarget($this, self::CMD_OPEN_IN_PLATFORM),
                '',
                '',
                '_blank'
            );
        }
    }

    public function checkHasAction(ilCloudStorageFileNode $node): bool
    {
        // Sn: ToDo ?
        //$upload_perm = $this->dic->access()->checkAccess('edit_in_online_editor', '', filter_input(INPUT_GET, 'ref_id', FILTER_SANITIZE_NUMBER_INT));
        
        $this->dic->logger()->root()->debug("checkHasAction: " . $node->getPath());
        $upload_perm = $this->dic->access()->checkAccess('edit_in_online_editor', '', $this->object->getRefId());
        $this->dic->logger()->root()->debug("checkHasAction: upload_perm: " . $upload_perm);
        $format = strtolower(pathinfo($node->getPath(), PATHINFO_EXTENSION));
        $this->dic->logger()->root()->debug("checkHasAction: format: " . $format);
        $this->dic->logger()->root()->debug("checkHasAction: getIsDir: " . $node->getIsDir());
        $this->dic->logger()->root()->debug("checkHasAction: isOpenInPlatformActive: " . $this->isOpenInPlatformActive());
        return $upload_perm
            && !$node->getIsDir()
            && in_array($format, $this->config->getCollaborationAppFormatsAsArray())
            && $this->isOpenInPlatformActive();
    }

    public function openInPlatform(): void
    {
        assert($this->service instanceof ilCloudStorageGenericService);
        $ref_id = $this->dic->http()->wrapper()->query()->retrieve('ref_id', $this->dic->refinery()->kindlyTo()->int());
        $upload_perm = $this->dic->access()->checkAccess('edit_in_online_editor', '', $ref_id);
        if (!$upload_perm || !$this->isOpenInPlatformActive()) {
            echo 'Permission Denied.';
            exit;
        }
        $path = $this->dic->http()->wrapper()->query()->retrieve(self::ITEM_PATH, $this->dic->refinery()->kindlyTo()->string());
        $id = $this->dic->http()->wrapper()->query()->retrieve(self::ITEM_ID, $this->dic->refinery()->kindlyTo()->string());
        //$this->checkAndRefreshAuthentication();
        //$client = $this->service->getClient();
        $this->service->shareItem($path, $this->dic->user());

        $url = $this->config->getFullCollaborationAppPath($id, urlencode($path));
        Header('Location: ' . $url);
        exit;
    }

    protected function isOpenInPlatformActive(): bool
    {
        if (is_null($this->open_in_platform_active)) {
            $this->open_in_platform_active = $this->config->getCollaborationAppIntegration();
        }

        return $this->open_in_platform_active;
    }
}
