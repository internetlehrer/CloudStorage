<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * ilCloudStorageFileNode class
 *
 * Representation of a node (a file or a folder) in the file tree
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 * @version $Id$
 */
class ilCloudStorageFileNode
{

    protected int $id = 0;
    
    protected string $path = "";
    
    protected int $parent_id = -1;
    
    protected array $children = [];
    
    protected bool $loading_complete = false;
    
    protected bool $is_dir = false;
    
    protected int $size = 0;
    
    protected int $modified = 0;
    
    protected int $created = 0;
    
    protected string $icon_path = "";
    
    protected $mixed;

    public function __construct(string $path, string $id)
    {
        $this->setPath($path);
        $this->setId($id);
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setLoadingComplete(bool $complete): void
    {
        $this->loading_complete = $complete;
    }

    public function getLoadingComplete(): bool
    {
        return $this->loading_complete;
    }

    public function setPath(string $path = "/")
    {
        $this->path = ilCloudStorageUtil::normalizePath($path, $this->is_dir);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function addChild(string $path): void
    {
        if (!isset($this->children[$path])) {
            $this->children[$path] = $path;
        }
    }

    public function removeChild(string $path): void
    {
        if (isset($this->children[$path])) {
            unset($this->children[$path]);
        }
    }

    public function getChildrenPathes(): ?array
    {
        if ($this->hasChildren()) {
            return $this->children;
        }
        return null;
    }

    public function hasChildren(): bool
    {
        return (count($this->children) > 0);
    }

    public function setParentId(int $id): void
    {
        $this->parent_id = $id;
    }

    public function getParentId(): int
    {
        return $this->parent_id;
    }

    public function setIsDir(bool $is_dir): void
    {
        $this->is_dir = $is_dir;
    }

    public function getIsDir(): bool
    {
        return $this->is_dir;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setModified(int $modified): void
    {
        $this->modified = $modified;
    }

    public function getModified(): int
    {
        return $this->modified;
    }

    public function setIconPath(string $path): void
    {
        $this->icon_path = $path;
    }

    public function getIconPath(): string
    {
        return $this->icon_path;
    }

    public function setMixed($mixed)
    {
        $this->mixed = $mixed;
    }

    public function getMixed()
    {
        return $this->mixed;
    }

    public function getJSONEncode(): array
    {
        $node = array();
        $node["id"] = $this->getId();
        $node["is_dir"] = $this->getIsDir();
        $node["path"] = $this->getPath();
        $node["parent_id"] = $this->getParentId();
        $node["loading_complete"] = $this->getLoadingComplete();
        $node["children"] = $this->getChildrenPathes();
        $node["size"] = $this->getSize();

        return $node;
    }
}
