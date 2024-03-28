<?php

/**
 * Class ilCloudStorageOwnCloudItemCache
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageOwnCloudItemCache
{

    const ITEM_CACHE = 'owncl_item_cache';
    /**
     * @var array
     */
    protected static $instances = array();


    /**
     * @param ilCloudStorageOwnCloudItem $ownclItem
     */
    public static function store(ilCloudStorageOwnCloudItem $ownclItem)
    {
        $_SESSION[self::ITEM_CACHE][$ownclItem->getId()] = serialize($ownclItem);
    }


    /**
     * @param $id
     *
     * @return bool
     */
    public static function exists($id)
    {
        return (unserialize($_SESSION[self::ITEM_CACHE][$id]) instanceof ilCloudStorageOwnCloudItem);
    }


    /**
     * @param $id
     *
     * @return ilCloudStorageOwnCloudItem
     */
    public static function get($id)
    {
        if (self::exists($id)) {
            return unserialize($_SESSION[self::ITEM_CACHE][$id]);
        }

        return null;
    }
}