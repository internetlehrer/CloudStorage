<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use Sabre\DAV\Client;
use Sabre\HTTP;
use Sabre\Xml\Service;

/**
 * Class ilCloudStorageOwnCloudDAVClient
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageOwnCloudDAVClient extends Client
{

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
}