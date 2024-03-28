<?php

// from class.ilCloudPluginService.php

interface ilCloudStorageServiceInterface
{

    public function getServiceName(): string;
    
    public static function getDefaultCollaborationAppFormats(): string;

    public static function getDefaultWebDavPath(): string;

    public static function getDefaultOAuth2Path(): string;
    
    public function authService(string $callback_url = ""): void;

    public function afterAuthService(): void;

    public function checkConnection(): void;

    public function checkAndRefreshAuthentication(): bool;

    public function getRootId(string $root_path): string;

    public function addToFileTree(ilCloudStorageFileTree $file_tree, string $parent_folder = "/"): void;
   
    public function addToFileTreeById(ilCloudStorageFileTree $file_tree, $id): bool;
    
    public function getFile(string $path = "", ?ilCloudStorageFileTree $file_tree = null): void;

    public function getFileById(string $id): bool;
    
    public function createFolder(string $path = "", ?ilCloudStorageFileTree $file_tree = null): void;

    public function createFolderById(string $parent_id, string $folder_name): string;
    
    public function putFile(string $tmp_name, string $file_name, string $path = '', ?ilCloudStorageFileTree $file_tree = null): void;

    public function putFileById($tmp_name, $file_name, $id): bool;

    public function deleteItem(string $path = "", ?ilCloudStorageFileTree $file_tree = null): void;

    public function deleteItemById(string $id): bool;

    public function isCaseSensitive(): bool;
    
}
