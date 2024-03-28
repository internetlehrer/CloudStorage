<?php

/**
 * Class ownclFolder
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageOwnCloudFolder extends ilCloudStorageOwnCloudItem
{

    /**
     * @var int
     */
    protected $type = self::TYPE_FOLDER;
    /**
     * @var int
     */
    protected $child_count = 0;
    /**
     * @var array
     */
    protected $childs;


    /**
     * @return int
     */
    public function getChildCount()
    {
        return $this->child_count;
    }


    /**
     * @param int $child_count
     */
    public function setChildCount($child_count)
    {
        $this->child_count = $child_count;
    }


    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }


    /**
     * @param int $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }


    /**
     * @param array $childs
     */
    public function setChilds($childs)
    {
        $this->childs = $childs;
    }


    /**
     * @return array
     */
    public function getChilds()
    {
        return $this->childs;
    }


    public function addChild($id)
    {
        $this->childs[] = $id;
    }
}
