<?php

declare(strict_types=1);

/**
 * Class davItem
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
abstract class ilCloudStorageItem
{

    const TYPE_UNKNOWN = -1;
    const TYPE_FOLDER = 1;
    const TYPE_FILE = 2;
    
    protected int $parent_id = ilCloudStorageFileNode::ID_UNKNOWN;
    
    protected int $id = ilCloudStorageFileNode::ID_UNKNOWN;
    
    protected string $path = '';

    protected int $type = self::TYPE_UNKNOWN;

    protected string $web_url = '';

    protected string $date_time_created = '';

    protected string $date_time_last_modified = '';

    protected string $name = '';
    
    protected string $last_modified_by = '';
    
    protected string $e_tag = '';

    /*
    public function loadFromProperties(string $web_url, array $properties, int $parent_id, int $id, array $settings): void
    {

        $web_url = rawurldecode($web_url);
        $web_dav_path = $settings['webDavPath'];
        $this->setId($id);
        $this->setParentId($parent_id);
        $this->setWebUrl($web_url);
        if ($this->getType() == self::TYPE_FOLDER) {
            $web_url = substr($web_url, 0, -1);
        }
        $this->setName(substr($web_url, strrpos($web_url, '/') + 1, strlen($web_url) - strrpos($web_url, '/')));
        $web_url = substr($web_url, 0, -(strlen($this->getName())));
        $this->setPath(substr($web_url, strpos($web_url, $web_dav_path) + strlen($web_dav_path)));
        //$this->setPath($web_url);
        $this->setDateTimeLastModified($properties["{DAV:}getlastmodified"]);
        //$this->setETag($properties["{DAV:}getetag"]);
    }
    */
    
    public function loadFromProperties(string $parent_web_url, string $web_url, array $properties, ilCloudStorageGenericService $service): void
    {
        $url = $service->getDecodedWebUrl($web_url);
        
        $this->setWebUrl($url);

        // path is the directory path component of the ressource dir_name/ not the full path to the ressource!
        $path = $service->getPathFromWebUrl($web_url, $this->getType());        
        $this->setPath($path);

        // name is the name component of the ressource dir_name/ not the full path to the ressource!
        $name = $service->getNameFromWebUrl($web_url, $this->getType());

        $this->setName($name);

        if ($service->hasParentId()) {
            $this->setParentId((int) $properties[$service->getParentIdField()]);
        }

        if ($service->hasFileId()) {
            $this->setId((int) $properties[$service->getFileIdField()]);
        }

        $this->setDateTimeLastModified($properties["{DAV:}getlastmodified"]);

        if (isset($properties["{DAV:}getetag"])) {
            $this->setETag($properties["{DAV:}getetag"]);
        }
    }

    public function getFullPath(): string
    {
        $path = '';
        if ($this->getPath() AND $this->getPath() != '/') {
            $path = $this->getPath();
        }

        return rtrim($path,"/") . '/' . $this->getName();
    }

    public function getEncodedFullPath(): string
    {
        return $this->urlencode($this->getFullPath());
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getParentId(): int
    {
        return $this->parent_id;
    }

    public function setParentId(int $parent_id): void
    {
        $this->parent_id = $parent_id;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getWebUrl(): string
    {
        return $this->web_url;
    }

    public function setWebUrl(string $web_url): void
    {
        $this->web_url = $web_url;
    }
    
    public function getDateTimeCreated(): string
    {
        return $this->date_time_created;
    }

    public function setDateTimeCreated(string $date_time_created): void
    {
        $this->date_time_created = $date_time_created;
    }

    public function getDateTimeLastModified(): string
    {
        return $this->date_time_last_modified;
    }

    public function setDateTimeLastModified(string $date_time_last_modified): void
    {
        $this->date_time_last_modified = $date_time_last_modified;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLastModifiedBy(): string
    {
        return $this->last_modified_by;
    }

    public function setLastModifiedBy(string $last_modified_by): void
    {
        $this->last_modified_by = $last_modified_by;
    }

    public function getETag(): string
    {
        return $this->e_tag;
    }

    public function setETag(string $e_tag): void
    {
        $this->e_tag = $e_tag;
    }

    public static function toCamelCase(string $str, bool $capitalise_first_char = false): string
    {
        if ($capitalise_first_char) {
            $str[0] = strtoupper($str[0]);
        }
        //$func = create_function('$c', 'return strtoupper($c[1]);');
        $func = function($c) { 
            return strtoupper($c[1]); 
        };

        return preg_replace_callback('/_([a-z])/', $func, $str);
    }

    protected static function fromCamelCase(string $str): string
    {
        $str[0] = strtolower($str[0]);
        //$func = create_function('$c', 'return "_" . strtolower($c[1]);');
        $func = function($c) {
            return "_" . strtolower($c[1]);
        };
        
        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

    protected function urlencode(string $str): string
    {
        return str_replace('%2F', '/', rawurlencode($str));
    }
}