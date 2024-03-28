<?php
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
}
