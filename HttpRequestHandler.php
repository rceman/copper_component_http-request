<?php

namespace Copper\Component\HttpRequest;

use Copper\Component\HttpRequest\Entity\ResponseStatus;
use GuzzleHttp;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

class HttpRequestHandler
{
    public const CONTENT_TYPE_JSON = 'application/json';
    public const CONTENT_TYPE_FORM = 'application/x-www-form-urlencoded';

    public const AUTHORIZATION_TYPE_BASIC = 'Basic';
    public const AUTHORIZATION_TYPE_BEARER = 'Bearer';

    public const HEADER_AUTHORIZATION = 'Authorization';
    public const HEADER_CONTENT_TYPE = 'Content-Type';

    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';

    private $default_request_headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ];

    private $allow_redirects = true;
    private $verify_ssl = false;
    private $retryMaxCount = 1;
    private $retryMsgNeedle = false;

    private $proxyConfig = false;

    private $default_response = [
        "msg" => "",
        "status" => false,
        "result" => [],
        "config" => [],
        "request" => [
            "url" => "",
            "method" => "",
            "body" => "",
            "headers" => []
        ]
    ];

    public function __construct($verify_ssl = false, $allow_redirects = true, $default_request_headers = [])
    {
        $this->verify_ssl = $verify_ssl;
        $this->allow_redirects = $allow_redirects;
        $this->default_request_headers = array_merge($this->default_request_headers, $default_request_headers);
    }

    public function setContentType($contentType)
    {
        $this->default_request_headers[self::HEADER_CONTENT_TYPE] = $contentType;
    }

    public function setAuthorization($credentials, $type = self::AUTHORIZATION_TYPE_BASIC)
    {
        $this->default_request_headers[self::HEADER_AUTHORIZATION] = $type . ' ' . $credentials;
    }

    public function setRetryMaxCount($maxCount)
    {
        $this->retryMaxCount = $maxCount;
    }

    /**
     * Setup Proxy
     *
     * With Creds: http://username:password@192.168.16.1:10
     *
     * @param string $https - Use this proxy with "https"
     * @param string $http - Use this proxy with "http"
     * @param array $no - Don't use a proxy with these ['.mit.edu', 'foo.com']
     */
    public function setProxyConfig($https, $http = null, $no = null)
    {
        $proxyConfig = [];

        $proxyConfig['https'] = $https;
        $proxyConfig['http'] = ($http !== null) ? $http : $https;
        $proxyConfig['no'] = ($no !== null) ? $no : [];

        $this->proxyConfig = $proxyConfig;
    }

    public function setRetryMsgNeedle($needle)
    {
        $this->retryMsgNeedle = $needle;
    }

    public function GET($url, $headers = [])
    {
        return $this->makeRequest($url, self::METHOD_GET, '', $headers);
    }

    /**
     * Send POST request to url
     * @param $url
     * @param string|int|float|array $body
     * @param array $headers
     * @return array
     */
    public function POST($url, $body = '', $headers = [])
    {
        return $this->makeRequest($url, self::METHOD_POST, $body, $headers);
    }

    public function PUT($url, $body = '', $headers = [])
    {
        return $this->makeRequest($url, self::METHOD_PUT, $body, $headers);
    }

    public function PATCH($url, $body = '', $headers = [])
    {
        return $this->makeRequest($url, self::METHOD_PATCH, $body, $headers);
    }

    public function DELETE($url, $headers = [])
    {
        return $this->makeRequest($url, self::METHOD_DELETE, '', $headers);
    }

    public function allowRedirects($bool = true)
    {
        $this->allow_redirects = $bool;
    }

    /**
     * @param mixed $body
     */
    private function bodyIsJSON($body)
    {
        if (is_string($body) === false)
            return false;

        $decoded_body = json_decode($body);

        if ($decoded_body === null && json_last_error() !== JSON_ERROR_NONE)
            return false;

        return true;
    }

    private function autoDetectContentType($method, $body, &$headers)
    {
        if (in_array(strtoupper($method), [self::METHOD_GET, self::METHOD_DELETE]))
            return;

        if (self::bodyIsJSON($body))
            $contentType = self::CONTENT_TYPE_JSON;
        else
            $contentType = self::CONTENT_TYPE_FORM;

        $headers = array_merge([self::HEADER_CONTENT_TYPE => $contentType], $headers);
    }

    private function detectCustomStatus($message)
    {
        $customStatus = false;

        foreach (ResponseStatus::CUSTOM_CODE_MATCH as $code => $matchText) {
            if (strpos($message, $matchText) !== false)
                $customStatus = ['code' => $code, 'text' => ResponseStatus::CODE_TEXT[$code]];
        }

        return $customStatus;
    }

    private function makeRetriedRequest($retryMaxCount, $url, $method, $body = '', $headers = [], $retryCount = 0)
    {
        $response = array_merge([], $this->default_response);

        $headers = array_merge($this->default_request_headers, is_array($headers) ? $headers : []);

        $this->autoDetectContentType($method, $body, $headers);

        $client = new GuzzleHttp\Client(array(
            GuzzleHttp\RequestOptions::VERIFY => $this->verify_ssl
        ));

        $response["request"] = [
            "url" => $url,
            "method" => $method,
            "body" => $body,
            "headers" => $headers
        ];

        $response["config"] = [
            "default_request_headers" => $this->default_request_headers,
            "verify_ssl" => $this->verify_ssl,
            "allow_redirects" => $this->allow_redirects,
        ];

        if ($url === '') {
            $response["msg"] = "(url) param is empty " . $url;
            return $response;
        }

        try {
            $requestParams = [
                GuzzleHttp\RequestOptions::HEADERS => $headers,
                GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => $this->allow_redirects
            ];

            if (is_array($body))
                $requestParams[GuzzleHttp\RequestOptions::FORM_PARAMS] = $body;
            else
                $requestParams[GuzzleHttp\RequestOptions::BODY] = $body;

            if ($this->proxyConfig !== false)
                $requestParams[GuzzleHttp\RequestOptions::PROXY] = $this->proxyConfig;

            $requestResponse = $client->request($method, $url, $requestParams);

            $response["status"] = true;
        } catch (BadResponseException $e) {
            $requestResponse = $e->getResponse();

            $response["status"] = false;
            $response["msg"] = $e->getMessage();
        } catch (GuzzleException $e) {
            $requestResponse = null;

            $response["result"] = [
                "status_code" => ResponseStatus::CODE_0,
                "status_text" => ResponseStatus::CODE_TEXT[ResponseStatus::CODE_0],
                "protocol_version" => "1.1",
                "body" => "GuzzleException " . $e->getMessage(),
                "body_size" => 0,
                "headers" => []
            ];

            $customStatus = $this->detectCustomStatus($e->getMessage());

            if ($customStatus !== false) {
                $response["result"]["status_code"] = $customStatus['code'];
                $response["result"]["status_text"] = $customStatus['text'];;
            }

            $response["status"] = false;
            $response["msg"] = $e->getMessage();
        }

        if ($requestResponse !== null)
            $response["result"] = [
                "status_code" => $requestResponse->getStatusCode(),
                "status_text" => $requestResponse->getReasonPhrase(),
                "protocol_version" => $requestResponse->getProtocolVersion(),
                "body" => $requestResponse->getBody()->getContents(),
                "body_size" => $requestResponse->getBody()->getSize(),
                "headers" => $requestResponse->getHeaders()
            ];

        $response["result"]["headers"]["WH-RETRY-COUNT"] = trim($retryCount);

        if (array_key_exists('Content-Length', $response["result"]["headers"]))
            $response["result"]["body_size"] = $response["result"]["headers"]['Content-Length'][0];

        if (array_key_exists('x-encoded-content-length', $response["result"]["headers"]))
            $response["result"]["body_size"] = $response["result"]["headers"]['x-encoded-content-length'][0];

        if ($response["result"]["body_size"] === 0)
            $response["result"]["body_size"] = strlen($response["result"]["body"]);

        if ($this->retryMsgNeedle !== false && strpos($response["msg"], $this->retryMsgNeedle) !== false && $retryCount < $retryMaxCount)
            $response = $this->makeRetriedRequest($retryMaxCount, $url, $method, $body, $headers, ++$retryCount);

        return $response;
    }

    public function makeRequest($url, $method, $body = '', $headers = [])
    {
        return $this->makeRetriedRequest($this->retryMaxCount, $url, $method, $body, $headers);
    }
}
