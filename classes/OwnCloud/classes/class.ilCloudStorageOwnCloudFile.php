<?php

/**
 * Class ilCloudStorageOwnCloudFile
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageOwnCloudFile extends ilCloudStorageOwnCloudItem
{

    /**
     * @var int
     */
    protected $type = self::TYPE_FILE;
    /**
     * @var int
     */
    protected $size = 0;
    /**
     * @var string
     */
    protected $content_url = '';


    /**
     * @param $web_url    String
     * @param $properties array
     */
    public function loadFromProperties($web_url, $properties, $parent_id)
    {
        parent::loadFromProperties($web_url, $properties, $parent_id);
        $this->setSize($properties["{DAV:}getcontentlength"]);
    }


    /**
     * @param $web_url    String
     * @param $properties array
     */
    public function loadFromResponse($response, $path)
    {
        $this->setName(substr($path, strrpos($path, '/')));
        $this->setContentUrl($path);
    }


    /**
     * @return mixed
     */
    public function getSuffix()
    {
        return pathinfo($this->getName(), PATHINFO_EXTENSION);
    }


    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }


    /**
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }


    /**
     * @return string
     */
    public function getContentUrl()
    {
        return $this->content_url;
    }


    /**
     * @param string $content_url
     */
    public function setContentUrl($content_url)
    {
        $this->content_url = $content_url;
    }
}