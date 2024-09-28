<?php

declare(strict_types=1);

use Sabre\DAV\Client;
use Sabre\HTTP;
use Sabre\Xml\Service;

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilCloudStorageOAuth2
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 */
class ilCloudStorageBasicAuth
{

    const DB_TABLE_NAME = 'rep_robj_xcls_bauth';
   
    private ?int $conn_id = 0;

    private ?int $user_id = 0;
    
    private ?string $username = '';

    private ?string $password = '';

    // object model
    public function setConnId(int $conn_id): void
    {
        $this->conn_id = $conn_id;
    }

    public function getConnId(): int
    {
        return $this->conn_id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    // object functions
    private function store(): void {
        global $DIC;
        $query = $DIC->database()->query("SELECT user_id FROM " . self::DB_TABLE_NAME . " WHERE conn_id = " . $this->getConnId() . " AND user_id = " . $this->getUserId());
        $ret = $DIC->database()->fetchAssoc($query);
        if (!is_null($ret)) {
            $DIC->database()->manipulateF(
                'UPDATE ' . self::DB_TABLE_NAME . ' SET username = %s, password = %s WHERE conn_id = %s AND user_id = %s',
                array('text', 'text', 'integer', 'integer'),
                array($this->getUsername(), $this->getPassword(), $this->getConnId(), $this->getUserId())
            );
        } else {
            $DIC->database()->manipulateF(
                'INSERT INTO ' . self::DB_TABLE_NAME . ' (conn_id, user_id, username, password) VALUES (%s, %s, %s, %s)',
                array('integer', 'integer', 'text', 'text'),
                array($this->getConnId(), $this->getUserId(), $this->getUsername(), $this->getPassword())
            );
        }
    }
    
    public function storeUserAccount(string $username, string $password, int $conn_id)
    {
        $this->setConnId($conn_id);
        $this->setUsername($username);
        $this->setPassword($password);
        $this->store();
    }

    public static function getUserAccount(int $conn_id, int $user_id = 0): ilCloudStorageBasicAuth
    {
        global $DIC;
        if ($user_id == 0) {
            global $ilUser;
            $user_id = $ilUser->getId();
        }
        $query = $DIC->database()->query("SELECT * FROM " . self::DB_TABLE_NAME . " WHERE conn_id = " . $conn_id . " AND user_id = " . $user_id);
        $ret = $DIC->database()->fetchAssoc($query);
        if (is_null($ret)) {
            $account = new self();
            $account->setConnId($conn_id);
            $account->setUserId($user_id);
        } else {
            $account = new self();
            $account->setConnId($conn_id);
            $account->setUserId($user_id);
            $account->setUsername($ret['username']);
            $account->setPassword($ret['password']);
        }
        return $account;
    }

    public static function deleteUserAccount(int $conn_id, int $user_id = 0): void
    {
        global $DIC;
        if ($user_id == 0) {
            global $ilUser;
            $user_id = $ilUser->getId();
        }
        $DIC->database()->manipulate("DELETE FROM " . self::DB_TABLE_NAME . " WHERE conn_id = " . $conn_id . " AND user_id = " . $user_id);
    }

    // specific basic auth functions

    public static function getHeaders(int $conn_id, int $user_id): array
    {
        $account = self::getUserAccount($conn_id, $user_id);
        return array(
            'Authorization' => 'Basic ' . base64_encode($account->getUsername() . ':' . ilCloudStorageUtil::decrypt($account->getPassword()))
        );
    }

    // generic auth connection functions
    public static function checkConnection(int $conn_id, int $user_id, ilCloudStorageConfig $config): void
    {
        $status = self::getHTTPStatus($conn_id, $user_id, $config);
        
        // unauthorized
        if ($status == 401) {
            throw new ilCloudStorageException(ilCloudStorageException::NOT_AUTHORIZED);
        }

        // everything else
        if ($status > 401) {
            throw new ilCloudStorageException(ilCloudStorageException::NO_CONNECTION);
        }
    }

    public static function getHTTPStatus(int $conn_id, int $user_id, ilCloudStorageConfig $config): int
    {
        global $DIC;
        try {
            $client = new Client(self::getClientSettings($config));
            $response = $client->request('PROPFIND', '', null, self::getHeaders($conn_id, $user_id));
        } catch (Exception $e) {
            $DIC->logger()->root()->error($e->getMessage());
            throw new ilCloudStorageException(ilCloudStorageException::NO_CONNECTION, $e->getMessage());
            return -1;
        }
        return $response['statusCode'];
    }

    public static function getClientSettings(ilCloudStorageConfig $config): array
    {
        if ($config->getProxyURL() != '') {
            return array(
                'baseUri' => $config->getFullWebDAVPath(),
                'webDavPath' => $config->getWebDavPath(),
                'proxy'   => $config->getProxyURL(),
            );
        } else {
            return array(
                'baseUri' => $config->getFullWebDAVPath(),
                'webDavPath' => $config->getWebDavPath(),
            );
        }
    }
    
}