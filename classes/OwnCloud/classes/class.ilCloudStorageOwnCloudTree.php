<?php

/**
 * Class swdrTree
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */

class ilCloudStorageOwnCloudTree
{    
    public $client;

    function __construct(ilCloudStorageOwnCloudClient $client)
    {
        $this->client = $client;
    }

    public function getChilds($id, string $a_order = "", string $a_direction = "ASC"): array
    {
        return $this->client->listFolder(ilCloudStorageUtil::decodeBase64Path($id));
    }

    function getRootNode()
    {
        $root = new ilCloudStorageOwnCloudFolder();
        $root->setName('');
        $root->setPath('/');

        return $root;
    }
}