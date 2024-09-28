<?php

declare(strict_types=1);

/**
 * Class swdrTree
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */

class ilCloudStorageTree
{    
    public ?ilCloudStorageGenericService $service;

    function __construct(ilCloudStorageGenericService $service)
    {
        $this->service = $service;
    }

    public function getChilds($id, string $a_order = "", string $a_direction = "ASC"): array
    {
        return $this->service->listFolder(ilCloudStorageUtil::decodeBase64Path($id));
    }

    function getRootNode()
    {
        $root = new ilCloudStorageFolder();
        $root->setName('');
        $root->setPath('/');
        return $root;
    }
}