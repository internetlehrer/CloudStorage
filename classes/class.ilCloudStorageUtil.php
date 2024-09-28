<?php

declare(strict_types=1);

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilCloudStorageUtil
 *
 * Some utility function, mostly for path handling.
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 * @version $Id$
 *
 */
class ilCloudStorageUtil
{
    const IV = '6459219348742938';

    public static function normalizePath(string $path): string
    {
        if ($path == "." || $path == "/" || $path == "") {
            $path = "/";
        } else {
            $path = "/" . rtrim(ltrim(str_replace('//', '/', $path), "/"), "/");
        }

        return $path;
    }

    public static function joinPaths(string $path1, string $path2): string
    {
        $path1 = ilCloudStorageUtil::normalizePath($path1);
        $path2 = ilCloudStorageUtil::normalizePath($path2);

        return ilCloudStorageUtil::normalizePath(str_replace('//', '/', $path1 . $path2));
    }

    public static function joinPathsAbsolute(string $path1, string $path2): string
    {
        $path = ilCloudStorageUtil::normalizePath(str_replace('//', '/', $path1 . $path2));
        if ($path == "/") {
            return $path;
        } else {
            return "/" . ltrim($path, "/") . "/";
        }
    }

    public static function encodeBase64Path(string $path): string 
    {
        return str_replace("=", "Abase64Z", base64_encode(rawurlencode($path)));
    }

    public static function decodeBase64Path(string $path): string 
    {
        return rawurldecode(base64_decode(str_replace("Abase64Z", "=", $path)));
    }

    public static function validatePath(string $path): bool 
    {
        return preg_match('/^\/.*/', $path);
    }

    public static function encrypt(string $data): string {
        global $DIC;
        try {
            if ($data == '') {
                return '';
            }
            $key = self::getSalt();
            if ($key == "") {
                return "";
            }
            return openssl_encrypt($data, 'aes-256-cbc', $key, 0, self::IV);
        } catch(Exception $e) {
            $DIC->logger()->root()->error($e->getMessage());
            return "";
        }
    }

    public static function decrypt(string $data): string {
        global $DIC;
        try {
            if ($data == '') {
                return '';
            }
            $key = self::getSalt();
            return openssl_decrypt($data, 'aes-256-cbc', $key, 0, self::IV);
        } catch(Exception $e) {
            $DIC->logger()->root()->error($e->getMessage());
            return "";
        }
    }

    public static function getSalt(): string {
        global $DIC;
        try {
            $bcrypt = new ilBcryptPasswordEncoder(["data_directory" => ilFileUtils::getDataDir()]);
            //$bcrypt->setDataDirectory(ilFileUtils::getDataDir());
            return $bcrypt->getClientSalt();
        } catch(Exception $e) {
            $DIC->logger()->root()->error($e->getMessage());
            return "";
        }
    }

    public static function getStringParam(string $param): ?string {
        global $DIC;
        return ($DIC->http()->wrapper()->query()->has($param)) ? $DIC->http()->wrapper()->query()->retrieve($param, $DIC->refinery()->kindlyTo()->string()): '';
    }

    public static function getIntParam(string $param): ?int {
        global $DIC;
        return ($DIC->http()->wrapper()->query()->has($param)) ? $DIC->http()->wrapper()->query()->retrieve($param, $DIC->refinery()->kindlyTo()->int()): -1;
    }

    public static function getStringPost(string $param): ?string {
        global $DIC;
        return ($DIC->http()->wrapper()->post()->has($param)) ? $DIC->http()->wrapper()->post()->retrieve($param, $DIC->refinery()->kindlyTo()->string()): '';
    }

    public static function getIntPost(string $param): ?int {
        global $DIC;
        return ($DIC->http()->wrapper()->post()->has($param)) ? $DIC->http()->wrapper()->post()->retrieve($param, $DIC->refinery()->kindlyTo()->int()): -1;
    }
}
