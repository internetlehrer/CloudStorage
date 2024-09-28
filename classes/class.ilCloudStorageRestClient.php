<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ilCloudStorageWebDavRESTClient
 *
 * @author Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageRestClient
{

    protected ?Client $http_client = null;

    protected ?ilCloudStorageConfig $config = null;

    public function __construct(ilCloudStorageConfig $a_config)
    {
        $this->config = $a_config;
        $this->http_client = new Client([
            'base_uri' => rtrim($this->config->getServerURL(), '/')
        ]);
    }


    public function shareAPI(ilCloudStorageGenericService $a_dav)
    {
        return new ilCloudStorageShareAPI($this->http_client, $a_dav);
    }


    /**
     * @param string $uri
     *
     * @return mixed|ResponseInterface
     * @throws GuzzleException
     */
    public function get($uri)
    {
        $response = $this->http_client->request('GET', $uri);

        return $response;
    }
}
