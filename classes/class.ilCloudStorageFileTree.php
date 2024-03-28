<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * ilCloudStorageFileTree class
 *
 * Representation of the structure of all files and folders so far. Not really a tree but a list simulating a tree
 * (for faster access on the nodes). This class also calls the functions of a service to update the tree (addToFileTree,
 * deleteItem, etc.)
 *
 * @author Timon Amstutz <timon.amstutz@ilub.unibe.ch>
 * @version $Id$
 * @ingroup ModulesCloud
 */
class ilCloudStorageFileTree
{

    protected int $refId;

    protected int $connId;
    
    /**
     * id of the ilCloudStorageFileTree, equals the object_id of the calling object or gui_class
     */

    protected int $id = 0;

    protected ?ilCloudStorageFileNode $root_node;

    /**
     * Path to $root_node ($root_node has always path "/", root_path is the path which can be changed in the settings)
     */
    protected string $root_path = "";

    protected array $item_list = [];

    protected array $id_to_path_map = [];

    protected bool $case_sensitive = true;

    public function __construct(string $root_path, int $root_id, int $id, int $a_refId, int $a_connId)
    {
        global $DIC;
        $root_path = (empty($root_path)) ? "/" : $root_path;
        $this->setId($id);
        $this->connId = $a_connId;
        $this->refId = $a_refId;
        $this->root_node = $this->createNode($root_path, $root_id, true);
        $service = ilCloudStorageConfig::getServiceFromConfig($this->refId, $this->connId);
        $this->setCaseSensitive($service->isCaseSensitive());
    }

    protected function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    protected function setRootPath(string $path = "/"): void
    {
        $this->root_path = ilCloudStorageUtil::normalizePath($path);
    }

    public function getRootPath(): string
    {
        return $this->root_path;
    }

    public function isCaseSensitive(): bool
    {
        return $this->case_sensitive;
    }

    public function setCaseSensitive(bool $case_sensitive): void
    {
        $this->case_sensitive = $case_sensitive;
    }

    public function getRootNode(): ilCloudStorageFileNode
    {
        return $this->root_node;
    }

    protected function createNode(string $path, string $id, bool $is_dir = false): ilCloudStorageFileNode
    {
        $path = (empty($path)) ? "/" : $path;
        $node = new ilCloudStorageFileNode(ilCloudStorageUtil::normalizePath($path), $id);
        $this->item_list[$node->getPath()] = $node;
        $this->id_to_path_map[$node->getId()] = $node->getPath();
        $node->setIsDir($is_dir);
        return $node;
    }

    public function addNode(string $path, string $id, bool $is_Dir, int $modified = 0, $size = 0): ilCloudStorageFileNode
    {
        $path = ilCloudStorageUtil::normalizePath($path);
        $node = $this->getNodeFromPath($path);

        //node does not yet exist
        if (!$node) {
            if ($this->getNodeFromId($id)) {
                throw new ilCloudStorageException(ilCloudStorageException::ID_ALREADY_EXISTS_IN_FILE_TREE_IN_SESSION);
            }
            $path_of_parent = ilCloudStorageUtil::normalizePath(dirname($path));
            $node_parent = $this->getNodeFromPath($path_of_parent);
            if (!$node_parent) {
                throw new ilCloudStorageException(ilCloudStorageException::PATH_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION, "Parent: " . $path_of_parent);
            }
            $node = $this->createNode($path, $id, $is_Dir);
            $node->setParentId($node_parent->getId());
            $node_parent->addChild($node->getPath());
        }
        $node->setSize((int) $size);
        $node->setModified((int) $modified);
        return $node;
    }

    public function addIdBasedNode(string $path, string $id, string $parent_id, bool $is_Dir, int $modified = null, $size = 0): ilCloudStorageFileNode
    {
        $path = ilCloudStorageUtil::normalizePath($path);
        $node = $this->getNodeFromPath($path);

        //node does not yet exist
        if (!$node) {
            $nodeFromId = $this->getNodeFromId($id);
            // If path isn't found but id is there -> Path got changed
            if ($nodeFromId) {
                // Adjust path
                $nodeFromId->setPath($path);
            }

            $node_parent = $this->getNodeFromId($parent_id);
            if (!$node_parent) {
                throw new ilCloudStorageException(ilCloudStorageException::PATH_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION, "Parent: " . $parent_id);
            }
            $node = $this->createNode($path, $id, $is_Dir);
            $node->setParentId($node_parent->getId());
            $node_parent->addChild($node->getPath());
        }

        $node->setSize($size);
        $node->setModified($modified);

        return $node;
    }

    public function removeNode(string $path): void
    {
        $node = $this->getNodeFromPath($path);
        $parent = $this->getNodeFromId($node->getParentId());
        $parent->removeChild($path);
        unset($this->item_list[$node->getPath()]);
        unset($this->id_to_path_map[$node->getId()]);
    }

    /**
     * @return array
     */
    public function getItemList()
    {
        return $this->item_list;
    }

    /**
     * @param   string $path
     * @return  ilCloudStorageFileNode  node;
     */
    public function getNodeFromPath($path = "/")
    {
        if (!$this->isCaseSensitive() || array_key_exists($path, $this->item_list)) {
            return $this->item_list[$path];
        }

        foreach (array_keys($this->item_list) as $item) {
            if (strtolower($item) == strtolower($path)) {
                return $this->item_list[$item];
            }
        }

        return null;
    }

    /**
     * @param $id
     * @return bool|ilCloudStorageFileNode
     */
    public function getNodeFromId($id)
    {
        if (!array_key_exists($id, $this->id_to_path_map)) {
            return false;
        } else {
            if (!array_key_exists($this->id_to_path_map[$id], $this->item_list)) {
                return false;
            } else {
                return $this->item_list[$this->id_to_path_map[$id]];
            }
        }
    }

    /**
     * @param $path
     * @throws ilCloudStorageException
     */
    public function setLoadingOfFolderComplete($path)
    {
        $node = $this->getNodeFromPath($path);
        if (!$node) {
            throw new ilCloudStorageException(ilCloudStorageException::PATH_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION, $path);
        }
        $node->setLoadingComplete(true);
    }

    /**
     * @param $current_path
     */
    public function updateFileTree($current_path)
    {
        $node = $this->getNodeFromPath($current_path);

        if (!$node) {
            // infinite loop protection?
            if ($current_path == dirname($current_path)) {
                return;
            }
            $this->updateFileTree(dirname($current_path));
            $node = $this->getNodeFromPath($current_path);
        }
        if ($node && !$node->getLoadingComplete()) {
            $this->addItemsFromService($node->getId());
        }
        $this->storeFileTreeToSession();
    }

    public function addItemsFromService($folder_id)
    {
        try {
            $node = $this->getNodeFromId($folder_id);
            if (!$node) {
                throw new ilCloudStorageException(ilCloudStorageException::ID_DOES_NOT_EXIST_IN_FILE_TREE_IN_SESSION, $folder_id);
            }
            $service = ilCloudStorageConfig::getServiceFromConfig($this->refId, $this->connId);
            assert($service instanceof ilCloudStorageServiceInterface);
            $service->addToFileTree($this, $node->getPath());
        } catch (Exception $e) {
            if ($e instanceof ilCloudStorageException) {
                if ($e->getCode() == ilCloudStorageException::RESSOURCE_NOT_EXISTING_OR_RENAMED) {
                    // root folder does not exists anymore
                    if ($node->getParentId() == -1) {
                        throw new ilCloudStorageException(ilCloudStorageException::ROOT_FOLDER_NOT_EXISTING_OR_RENAMED);
                    } else {
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }
            throw new ilCloudStorageException(ilCloudStorageException::ADD_ITEMS_FROM_SERVICE_FAILED, $e->getMessage());
        }
    }

    /**
     * @param $id
     * @param $folder_name
     *
     * @return bool|ilCloudStorageFileNode|null
     * @throws ilCloudStorageException
     */
    public function addFolderToService(string $id, string $folder_name)
    {

        try {
            if ($folder_name == null) {
                throw new ilCloudStorageException(ilCloudStorageException::INVALID_INPUT, $folder_name);
            }
            if (preg_match('/[^a-zA-Z0-9äüöÄÜÖ\(\) \_\-]/', $folder_name)) {
                $ae = new ilCloudStorageException(ilCloudStorageException::ALLOWED_CHARACTERS);
                throw new ilCloudStorageException(ilCloudStorageException::INVALID_INPUT, sprintf($ae->getMessage(),'a-zA-Z0-9äüöÄÜÖ() _-'));
            }
            $current_node = $this->getNodeFromId($id);
            $path = ilCloudStorageUtil::joinPaths($current_node->getPath(), ilCloudStorageUtil::normalizePath($folder_name));

            if ($this->getNodeFromPath($path) != null) {
                throw new ilCloudStorageException(ilCloudStorageException::FOLDER_ALREADY_EXISTING_ON_SERVICE, $folder_name);
            }


            $current_node->setLoadingComplete(false);
            $this->storeFileTreeToSession();

            $service = ilCloudStorageConfig::getServiceFromConfig($this->refId, $this->connId);
            assert($service instanceof ilCloudStorageServiceInterface);

            $new_folder_id = $service->createFolderById($id, $folder_name);
            $new_node = null;

            if (is_null($new_folder_id) || !$new_folder_id) {
                // Use path
                $service->createFolder($path, $this);
                $this->addItemsFromService($current_node->getId());
                $new_path = ilCloudStorageUtil::joinPaths($current_node->getPath(), $folder_name);
                $new_node = $this->getNodeFromPath($new_path);
            } else {
                // Use id
                $this->addItemsFromService($current_node->getId());
                $new_node = $this->getNodeFromId($new_folder_id);
            }

            return $new_node;
        } catch (Exception $e) {
            if ($e instanceof ilCloudStorageException) {
                throw $e;
            }
            throw new ilCloudStorageException(ilCloudStorageException::FOLDER_CREATION_FAILED, $e->getMessage());
        }
    }


    /**
     * @param $current_id
     * @param $tmp_name
     * @param $file_name
     *
     * @throws ilCloudStorageException
     */
    public function uploadFileToService(string $current_id, string $tmp_name, string $file_name): void
    {
        $max_file_size = ilFileUploadUtil::getMaxFileSize();
        if ($max_file_size >= filesize($tmp_name)) {
            $current_node = $this->getNodeFromId($current_id);
            $current_node->setLoadingComplete(false);
            $this->storeFileTreeToSession();
            try {
                $service = ilCloudStorageConfig::getServiceFromConfig($this->refId, $this->connId);
                $service->putFile($tmp_name, $file_name, $current_node->getPath(), $this);
            } catch (Exception $e) {
                if ($e instanceof ilCloudStorageException) {
                    throw $e;
                }
                throw new ilCloudStorageException(ilCloudStorageException::UPLOAD_FAILED, $e->getMessage());
            }
        } else {
            throw new ilCloudStorageException(ilCloudStorageException::UPLOAD_FAILED_MAX_FILESIZE, filesize($tmp_name) / (1024 * 1024) . " MB");
        }
    }

    public function deleteFromService(string $id): void
    {
        $item_node = $this->getNodeFromId($id);

        try {
            $service = ilCloudStorageConfig::getServiceFromConfig($this->refId, $this->connId);

            if (!$service->deleteItemById($item_node->getId())) {
                $service->deleteItem($item_node->getPath(), $this);
            }

            $this->removeNode($item_node->getPath());
            $this->storeFileTreeToSession();
        } catch (Exception $e) {
            if ($e instanceof ilCloudStorageException) {
                throw $e;
            }
            throw new ilCloudStorageException(ilCloudStorageException::DELETE_FAILED, $e->getMessage());
        }
    }

    /**
     * @param $id
     * @throws ilCloudStorageException
     */
    public function downloadFromService(string $id): void
    {
        try {
            $service = ilCloudStorageConfig::getServiceFromConfig($this->refId, $this->connId);
            $node = $this->getNodeFromId($id);
            if (!$service->getFileById($node->getId())) {
                $service->getFile($node->getPath(), $this);
            }
        } catch (Exception $e) {
            if ($e instanceof ilCloudStorageException) {
                throw $e;
            }
            throw new ilCloudStorageException(ilCloudStorageException::DOWNLOAD_FAILED, $e->getMessage());
        }
    }

    public function storeFileTreeToSession(): void
    {
        $_SESSION['ilCloudStorageFileTree_' . $this->refId] = null;
        $_SESSION['ilCloudStorageFileTree_' . $this->refId] = serialize($this);
    }

    /**
     * @return    ?ilCloudStorageFileTree  fileTree;
     */
    public static function getFileTreeFromSession(int $refId): ?ilCloudStorageFileTree
    {
        if (isset($_SESSION['ilCloudStorageFileTree_' . $refId])) {
            return unserialize($_SESSION['ilCloudStorageFileTree_' . $refId]);
        } else {
            return null;
        }
    }


    public static function clearFileTreeSession(int $refId): void
    {
        $_SESSION['ilCloudStorageFileTree_' . $refId] = null;
    }

    public function orderListAlphabet(string $path1, string $path2): int
    {
        $node1 = $this->getNodeFromPath($path1);
        $node2 = $this->getNodeFromPath($path2);
        if ($node1->getIsDir() != $node2->getIsDir()) {
            return $node2->getIsDir() ? +1 : -1;
        }
        $nameNode1 = strtolower(basename($node1->getPath()));
        $nameNode2 = strtolower(basename($node2->getPath()));
        return ($nameNode1 > $nameNode2) ? +1 : -1;
    }

    /**
     * @param ilCloudStorageFileNode $node
     * @return array|null
     */
    public function getSortedListOfChildren(ilCloudStorageFileNode $node): ?array
    {
        $children = $node->getChildrenPathes();
        usort($children, array("ilCloudStorageFileTree", "orderListAlphabet"));
        return $children;
    }

    public function getListForJSONEncode(): array
    {
        $list = array();
        foreach ($this->getItemList() as $path => $node) {
            $list[$node->getId()] = $node->getJSONEncode();
        }
        return $list;
    }
}
