<?php

/**
 * Class ilCloudStorageOwnCloudShare
 *
 * @author Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageOwnCloudShare
{

    /**
     * @var int
     */
    protected $id;
    /**
     * @var int
     */
    protected $share_type;
    /**
     * @var string
     */
    protected $uid_owner;
    /**
     * @var string
     */
    protected $displayname_owner;
    /**
     * @var int
     */
    protected $permissions;
    /**
     * @var int
     */
    protected $stime;
    /**
     * @var string
     */
    protected $parent;
    /**
     * @var
     */
    protected $expiration;
    /**
     * @var string
     */
    protected $token;
    /**
     * @var string
     */
    protected $uid_file_owner;
    /**
     * @var string
     */
    protected $displayname_file_owner;
    /**
     * @var string
     */
    protected $path;
    /**
     * @var string
     */
    protected $item_type;
    /**
     * @var string
     */
    protected $mimetype;
    /**
     * @var string
     */
    protected $storage_id;
    /**
     * @var int
     */
    protected $storage;
    /**
     * @var int
     */
    protected $item_source;
    /**
     * @var int
     */
    protected $file_source;
    /**
     * @var int
     */
    protected $file_parent;
    /**
     * @var string
     */
    protected $file_target;
    /**
     * @var string
     */
    protected $share_with;
    /**
     * @var string
     */
    protected $share_with_displayname;
    /**
     * @var
     */
    protected $share_with_additional_info;
    /**
     * @var int
     */
    protected $mail_send;
    /**
     * @var
     */
    protected $attributes;


    /**
     * @param stdClass $std_class
     *
     * @return ilCloudStorageOwnCloudShare
     */
    public static function loadFromStdClass(stdClass $std_class) : ilCloudStorageOwnCloudShare
    {
        return self::loadFromArray((array) $std_class);
    }


    /**
     * @param array $array
     *
     * @return ilCloudStorageOwnCloudShare
     */
    public static function loadFromArray(array $array) : ilCloudStorageOwnCloudShare
    {
        $new = new self();
        foreach ($array as $key => $value) {
            $new->{$key} = $value;
        }
        return $new;
    }


    /**
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }


    /**
     * @return int
     */
    public function getShareType() : int
    {
        return $this->share_type;
    }


    /**
     * @return string
     */
    public function getUidOwner() : string
    {
        return $this->uid_owner;
    }


    /**
     * @return string
     */
    public function getDisplaynameOwner() : string
    {
        return $this->displayname_owner;
    }


    /**
     * @param int $permission
     *
     * @return bool
     */
    public function hasPermission(int $permission) : bool
    {
    	return $this->permissions & $permission;
    }

    /**
     * @return int
     */
    public function getPermissions() : int
    {
        return $this->permissions;
    }


    /**
     * @return int
     */
    public function getStime() : int
    {
        return $this->stime;
    }


    /**
     * @return string
     */
    public function getParent() : string
    {
        return $this->parent;
    }


    /**
     * @return mixed
     */
    public function getExpiration()
    {
        return $this->expiration;
    }


    /**
     * @return string
     */
    public function getToken() : string
    {
        return $this->token;
    }


    /**
     * @return string
     */
    public function getUidFileOwner() : string
    {
        return $this->uid_file_owner;
    }


    /**
     * @return string
     */
    public function getDisplaynameFileOwner() : string
    {
        return $this->displayname_file_owner;
    }


    /**
     * @return string
     */
    public function getPath() : string
    {
        return $this->path;
    }


    /**
     * @return string
     */
    public function getItemType() : string
    {
        return $this->item_type;
    }


    /**
     * @return string
     */
    public function getMimetype() : string
    {
        return $this->mimetype;
    }


    /**
     * @return string
     */
    public function getStorageId() : string
    {
        return $this->storage_id;
    }


    /**
     * @return int
     */
    public function getStorage() : int
    {
        return $this->storage;
    }


    /**
     * @return int
     */
    public function getItemSource() : int
    {
        return $this->item_source;
    }


    /**
     * @return int
     */
    public function getFileSource() : int
    {
        return $this->file_source;
    }


    /**
     * @return int
     */
    public function getFileParent() : int
    {
        return $this->file_parent;
    }


    /**
     * @return string
     */
    public function getFileTarget() : string
    {
        return $this->file_target;
    }


    /**
     * @return string
     */
    public function getShareWith() : string
    {
        return $this->share_with;
    }


    /**
     * @return string
     */
    public function getShareWithDisplayname() : string
    {
        return $this->share_with_displayname;
    }


    /**
     * @return mixed
     */
    public function getShareWithAdditionalInfo()
    {
        return $this->share_with_additional_info;
    }


    /**
     * @return int
     */
    public function getMailSend() : int
    {
        return $this->mail_send;
    }


    /**
     * @return mixed
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
}