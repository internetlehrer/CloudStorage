<?php

declare(strict_types=1);

class ilCloudStorageOwnCloud extends ilCloudStorageGenericService
{
    public const SERVICE_ID = "ocld";

    public const SERVICE_NAME = "OwnCloud";
    
    public const ACCESS_TOKEN_EXPIRATION = "1 hour";

    public const REFRESH_TOKEN_EXPIRES = "6 month";

    public function getServiceId(): string
    {
        return self::SERVICE_ID;
    }

    public function getServiceName(): string
    {
        return self::SERVICE_NAME;
    }

    public static function getDefaultWebDavPath(): string
    {
        return "remote.php/webdav";
    }

    public static function getDefaultOAuth2Path(): string
    {
        return "index.php/apps/oauth2";
    }

    public function hasCollaborationAppSupport(): bool
    {
        return true;
    }

    // for tree only not required
    // maybe required for collaboration app link
    public function getParentIdField(): string
    {
        return "{http://owncloud.org/ns}id";
    }

    public function getFileIdField(): string
    {
        return "{http://owncloud.org/ns}fileid";
    }

    public function folderPropFind(): array {
        return [
            $this->getParentIdField(),
            $this->getFileIdField(),
            '{DAV:}getcontenttype',
            '{DAV:}getcontentlength',
            '{DAV:}getlastmodified',
            '{DAV:}getetag'
        ];
    }

    public function getAccessTokenExpiration(): string
    {
        return "1 hour";
    }

    public function getRefreshTokenExpiration(): string
    {
        return "6 month";
    }
}