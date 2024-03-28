<?php

/**
 * Class ilCloudStorageOwnCloudItemFactory
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageOwnCloudItemFactory
{

    /**
     * @param array $response
     *
     * @return ilCloudStorageOwnCloudFolder[]|ilCloudStorageOwnCloudFile[]
     */
    public static function getInstancesFromResponse($response)
    {
        $return = array();
        if (count($response) == 0) {
            return $return;
        }
        $parent = array_shift($response);
        $parent_id = $parent['{http://owncloud.org/ns}id'];
        foreach ($response as $web_url => $props) {
            if (!array_key_exists("{DAV:}getcontenttype", $props)) {//is folder
                $exid_item = new ilCloudStorageOwnCloudFolder();
                $exid_item->loadFromProperties($web_url, $props, $parent_id);
                ilCloudStorageOwnCloudItemCache::store($exid_item);
                $return[] = $exid_item;
            } else { // is file
                $exid_item = new ilCloudStorageOwnCloudFile();
                $exid_item->loadFromProperties($web_url, $props, $parent_id);
                ilCloudStorageOwnCloudItemCache::store($exid_item);
                $return[] = $exid_item;
            }
        }

        return $return;
    }
}
