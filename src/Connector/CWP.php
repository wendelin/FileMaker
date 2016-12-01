<?php
/**
 * FileMaker API for PHP
 *
 * @package airmoi\FileMaker
 *
 * @copyright Copyright (c) 2016 by 1-more-thing (http://1-more-thing.com) All rights reserved.
 * @license BSD
 */

namespace airmoi\FileMaker\Connector;

use airmoi\FileMaker\FileMaker;

/**
 * Connector For FileMaker Custom web publishing (xml)
 *
 * @package airmoi\FileMaker\Connector
 */
class CWP implements ConnectorInterface
{
    /**
     * @var FileMaker
     */
    private $filemaker;

    /**
     * @var string Store the last URL call to Custom Web Publishing engine
     */
    public $lastRequestedUrl;

    public function __construct(FileMaker $filemaker)
    {
        $this->filemaker = $filemaker;
    }
    /**
     * Perform xml query to FM Server
     *
     * @param array $params
     *
     * @return string|FileMakerException the cUrl response
     * @throws FileMakerException
     */
    public function execute($params)
    {
        if (!function_exists('curl_init')) {
            $error = new FileMakerException($this->filemaker, 'cURL is required to use the FileMaker API.');
            if ($this->filemaker->getProperty('errorHandling') === 'default') {
                return $error;
            }
            throw $error;
        }

        if (isset($params['-grammar'])) {
            $grammar = $params['-grammar'];
            unset($params['-grammar']);
        } else {
            $grammar = 'fmresultset';
        }

        $restParams = [];
        foreach ($params as $option => $value) {
            if (($value !== true) && strtolower($this->filemaker->getProperty('charset')) !== 'utf-8') {
                $value = utf8_encode($value);
            }
            $restParams[] = urlencode($option) . ($value === true ? '' : '=' . urlencode($value));
        }

        $host = $this->filemaker->getProperty('hostspec');
        if (substr($host, -1, 1) !== '/') {
            $host .= '/';
        }
        $host .= 'fmi/xml/' . $grammar . '.xml';
        $this->filemaker->log('Request for ' . $host, FileMaker::LOG_INFO);

        $curl = curl_init($host);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        $curlHeadersSent = false;
        if (!headers_sent()) {
            $curlHeadersSent = true;
            curl_setopt($curl, CURLOPT_HEADER, true);
        }
        $this->setCurlWPCSessionCookie($curl);

        if ($this->filemaker->getProperty('username')) {
            $auth = base64_encode(
                utf8_decode($this->filemaker->getProperty('username'))
                . ':' . utf8_decode($this->filemaker->getProperty('password'))
            );
            $authHeader = 'Authorization: Basic ' . $auth;
            curl_setopt(
                $curl,
                CURLOPT_HTTPHEADER,
                [
                    'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
                    'X-FMI-PE-ExtendedPrivilege: IrG6U+Rx0F5bLIQCUb9gOw==',
                    $authHeader
                ]
            );
        } else {
            curl_setopt(
                $curl,
                CURLOPT_HTTPHEADER,
                [
                    'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
                    'X-FMI-PE-ExtendedPrivilege: IrG6U+Rx0F5bLIQCUb9gOw=='
                ]
            );
        }

        curl_setopt($curl, CURLOPT_POSTFIELDS, implode('&', $restParams));
        if ($curlOptions = $this->filemaker->getProperty('curlOptions')) {
            foreach ($curlOptions as $key => $value) {
                curl_setopt($curl, $key, $value);
            }
        }
        $this->lastRequestedUrl = $host . '?' . implode('&', $restParams);
        $this->filemaker->log($this->lastRequestedUrl, FileMaker::LOG_DEBUG);

        $curlResponse = curl_exec($curl);
        $this->filemaker->log($curlResponse, FileMaker::LOG_DEBUG);
        if ($curlError = curl_errno($curl)) {
            if ($curlError === 52) {
                $error = new FileMakerException(
                    $this,
                    'cURL Communication Error: (' . $curlError . ') ' . curl_error($curl)
                    . ' - The Web Publishing Core and/or FileMaker Server services are not running.',
                    $curlError
                );
            } elseif ($curlError === 22) {
                if (stristr("50", curl_error($curl))) {
                    $error = new FileMakerException(
                        $this,
                        'cURL Communication Error: (' . $curlError . ') ' . curl_error($curl)
                        . ' - The Web Publishing Core and/or FileMaker Server services are not running.',
                        $curlError
                    );
                } else {
                    $error = new FileMakerException(
                        $this,
                        'cURL Communication Error: (' . $curlError . ') ' . curl_error($curl)
                        . ' - This can be due to an invalid username or password, or if the FMPHP privilege is not '
                        . 'enabled for that user.',
                        $curlError
                    );
                }
            } else {
                $error = new FileMakerException(
                    $this,
                    'cURL Communication Error: (' . $curlError . ') ' . curl_error($curl),
                    $curlError
                );
            }
            if ($this->filemaker->getProperty('errorHandling') === 'default') {
                return $error;
            }
            throw $error;
        }
        curl_close($curl);

        $this->setClientWPCSessionCookie($curlResponse);
        if ($curlHeadersSent) {
            $curlResponse = $this->eliminateXMLHeader($curlResponse);
        }

        return $curlResponse;
    }

    /**
     * Returns the data for the specified container field.
     * Pass in a URL string that represents the file path for the container
     * field contents. For example, get the image data from a container field
     * named 'Cover Image'. For a Object\Record object named $record,
     * URL-encode the path returned by the getField() method.  For example:
     *
     * @example <IMG src="img.php?-url=<?php echo urlencode($record->getField('Cover Image')); ?>">
     *
     * Then as shown below in a line from img.php, pass the URL into
     * getContainerData() for the FileMaker object named $fm:
     *
     * @example echo $fm->getContainerData($_GET['-url']);
     *
     * @param string $url URL of the container field contents to get.
     *
     * @return string|FileMakerException Raw field data.
     * @throws FileMakerException if remote container field or curl not active.
     */
    public function getContainerData($url)
    {
        if (!function_exists('curl_init')) {
            $error = new FileMakerException($this->filemaker, 'cURL is required to use the FileMaker API.');
            if ($this->filemaker->getProperty('errorHandling') === 'default') {
                return $error;
            }
            throw $error;
        }
        if (strncasecmp($url, '/fmi/xml/cnt', 11) !== 0) {
            $error = new FileMakerException($this->filemaker, 'getContainerData() does not support remote containers');
            if ($this->filemaker->getProperty('errorHandling') === 'default') {
                return $error;
            }
            throw $error;
        } else {
            $hostspec = $this->filemaker->getProperty('hostspec');
            if (substr($hostspec, -1, 1) === '/') {
                $hostspec = substr($hostspec, 0, -1);
            }
            $hostspec .= $url;
            $hostspec = htmlspecialchars_decode($hostspec);
            $hostspec = str_replace(" ", "%20", $hostspec);
        }
        $this->filemaker->log('Request for ' . $hostspec, FileMaker::LOG_INFO);
        $curl = curl_init($hostspec);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        $isHeadersSent = false;
        if (!headers_sent()) {
            $isHeadersSent = true;
            curl_setopt($curl, CURLOPT_HEADER, true);
        }
        $this->setCurlWPCSessionCookie($curl);

        if ($this->filemaker->getProperty('username')) {
            $authString = base64_encode($this->filemaker->getProperty('username') . ':' . $this->filemaker->getProperty('password'));
            $headers    = [
                'Authorization: Basic ' . $authString,
                'X-FMI-PE-ExtendedPrivilege: IrG6U+Rx0F5bLIQCUb9gOw=='
            ];
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        } else {
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['X-FMI-PE-ExtendedPrivilege: IrG6U+Rx0F5bLIQCUb9gOw==']);
        }
        if ($curlOptions = $this->filemaker->getProperty('curlOptions')) {
            foreach ($curlOptions as $property => $value) {
                curl_setopt($curl, $property, $value);
            }
        }
        $curlResponse = curl_exec($curl);
        $this->setClientWPCSessionCookie($curlResponse);
        if ($isHeadersSent) {
            $curlResponse = $this->eliminateContainerHeader($curlResponse);
        }
        $this->filemaker->log($curlResponse, FileMaker::LOG_DEBUG);
        if ($curlError = curl_errno($curl)) {
            $error = new FileMakerException(
                $this,
                'cURL Communication Error: (' . $curlError . ') ' . curl_error($curl)
            );
            if ($this->filemaker->getProperty('errorHandling') === 'default') {
                return $error;
            }
            throw $error;
        }
        curl_close($curl);
        return $curlResponse;
    }

    /**
     * Set curl Sesion cookie
     * @param Resource $curl a cUrl handle ressource
     */
    private function setCurlWPCSessionCookie($curl)
    {
        if (!$this->filemaker->getProperty('useCookieSession')) {
            return;
        }
        if (isset($_COOKIE["WPCSessionID"])) {
            $wpcSessionId = $_COOKIE["WPCSessionID"];
            if (!is_null($wpcSessionId)) {
                $header = "WPCSessionID=" . $wpcSessionId;
                curl_setopt($curl, CURLOPT_COOKIE, $header);
            }
        }
    }

    /**
     * Pass WPC session cookie to client for later auth
     * @param string $curlResponse a curl response
     */
    private function setClientWPCSessionCookie($curlResponse)
    {
        if (!$this->filemaker->getProperty('useCookieSession')) {
            return;
        }
        $found = preg_match('/WPCSessionID="([^;]*)";/m', $curlResponse, $matches);
        /* Update WPCSession Cookie if needed */
        if ($found && @$_COOKIE['WPCSessionID'] !== $matches[1]) {
            setcookie("WPCSessionID", $matches[1]);
            $_COOKIE['WPCSessionID'] = $matches[1];
        }
    }

    /**
     *
     * @param string $curlResponse a curl response
     * @return int content length, -1 if not provided by headers
     */
    private function getContentLength($curlResponse)
    {
        $found = preg_match('/Content-Length: (\d+)/', $curlResponse, $matches);
        if ($found) {
            return $matches[1];
        } else {
            return -1;
        }
    }

    /**
     *
     * @param string $curlResponse  a curl response
     * @return string curlResponse without xml header
     */
    private function eliminateXMLHeader($curlResponse)
    {
        $isXml = strpos($curlResponse, "<?xml");
        if ($isXml !== false) {
            return substr($curlResponse, $isXml);
        } else {
            return $curlResponse;
        }
    }

    /**
     *
     * @param string $curlResponse  a curl response
     * @return string cUrl Response without leading carriage return
     */
    private function eliminateContainerHeader($curlResponse)
    {
        $len = strlen("\r\n\r\n");
        $pos = strpos($curlResponse, "\r\n\r\n");
        if ($pos !== false) {
            return substr($curlResponse, $pos + $len);
        } else {
            return $curlResponse;
        }
    }
}
