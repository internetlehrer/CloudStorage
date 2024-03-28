<?php
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
 * @ilCtrl_isCalledBy ilCloudStorageOwnCloudGUI: ilObjCloudStorageGUI
 * @ilCtrl_Calls ilCloudStorageOwnCloudGUI: ilObjCloudStorageGUI
 *
 */

use ILIAS\DI\Container;

class ilCloudStorageOwnCloudGUI implements ilCloudStorageServiceGUIInterface
{
    const CMD_OPEN_IN_PLATFORM = 'openInPlatform';
    
    const ITEM_ID = 'item_id';
    
    const ITEM_PATH = 'item_path';

    private Container $dic;

    public ?ilObjCloudStorageGUI $parent = null;

    public ?ilObjCloudStorage $object = null;

    private ?ilCloudStorageOwnCloud $service = null;

    private ?ilCloudStorageConfig $config = null;

    private ?bool $open_in_platform_active = null;

    public function __construct(ilObjCloudStorageGUI &$a_parent)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->parent = $a_parent;
        $this->object = $this->parent->object;
        $this->service = $this->parent->service;
        $this->config = $this->parent->config;
    }

    public function editProperties(): void {
        $root_path = ($this->dic->http()->wrapper()->query()->has('root_path')) ? $this->dic->http()->wrapper()->query()->retrieve('root_path', $this->dic->refinery()->kindlyTo()->string()): '';
        if ($root_path != '') {
            $this->parent->setRootFolder($root_path);
            $this->clearParams();
        } else {
            $action = ($this->dic->http()->wrapper()->query()->has('action')) ? $this->dic->http()->wrapper()->query()->retrieve('action', $this->dic->refinery()->kindlyTo()->string()): '';
            switch ($action) {
                case "choose_root":
                    if ($this->object->currentUserIsOwner()) {
                        $this->showTreeView();
                    } else {
                        $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', $this->object->txt('cld_only_owner_has_permission_to_change_root_path'), true);
                        $this->dic->ctrl()->redirect($this->parent, 'editProperties');
                    }
                    $this->clearParams();
                    $this->showTreeView();
                    break;
                default:
                    $this->parent->editProperties();
            }
        }
    }

    private function clearParams() {
        $this->dic->ctrl()->setParameter($this->parent, 'action', '');
        $this->dic->ctrl()->setParameter($this->parent, 'root_path', '');
    }

    public function updateProperties(): void
    {
        $root_folder = ($this->parent->form->getInput("root_folder") == "") ? $this->config->getBaseDirectory() : $this->parent->form->getInput("root_folder");
        $this->object->setRootFolder($root_folder);
    }

    public function initPropertiesForm(): void
    {
        $folder = new ilTextInputGUI($this->object->txt("root_folder"), "root_folder");
        if (!$this->object->currentUserIsOwner()) {
            $folder->setDisabled(true);
            $folder->setInfo($this->object->txt("cld_only_owner_has_permission_to_change_root_path"));
        } else {
            $this->dic->ctrl()->setParameter($this->parent, 'action', "choose_root");
            $folder->setInfo("<a href='{$this->dic->ctrl()->getLinkTarget($this->parent, 'editProperties')}'>" . $this->object->txt("link_choose_root") . "</a>");
            $this->dic->ctrl()->setParameter($this->parent, 'action', "");
        }
        $root_folder = ($this->object->getRootFolder() == "") ? $this->config->getBaseDirectory() : $this->object->getRootFolder();
        $folder->setValue($root_folder);
        $folder->setMaxLength(255);
        $folder->setSize(50);
        $this->parent->form->addItem($folder);

        /*
        if ($this->getAdminConfigObject()->getValue(ownclConfig::F_COLLABORATION_APP_INTEGRATION)) {
            $open_in_owncloud = new ilCheckboxInputGUI($this->txt('allow_open_in_owncloud'), 'allow_open_in_owncloud');
            $open_in_owncloud->setInfo($this->txt('allow_open_in_owncloud_info'));
            $this->form->addItem($open_in_owncloud);
        }
        */
        if ($this->config->getOAuth2Active()) {    
            $n = new ilNonEditableValueGUI($this->object->txt('info_token_expires'));
            $n->setValue(date('d.m.Y - H:i:s', $this->service->getToken()->getValidThrough()));
            $this->parent->form->addItem($n);
        }
    }

    public function getPropertiesValues(array &$values): void
    {
        $root_folder = ($this->object->getRootFolder() == "") ? $this->config->getBaseDirectory() : $this->object->getRootFolder();
        $values['root_folder'] = $root_folder;
    }

    /*
    private function setRootFolder(string $a_root_path) {
        $this->object->setRootFolder(ilCloudStorageUtil::normalizePath($a_root_path));
        $this->object->update();
        $this->dic->ui()->mainTemplate()->setOnScreenMessage('success', $this->object->txt('msg_obj_modified'), true);
        $this->dic->ctrl()->redirect($this->parent, 'editProperties');
    }
    */
    public function showTreeView(): void
    {
        $this->dic->logger()->root()->debug("showTreeView");
        $isAsync = false;
        if ($this->dic->http()->wrapper()->query()->has("cmdMode")) {
            if ($this->dic->http()->wrapper()->query()->retrieve("cmdMode",  $this->dic->refinery()->to()->string()) == "asynch") {
                $isAsync = true;
            }
        }
        if (!$isAsync) {
            $this->dic->logger()->root()->debug("showTreeView not async");
            $client = $this->service->getClient();
            if ($client->hasConnection()) {
                $tree = new ilCloudStorageOwnCloudTree($client);
                $tree_gui = new ilCloudStorageOwnCloudTreeGUI('tree_expl', $this->parent, 'editProperties', $tree);
                $this->dic->tabs()->clearTargets();
                $this->dic->tabs()->setBackTarget($this->object->txt('back'), $this->dic->ctrl()->getLinkTarget($this->parent, 'editProperties'));
                $this->dic->ui()->mainTemplate()->setOnScreenMessage('info', $this->object->txt('choose_root'), true);
                $this->dic->ctrl()->setParameter($this->parent, 'action', 'choose_root');
                $this->dic->ui()->mainTemplate()->setContent($tree_gui->getHTML());
            } else {
                $this->dic->ctrl()->redirect($this->parent, 'editProperties');
            }
        } else {
            $this->dic->logger()->root()->debug("showTreeView async");
            $client = $this->service->getClient();
            if ($client->hasConnection()) {
                $this->dic->logger()->root()->debug("showTreeView async hasConnection");
                $tree = new ilCloudStorageOwnCloudTree($client);
                $tree_gui = new ilCloudStorageOwnCloudTreeGUI('tree_expl', $this->parent, 'editProperties', $tree);
                $tree_gui->handleCommand();
                return;
            }
        }
    }

    //from ilOwnCloudActionListGUI
    public function addItemsBefore(ilCloudStorageFileNode $node, ilAdvancedSelectionListGUI &$selection_list): void {}

    public function addItemsAfter(ilCloudStorageFileNode $node, ilAdvancedSelectionListGUI &$selection_list): void
    {
        if ($this->checkHasAction($node)) {
            $this->dic->ctrl()->setParameter($this->parent, self::ITEM_ID, $node->getId());
            $this->dic->ctrl()->setParameter($this->parent, self::ITEM_PATH, urlencode($node->getPath()));
            $selection_list->addItem(
                $this->object->txt('open_in_owncloud'),
                '',
                $this->dic->ctrl()->getLinkTarget($this->parent, self::CMD_OPEN_IN_PLATFORM),
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
        $upload_perm = $this->dic->access()->checkAccess('edit_in_online_editor', '', filter_input(INPUT_GET, 'ref_id', FILTER_SANITIZE_NUMBER_INT));
        if (!$upload_perm || !$this->isOpenInPlatformActive()) {
            echo 'Permission Denied.';
            exit;
        }
        $path = filter_input(INPUT_GET, self::ITEM_PATH, FILTER_SANITIZE_STRING);
        $id = filter_input(INPUT_GET, self::ITEM_ID, FILTER_SANITIZE_STRING);
        $this->service->checkAndRefreshAuthentication();
        $client = $this->service->getClient();
        $client->shareItem($path, $this->dic->user());

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

?>