<?php

declare(strict_types=1);

/**
 * Class ilCloudStorageWebDavFile
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageFile extends ilCloudStorageItem
{

    protected int $type = self::TYPE_FILE;

    protected int $size = 0;
    
    protected string $content_url = '';

    public function loadFromProperties(string $parent_web_url, string $web_url, array $properties, ilCloudStorageGenericService $service): void
    {
        parent::loadFromProperties($parent_web_url, $web_url, $properties, $service);
        $this->setSize((int) $properties["{DAV:}getcontentlength"]);
    }

    public function loadFromResponse(string $path): void
    {
        $this->setName(substr($path, strrpos($path, '/')));
        $this->setContentUrl($path);
    }

    /**
     * @return array|string
     */
    public function getSuffix()
    {
        return pathinfo($this->getName(), PATHINFO_EXTENSION);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getContentUrl(): string
    {
        return $this->content_url;
    }

    public function setContentUrl(string $content_url): void
    {
        $this->content_url = $content_url;
    }
}