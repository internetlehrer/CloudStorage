<?php

declare(strict_types=1);

/**
 * Class davFolder
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageFolder extends ilCloudStorageItem
{

    protected int $type = self::TYPE_FOLDER;
    
    protected int $child_count = 0;
    
    protected array $childs = array();

    public function getChildCount(): int
    {
        return $this->child_count;
    }

    public function setChildCount(int $child_count): void
    {
        $this->child_count = $child_count;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
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
