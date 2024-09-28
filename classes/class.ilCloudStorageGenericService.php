<?php

declare(strict_types=1);

use ILIAS\DI\Container;

use \League\OAuth2\Client\Provider\GenericProvider;

use Sabre\DAV\Client;
use Sabre\HTTP;
use Sabre\Xml\Service;

//Sn: the authService and afterAuth* functions into serviceGUI and interface

abstract class ilCloudStorageGenericService extends Client
{   
    public const DEBUG = true;

    public const INI_FILENAME = 'plugin';
    
    public const PLUGIN_PATH = './Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage';

    public const SESSION_CALLBACK_URL = 'callback_url';
    
    public const SESSION_AUTH_BEARER = 'access_token';

    private const DEFAULT_COLLABORATION_APP_FORMATS = 'xls,xlsx,doc,docx,dot,dotx,odt,ott,rtf,txt,pdf,pdfa,html,epub,xps,djvu,djv,ppt,pptx';

    public const DEFAULT_WEBDAV_PATH = '/';

    public const DEFAULT_OAUTH2_PATH = '/';

    public const AUTH_BEARER = 'auth_bearer'; // ToDo!

    public ?ilObjCloudStorage $object = null;

    public ?ilCloudStorageConfig $config = null;

    public ?Container $dic = null;

    public ?ilCloudStorageOAuth2 $user_token = null;

    public ?ilCloudStorageBasicAuth $user_account = null;

    public ?GenericProvider $oauth2_provider = null;

    public array $provider_options = [];
    
    protected ?ilCloudStorageRestClient $rest_client = null;
    
    public function __construct(int $a_ref_id, int $connId)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->dic->logger()->root()->debug("ilCloudStorageGenericService __construct");
        $this->config = ilCloudStorageConfig::getInstance($connId);

        $this->object = new ilObjCloudStorage($a_ref_id);
        
        if ($this->config->getAuthMethod() == ilCloudStorageConfig::AUTH_METHOD_OAUTH2) {
            $this->oauth2_provider = ilCloudStorageOAuth2::getOAuth2Provider($this->config);
        }
        $this->rest_client = new ilCloudStorageRestClient($this->config);
        parent::__construct($this->getClientSettings());
    }

    abstract function getServiceId(): string;

    abstract function getServiceName(): string;
    
    abstract function hasCollaborationAppSupport(): bool;

    abstract function getParentIdField(): string;

    abstract function getFileIdField(): string;

    abstract function getAccessTokenExpiration(): string;

    abstract function getRefreshTokenExpiration(): string;

    public function hasParentId(): bool {
        return ($this->getParentIdField() != "");
    }

    public function hasFileId(): bool {
        return ($this->getFileIdField() != "");
    }
    
    public function getSessionName(string $session_name): string {
        return $this->getServiceId() . "_" . $session_name;
    }

    public function folderPropFind(): array {
        return [
            '{DAV:}resourcetype',
            '{DAV:}getcontentlength',
            '{DAV:}getcontenttype',
            '{DAV:}getlastmodified'
        ];
    }

    public static function getDefaultCollaborationAppFormats(): string
    {
        return self::DEFAULT_COLLABORATION_APP_FORMATS;
    }

    public static function getDefaultWebDavPath(): string
    {
        return self::DEFAULT_WEBDAV_PATH;
    }

    public static function getDefaultOAuth2Path(): string
    {
        return self::DEFAULT_OAUTH2_PATH;
    }

    // deprecated in new model see ilCloudStorageOAuth2 ilCloudStorageBasicAuth
    public function getHeaders(): array
    {
        $this->dic->logger()->root()->debug("getHeaders");
        switch ($this->config->getAuthMethod()) {
            case $this->config::AUTH_METHOD_OAUTH2:
                return array('Authorization' => 'Bearer ' . $this->getToken()->getAccessToken());
                break;
            case $this->config::AUTH_METHOD_BASIC:
                return array(
                    'Authorization' => 'Basic ' . base64_encode($this->getAccount()->getUsername() . ':' . ilCloudStorageUtil::decrypt($this->getAccount()->getPassword()))
                );
                break;
            default: 
                //ToDo
        }
    }

    public function getClientSettings(): array
    {
        $this->dic->logger()->root()->debug("getClientSettings");
        
        switch ($this->config->getAuthMethod()) {
            case $this->config::AUTH_METHOD_OAUTH2:
                return ilCloudStorageOAuth2::getClientSettings($this->config);
                break;
            case $this->config::AUTH_METHOD_BASIC:
                return ilCloudStorageBasicAuth::getClientSettings($this->config);
                break;
            default: 
                //ToDo
        }
    }
    
    public function getToken(): ilCloudStorageOAuth2
    {
        $this->dic->logger()->root()->debug("getToken");
        if (!$this->user_token) {
            $this->loadToken();
        }
        return $this->user_token;
    }

    // deprecated in new model see ilCloudStorageOAuth2
    public function loadToken(): void
    {
        $this->dic->logger()->root()->debug("loadToken");
        assert($this->object instanceof ilObjCloudStorage);
        $this->user_token = ilCloudStorageOAuth2::getUserToken($this->object->getConnId(), $this->object->getOwnerId());
    }
    
    public function getAccount(): ilCloudStorageBasicAuth
    {
        $this->dic->logger()->root()->debug("getAccount");
        if (!$this->user_account) {
            $this->loadAccount();
        }
        //$this->dic->logger()->root()->debug(var_export($this->user_token,true));
        return $this->user_account;
    }

    public function loadAccount(): void
    {
        $this->dic->logger()->root()->debug("loadAccount");
        assert($this->object instanceof ilObjCloudStorage);
        $this->user_account = ilCloudStorageBasicAuth::getUserAccount($this->object->getConnId(), $this->object->getOwnerId());
    }

    /**
     * @throws ilCloudStorageException
     */
    public function checkConnection(): void
    {
        $this->dic->logger()->root()->debug("checkConnection");

        $status = $this->getHTTPStatus();
        
        // unauthorized
        if ($status == 401) {
            throw new ilCloudStorageException(ilCloudStorageException::NOT_AUTHORIZED);
        }

        // everything else
        if ($status > 401) {
            throw new ilCloudStorageException(ilCloudStorageException::NO_CONNECTION);
        }
    }

    /**
     * Tree
     */
    public function addToFileTree(ilCloudStorageFileTree $file_tree, string $parent_folder = "/"): void
    {
        $this->dic->logger()->root()->debug("addToFileTree");
        $files = $this->listFolder($parent_folder);
        foreach ($files as $k => $item) {
            $this->dic->logger()->root()->debug("files...");
            if ($item instanceof ilCloudStorageFile) {
                assert($item instanceof ilCloudStorageFile);
                $size = $item->getSize();
                $is_dir = false;
            }
            if ($item instanceof ilCloudStorageFolder) {
                assert($item instanceof ilCloudStorageFolder);
                $size = null;
                $is_dir = true;
            }
            //$size = ($item instanceof ilCloudStorageFile) ? $size = $item->getSize() : null;
            //$is_dir = $item instanceof ilCloudStorageFolder;
            $file_tree->addNode($item->getFullPath(), (int) $item->getId(), $is_dir, strtotime($item->getDateTimeLastModified()), $size);
        }
    }

    public function addToFileTreeById(ilCloudStorageFileTree $file_tree, $id): bool 
    {
        $this->dic->logger()->root()->debug("addToFileTreeById");
        return false;
    }

    public function getFile(string $path = "", ?ilCloudStorageFileTree $file_tree = null): void
    {
        $this->deliverFile($path);
    }

    public function getFileById(int $id): bool
    {
        return false;
    }

    public function createFolder(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        if ($file_tree instanceof ilCloudStorageFileTree) {
            $path = ilCloudStorageUtil::joinPaths($file_tree->getRootPath(), $path);
        }

        if ($path != '/' && !$this->folderExists($path)) {
            $this->davCreateFolder($path);
        }
    }

    public function createFolderById(int $id, string  $folder_name) : int
    {
        $path = $this->idToPath($id, $folder_name);

        $this->createFolder($path);

        return $this->pathToId($path);
    }

    public function putFile(string $tmp_name, string $file_name, string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        $path = ilCloudStorageUtil::joinPaths($file_tree->getRootPath(), $path);
        if ($path == '/') {
            $path = '';
        }
        $this->uploadFile($path . "/" . $file_name, $tmp_name);
    }

    public function putFileById($tmp_name, $file_name, $id): bool
    {
        return false;
    }

    public function deleteItem(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        $path = ilCloudStorageUtil::joinPaths($file_tree->getRootPath(), $path);
        $this->delete($path);
    }

    public function deleteItemById(int $id): bool
    {
        return false;
    }
    
    public function isCaseSensitive(): bool
    {
        return true;
    }

    protected function idToPath(int $id, string $folder_name = '') : string
    {
        $path = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId())->getNodeFromId($id)->getPath();

        if (!empty($folder_name)) {
            $path = ilCloudStorageUtil::joinPaths($path, $folder_name);
        }

        return $path;
    }

    protected function pathToId(string $path) : int
    {
        $node = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId())->getNodeFromPath($path);

        if ($node === null) {
            $id = $this->davPathToId($path);

            $node = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId())->addNode($path, $id, true);
        }

        return $node->getId();
    }

    function propFind($url, array $properties, $depth = 0): array
    {
        $additional_headers = func_get_arg(3);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS('DAV:', 'd:propfind');
        $prop = $dom->createElement('d:prop');

        foreach ($properties as $property) {

            list(
                $namespace,
                $elementName
                )
                = Service::parseClarkNotation($property);

            if ($namespace === 'DAV:') {
                $element = $dom->createElement('d:' . $elementName);
            } else {
                $element = $dom->createElementNS($namespace, 'x:' . $elementName);
            }

            $prop->appendChild($element);
        }
        // $element = $dom->createElementNS('http://owncloud.org/ns', 'oc:id');
        // $prop->appendChild($element);

        $dom->appendChild($root)->appendChild($prop);
        $body = $dom->saveXML();

        $url = $this->getAbsoluteUrl($url);

        $request = new HTTP\Request('PROPFIND', $url, [
                'Depth'        => $depth,
                'Content-Type' => 'application/xml'
            ] + $additional_headers, $body);

        $response = $this->send($request);

        if ((int) $response->getStatus() == 404) {
            throw new ilCloudStorageException(ilCloudStorageException::RESSOURCE_NOT_EXISTING_OR_RENAMED);
        } else {
            if ((int) $response->getStatus() > 400) {
                throw new Exception('HTTP error: ' . $response->getStatus());
            }
        }

        $result = $this->parseMultiStatus($response->getBodyAsString());

        // If depth was 0, we only return the top item
        if ($depth === 0) {
            reset($result);
            $result = current($result);

            return isset($result[200]) ? $result[200] : [];
        }

        $newResult = [];
        foreach ($result as $href => $statusList) {

            $newResult[$href] = isset($statusList[200]) ? $statusList[200] : [];
        }

        return $newResult;
    }
    
    public function hasConnection(): bool
    {
        global $DIC;
        try {   //sabredav version 1.8 throws exception on missing connection
            $response = $this->request('PROPFIND', '', null, $this->getHeaders());
        } catch (Exception $e) {
            return false;
        }

        return ($response['statusCode'] < 400);
    }

    public function getHTTPStatus(): int
    {
        global $DIC;
        try {
            $response = $this->request('PROPFIND', '', null, $this->getHeaders());
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
     * @return ilCloudStorageWebDavFile[]|ilCloudStorageWebDavFolder[]
     */
    public function listFolder($id): array
    {
        global $DIC;
        /*
        if ($id == "/") {
            $id = $this->dav->object->getRootFolder();
        }
        */
        $id = $this->urlencode(ltrim($id, '/'));

        $settings = $this->getClientSettings();

        $response = $this->propFind(
            $settings['baseUri'] . $id,
            $this->folderPropFind(),
            1,
            $this->getHeaders()
        );
        $items = $this->getInstancesFromResponse($response);
        //$DIC->logger()->root()->log(var_export($items,true));
        return $items;
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function folderExists(string $path): bool
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
        global $DIC;
        $path = ltrim($path, "/");
        $encoded_path = $this->urlencode($path);
        $headers = $this->getHeaders();
        $settings = $this->getClientSettings();
        $arr = $this->propFind($settings['baseUri'] . $encoded_path, array(), 1, $headers);

        $prop = array_shift($arr);
        if (isset($prop['{DAV:}getcontenttype'])) {
            header("Content-type: " . $prop['{DAV:}getcontenttype']);
        }
        if (isset($prop['{DAV:}getcontentlength'])) {
            header("Content-type: " . $prop['{DAV:}getcontentlength']);
        }
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
    public function davCreateFolder(string $path): bool
    {
        global $DIC;
        
        $path = $this->urlencode(rtrim(ltrim($path, '/'),'/')).'/';
        //see: https://github.com/seedvault-app/seedvault/issues/500
        $response = $this->request('MKCOL', $path, null, $this->getHeaders());
        if (self::DEBUG) {
            global $log;
            $log->write("[davClient]->createFolder({$path}) | response status Code: {$response['statusCode']}");
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
    protected function urlencode(string $str): string
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
        $response = $this->request('PUT', $location, file_get_contents($local_file_path), $this->getHeaders());
        if (self::DEBUG) {
            global $log;
            $log->write("[davClient]->uploadFile({$location}, {$local_file_path}) | response status Code: {$response['statusCode']}");
        }

        return ($response['statusCode'] == 200);
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function delete(string $path): bool
    {
        $response = $this->request('DELETE', ltrim($this->urlencode($path), '/'), null, $this->getHeaders());
        if (self::DEBUG) {
            global $log;
            $log->write("[davClient]->delete({$path}) | response status Code: {$response['statusCode']}");
        }

        return ($response['statusCode'] == 200);
    }


    /**
     * @param $path
     *
     * @return bool
     */
    protected function itemExists(string $path): bool
    {
        try {
            $request = $this->request('PROPFIND', ltrim($this->urlencode($path), '/'), null, $this->getHeaders());
        } catch (Exception $e) {
            return false;
        }

        return ($request['statusCode'] < 400);
    }

    /**
     * @param string    $path
     * @param ilObjUser $user
     *
     * @throws ilCloudPluginConfigException
     * @throws GuzzleException
     */
    
    public function shareItem(string $path, ilObjUser $user): void
    {
        if ($user->getId() == $this->object->getOwnerId()) {
            // no need to share with yourself (can result in an error with nextcloud)
            return;
        }
        $user_string = $this->config->getMappingValueForUser($user);
        $shareAPI = $this->rest_client->shareAPI($this); // ToDo
        $existing = $shareAPI->getForPath($path);
        foreach ($existing as $share) {
            if ($share->getShareWith() === $user_string) {
                if (!$share->hasPermission(ilCloudStorageShareAPI::PERM_TYPE_UPDATE)) {
                    $shareAPI->update($share->getId(), $share->getPermissions() | (ilCloudStorageShareAPI::PERM_TYPE_UPDATE + ilCloudStorageShareAPI::PERM_TYPE_READ));
                }
                return;
            }
        }
        $shareAPI->create($path, $user_string, ilCloudStorageShareAPI::PERM_TYPE_UPDATE + ilCloudStorageShareAPI::PERM_TYPE_READ);
    }
    

    /**
     * @param string $path
     *
     * @return int
     */
    public function davPathToId(string $path) : int
    {
        // in generic WebDav no id can be retrieved from storage
        $id = ilCloudStorageFileNode::ID_UNKNOWN;
        if ($this->hasFileId()) {
            $settings = $this->getClientSettings();
            $response = $this->propFind(
                $settings['baseUri'] . $this->urlencode($path),
                [
                    $this->getFileIdField()
                ],
                0,
                $this->getHeaders()
            );
            $id = (int) (current($response));
        }
        return $id;
    }

    public function getDecodedWebUrl(string $web_url): string {
        return rawurldecode($web_url);
    }

    public function getPathFromWebUrl(string $web_url, int $type): string {
        global $DIC;
        $settings = $this->getClientSettings();
        $url = $this->getDecodedWebUrl($web_url);
        $web_dav_path = $settings['webDavPath'];
        if ($type == ilCloudStorageItem::TYPE_FOLDER) {
            $url = substr($url, 0, -1);
        }
        $url = substr($url, 0, -(strlen($this->getNameFromWebUrl($web_url, $type))));
        $url = substr($url, strpos($url, $web_dav_path) + strlen($web_dav_path));
        return ltrim($url,"/");
    }

    public function getNameFromWebUrl(string $web_url, int $type): string {
        $url = $this->getDecodedWebUrl($web_url);
        if ($type == ilCloudStorageItem::TYPE_FOLDER) {
            $url = substr($url, 0, -1);
        }
        return substr($url, strrpos($url, '/') + 1, strlen($url) - strrpos($url, '/'));
    }

    public function getInstancesFromResponse(array $response): array
    {
        $ret = array();
        if (count($response) == 0) {
            return $ret;
        }
        
        $parent_web_url = '';
        
        // get first item as parent
        foreach ($response as $url => $props) {
            $parent_web_url = $url;
            break;
        }

        array_shift($response);
       
        foreach ($response as $web_url => $props) {
            if (!array_key_exists("{DAV:}getcontentlength", $props)) { // is folder
                $exid_item = new ilCloudStorageFolder();
                $exid_item->loadFromProperties($parent_web_url, $web_url, $props, $this);
                $ret[] = $exid_item;
            } else { // is file
                $exid_item = new ilCloudStorageFile();
                $exid_item->loadFromProperties($parent_web_url, $web_url, $props, $this);
                $ret[] = $exid_item;
            }
        }
        //$DIC->logger()->root()->log("items: " . var_export($return,true));
        return $ret;
    }
}
