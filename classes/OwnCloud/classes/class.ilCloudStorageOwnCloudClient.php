<?php

use GuzzleHttp\Exception\GuzzleException;

/**
 * Class ilCloudStorageOwnCloudClient
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageOwnCloudClient
{

    const AUTH_BEARER = 'auth_bearer';

    protected ?ilCloudStorageOwnCloudDAVClient $sabre_client = null;
    
    protected ?ilCloudStorageOwnCloudRESTClient $rest_client = null;
    
    protected ?ilCloudStorageOwnCloud $owncl = null;
   
    const DEBUG = true;

    public function __construct(ilCloudStorageOwnCloud $a_owncl)
    {
        $this->owncl = $a_owncl;
    }

    protected function getWebDAVClient(): ilCloudStorageOwnCloudDAVClient
    {
        if (!$this->sabre_client) {
            $this->sabre_client = new ilCloudStorageOwnCloudDAVClient($this->owncl->getClientSettings());
        }

        return $this->sabre_client;
    }

    protected function getRESTClient(): ilCloudStorageOwnCloudRESTClient
    {
        if (!$this->rest_client) {
            $this->rest_client = new ilCloudStorageOwnCloudRESTClient($this->owncl->config);
        }

        return $this->rest_client;
    }


    public function hasConnection(): bool
    {
        try {   //sabredav version 1.8 throws exception on missing connection
            $response = $this->getWebDAVClient()->request('GET', '', null, $this->owncl->getHeaders());
        } catch (Exception $e) {
            return false;
        }

        return ($response['statusCode'] < 400);
    }

    public function getHTTPStatus(): int
    {
        global $DIC;
        try {
            $response = $this->getWebDAVClient()->request('GET', '', null, $this->owncl->getHeaders());
        } catch (Exception $e) {
            $DIC->logger()->root()->error($e->getMessage());
            throw new ilCloudStorageException(ilCloudStorageException::NO_CONNECTION, $e->getMessage());
            return -1;
        }
        return $response['statusCode'];
    }


    /**
     * @param $id
     *
     * @return ilCloudStorageOwnCloudFile[]|ilCloudStorageOwnCloudFolder[]
     */
    public function listFolder($id)
    {
        global $ilLog;
        $id = $this->urlencode(ltrim($id, '/'));
        //$ilLog->write('listFolder: ' . $id);

        $settings = $this->owncl->getClientSettings();
        if ($client = $this->getWebDAVClient()) {
            //$ilLog->write('listFolder: ' . $settings['baseUri'] . $id);

            $response = $client->propFind(
                $settings['baseUri'] . $id,
                [
                    '{http://owncloud.org/ns}id',
                    '{http://owncloud.org/ns}fileid',
                    '{DAV:}getcontenttype',
                    '{DAV:}getcontentlength',
                    '{DAV:}getlastmodified',
                    '{DAV:}getetag'
                ],
                1,
                $this->owncl->getHeaders()
            );
            // $response = $client->propFind($settings['baseUri'] . $id, [], 1, $this->getAuth()->getHeaders());
            $items = ilCloudStorageOwnCloudItemFactory::getInstancesFromResponse($response);

            return $items;
        }

        return array();
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function folderExists($path)
    {
        return $this->itemExists($path);
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function fileExists($path)
    {
        return $this->itemExists($path);
    }

    public function deliverFile(string $path): void
    {
        $path = ltrim($path, "/");
        $encoded_path = $this->urlencode($path);
        $headers = $this->owncl->getHeaders();
        $settings = $this->owncl->getClientSettings();
        $arr = $this->getWebDAVClient()->propFind($settings['baseUri'] . $encoded_path, array(), 1, $headers);
        $prop = array_shift($arr);
        header("Content-type: " . $prop['{DAV:}getcontenttype']);
        header("Content-Length: " . $prop['{DAV:}getcontentlength']);
        header("Connection: close");
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        set_time_limit(0);
        $opts = array(
            'http' => array(
                'protocol_version' => 1.1,
                'method' => "GET",
                'header' => "Authorization: " . $headers['Authorization']
            )
        );
        $context = stream_context_create($opts);
        $file = fopen($settings['baseUri'] . $encoded_path, "rb", false, $context);
        fpassthru($file);
        exit;
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function createFolder($path): bool
    {
        $path = $this->urlencode($path);
        $response = $this->getWebDAVClient()->request('MKCOL', ltrim($path, '/'), null, $this->owncl->getHeaders());
        if (self::DEBUG) {
            global $log;
            $log->write("[ownclClient]->createFolder({$path}) | response status Code: {$response['statusCode']}");
        }

        return ($response['statusCode'] == 200);
    }


    /**
     * urlencode without encoding slashes
     *
     * @param $str
     *
     * @return mixed
     */
    protected function urlencode($str)
    {
        return str_replace('%2F', '/', rawurlencode($str));
    }


    /**
     * @param $location
     * @param $local_file_path
     *
     * @return bool
     * @throws ilCloudException
     */
    public function uploadFile($location, $local_file_path)
    {
        $location = $this->urlencode(ltrim($location, '/'));
        if ($this->fileExists($location)) {
            $basename = pathinfo($location, PATHINFO_FILENAME);
            $extension = pathinfo($location, PATHINFO_EXTENSION);
            $i = 1;
            while ($this->fileExists($basename . "({$i})." . $extension)) {
                $i++;
            }
            $location = $basename . "({$i})." . $extension;
        }
        $response = $this->getWebDAVClient()->request('PUT', $location, file_get_contents($local_file_path), $this->owncl->getHeaders());
        if (self::DEBUG) {
            global $log;
            $log->write("[ownclClient]->uploadFile({$location}, {$local_file_path}) | response status Code: {$response['statusCode']}");
        }

        return ($response['statusCode'] == 200);
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $response = $this->getWebDAVClient()->request('DELETE', ltrim($this->urlencode($path), '/'), null, $this->owncl->getHeaders());
        if (self::DEBUG) {
            global $log;
            $log->write("[ownclClient]->delete({$path}) | response status Code: {$response['statusCode']}");
        }

        return ($response['statusCode'] == 200);
    }


    /**
     * @param $path
     *
     * @return bool
     */
    protected function itemExists($path)
    {
        try {
            $request = $this->getWebDAVClient()->request('GET', ltrim($this->urlencode($path), '/'), null, $this->owncl->getHeaders());
        } catch (Exception $e) {
            return false;
        }

        return ($request['statusCode'] < 400);
    }


    /**
     * (re)initialize the client with settings from the owncloud object
     */
    public function loadClient()
    {
        $this->sabre_client = new ilCloudStorageOwnCloudDAVClient($this->owncl->getClientSettings());
    }


    /**
     * @param string    $path
     * @param ilObjUser $user
     *
     * @throws ilCloudPluginConfigException
     * @throws GuzzleException
     */
    public function shareItem($path, $user)
    {
        if ($user->getId() == $this->owncl->object->getOwnerId()) {
            // no need to share with yourself (can result in an error with nextcloud)
            return;
        }
        $user_string = $this->owncl->config->getMappingValueForUser($user);
        $shareAPI = $this->getRESTClient()->shareAPI($this->owncl);
        $existing = $shareAPI->getForPath($path);
        foreach ($existing as $share) {
            if ($share->getShareWith() === $user_string) {
                if (!$share->hasPermission(ilCloudStorageOwnCloudShareAPI::PERM_TYPE_UPDATE)) {
                    $shareAPI->update($share->getId(), $share->getPermissions() | (ilCloudStorageOwnCloudShareAPI::PERM_TYPE_UPDATE + ilCloudStorageOwnCloudShareAPI::PERM_TYPE_READ));
                }
                return;
            }
        }
        $shareAPI->create($path, $user_string, ilCloudStorageOwnCloudShareAPI::PERM_TYPE_UPDATE + ilCloudStorageOwnCloudShareAPI::PERM_TYPE_READ);
    }


    /**
     * @param string $path
     *
     * @return string
     */
    public function pathToId(string $path) : string
    {
        $settings = $this->owncl->getClientSettings();

        $client = $this->getWebDAVClient();

        $response = $client->propFind(
            $settings['baseUri'] . $this->urlencode($path),
            [
                '{http://owncloud.org/ns}fileid'
            ],
            0,
            $this->owncl->getHeaders()
        );

        $id = strval(current($response));

        return $id;
    }
}
