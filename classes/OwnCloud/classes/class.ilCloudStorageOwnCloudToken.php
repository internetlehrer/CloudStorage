<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use League\OAuth2\Client\Token\AccessToken;

/**
 * Class ilCloudStorageOwnCloudToken
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 */
class ilCloudStorageOwnCloudToken
{

    const DB_TABLE_NAME = 'rep_robj_xcls_ocld_tk';
   
    private ?int $conn_id = 0;

    private ?int $user_id = 0;
    
    private ?string $access_token = '';

    private ?string $refresh_token = '';
    
    private ?int $valid_through = 0;

    private function store(): void {
        global $DIC;
        $query = $DIC->database()->query("SELECT user_id FROM rep_robj_xcls_ocld_tk WHERE conn_id = " . $this->getConnId() . " AND user_id = " . $this->getUserId());
        $ret = $DIC->database()->fetchAssoc($query);
        if (!is_null($ret)) {
            $DIC->database()->manipulateF(
                'UPDATE rep_robj_xcls_ocld_tk SET access_token = %s, refresh_token = %s, valid_through = %s WHERE conn_id = %s AND user_id = %s',
                array('text', 'text', 'integer', 'integer', 'integer'),
                array($this->getAccessToken(), $this->getRefreshToken(), $this->getValidThrough(), $this->getConnId(), $this->getUserId())
            );
        } else {
            $DIC->database()->manipulateF(
                'INSERT INTO rep_robj_xcls_ocld_tk (conn_id, user_id, access_token, refresh_token, valid_through) VALUES (%s, %s, %s, %s, %s)',
                array('integer', 'integer', 'text', 'text', 'integer'),
                array($this->getConnId(), $this->getUserId(), $this->getAccessToken(), $this->getRefreshToken(), $this->getValidThrough())
            );
        }
    }
    
    public function storeUserToken(League\OAuth2\Client\Token\AccessToken $token, int $conn_id)
    {
        $this->setConnId($conn_id);
        $this->setAccessToken($token->getToken());
        $this->setRefreshToken($token->getRefreshToken());
        $this->setValidThrough($token->getExpires());
        $this->store();
    }

    public static function getUserToken(int $conn_id, int $user_id = 0): ilCloudStorageOwnCloudToken
    {
        global $DIC;
        if ($user_id == 0) {
            global $ilUser;
            $user_id = $ilUser->getId();
        }
        $query = $DIC->database()->query("SELECT * FROM rep_robj_xcls_ocld_tk WHERE conn_id = " . $conn_id . " AND user_id = " . $user_id);
        $ret = $DIC->database()->fetchAssoc($query);
        if (is_null($ret)) {
            $token = new self();
            $token->setConnId($conn_id);
            $token->setUserId($user_id);
        } else {
            $token = new self();
            $token->setConnId($conn_id);
            $token->setUserId($user_id);
            $token->setAccessToken($ret['access_token']);
            $token->setRefreshToken($ret['refresh_token']);
            $token->setValidThrough($ret['valid_through']);
        }
        return $token;
    }

    public static function deleteUserToken(int $conn_id, int $user_id = 0): void
    {
        global $DIC;
        if ($user_id == 0) {
            global $ilUser;
            $user_id = $ilUser->getId();
        }
        $DIC->database()->manipulate("DELETE FROM rep_robj_xcls_ocld_tk WHERE conn_id = " . $conn_id . " AND user_id = " . $user_id);
    }

    /* never used
    public function flushTokens()
    {
        if ($this->getAccessToken() || $this->getRefreshToken() || $this->getValidThrough()) {
            $this->setAccessToken('');
            $this->setRefreshToken('');
            $this->setValidThrough(0);
            $this->store();
        }
    }
    */

    public function isExpired(): bool
    {
        return ((int) $this->getValidThrough() != 0) && ($this->getValidThrough() <= time());
    }

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

    public function getAccessToken(): string
    {
        return $this->access_token;
    }

    public function setAccessToken(string $access_token): void
    {
        $this->access_token = $access_token;
    }

    public function getRefreshToken(): string
    {
        return $this->refresh_token;
    }

    public function setRefreshToken(string $refresh_token): void
    {
        $this->refresh_token = $refresh_token;
    }

    public function getValidThrough(): int
    {
        return $this->valid_through;
    }

    public function setValidThrough(int $valid_through): void
    {
        $this->valid_through = $valid_through;
    }

}