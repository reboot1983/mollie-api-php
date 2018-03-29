<?php

namespace Mollie\Api;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Mollie\Api\Endpoints\MethodEndpoint;
use Mollie\Api\Endpoints\PaymentEndpoint;
use Mollie\Api\Endpoints\PaymentRefundEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class MollieApiClient
{
    /**
     * Version of our client.
     */
    const CLIENT_VERSION = "1.9.6";

    /**
     * Endpoint of the remote API.
     */
    const API_ENDPOINT = "https://api.mollie.com";

    /**
     * Version of the remote API.
     */
    const API_VERSION = "v2";

    /**
     * HTTP Methods
     */
    const HTTP_GET = "GET";
    const HTTP_POST = "POST";
    const HTTP_DELETE = "DELETE";

    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $apiEndpoint = self::API_ENDPOINT;

    /**
     * RESTful Payments resource.
     *
     * @var PaymentEndpoint
     */
    public $payments;

    /**
     * RESTful Payments Refunds resource.
     *
     * @var PaymentRefundEndpoint
     */
    public $paymentsRefunds;

    /**
     * RESTful Methods resource.
     *
     * @var MethodEndpoint
     */
    public $methods;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * True if an OAuth access token is set as API key.
     *
     * @var bool
     */
    protected $oauthAccess;

    /**
     * @var array
     */
    protected $versionStrings = [];

    /**
     * @var resource
     */
    protected $ch;

    /**
     * @var int
     */
    protected $lastHttpResponseStatusCode;

    /**
     * @param ClientInterface $httpClient
     *
     * @throws IncompatiblePlatform
     */
    public function __construct(ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ? $httpClient : new Client();

        $compatibilityChecker = new CompatibilityChecker();
        $compatibilityChecker->checkCompatibility();

        $this->initializeEndpoints();

        $this->addVersionString("Mollie/" . self::CLIENT_VERSION);
        $this->addVersionString("PHP/" . phpversion());
        $this->addVersionString("Guzzle/" . ClientInterface::VERSION);
    }

    public function initializeEndpoints()
    {
        $this->payments = new PaymentEndpoint($this);
        $this->paymentsRefunds = new PaymentRefundEndpoint($this);
        $this->methods = new MethodEndpoint($this);
    }

    /**
     * @param string $url
     */
    public function setApiEndpoint($url)
    {
        $this->apiEndpoint = rtrim(trim($url), '/');
    }

    /**
     * @return string
     */
    public function getApiEndpoint()
    {
        return $this->apiEndpoint;
    }

    /**
     * @param string $apiKey The Mollie API key, starting with 'test_' or 'live_'
     * @throws ApiException
     */
    public function setApiKey($apiKey)
    {
        $apiKey = trim($apiKey);

        if (!preg_match('/^(live|test)_\w{30,}$/', $apiKey)) {
            throw new ApiException("Invalid API key: '{$apiKey}'. An API key must start with 'test_' or 'live_'.");
        }

        $this->apiKey = $apiKey;
        $this->oauthAccess = false;
    }

    /**
     * @param string $accessToken OAuth access token, starting with 'access_'
     * @throws ApiException
     */
    public function setAccessToken($accessToken)
    {
        $accessToken = trim($accessToken);

        if (!preg_match('/^access_\w+$/', $accessToken)) {
            throw new ApiException("Invalid OAuth access token: '{$accessToken}'. An access token must start with 'access_'.");
        }

        $this->apiKey = $accessToken;
        $this->oauthAccess = true;
    }

    /**
     * @return bool
     */
    public function usesOAuth()
    {
        return $this->oauthAccess;
    }

    /**
     * @param string $versionString
     */
    public function addVersionString($versionString)
    {
        $this->versionStrings[] = str_replace([" ", "\t", "\n", "\r"], '-', $versionString);
    }

    /**
     * Perform an http call. This method is used by the resource specific classes. Please use the $payments property to
     * perform operations on payments.
     *
     * @see $payments
     * @see $isuers
     *
     * @param string $httpMethod
     * @param string $apiMethod
     * @param string|null|resource|StreamInterface $httpBody
     *
     * @return object
     * @throws ApiException
     *
     * @codeCoverageIgnore
     */
    public function performHttpCall($httpMethod, $apiMethod, $httpBody = null)
    {
        if (empty($this->apiKey)) {
            throw new ApiException("You have not set an API key or OAuth access token. Please use setApiKey() to set the API key.");
        }

        $url = $this->apiEndpoint . "/" . self::API_VERSION . "/" . $apiMethod;
        $userAgent = implode(' ', $this->versionStrings);

        if ($this->usesOAuth()) {
            $userAgent .= " OAuth/2.0";
        }

        $headers = [
            'Accept' => "application/json",
            'Authorization' => "Bearer {$this->apiKey}",
            'User-Agent' => $userAgent,
            'X-Mollie-Client-Info' => php_uname(),
        ];

        $request = new Request($httpMethod, $url, $headers, $httpBody);

        try {
            $response = $this->httpClient->send($request);
        } catch (GuzzleException $e) {
            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$response) {
            throw new ApiException("Did not receive API response.");
        }

        return $this->parseResponseBody($response);
    }

    /**
     * Parse the PSR-7 Response body
     *
     * @param ResponseInterface $response
     * @return object
     * @throws ApiException
     */
    private function parseResponseBody(ResponseInterface $response)
    {
        $body = $response->getBody()->getContents();
        if (empty($body)) {
            throw new ApiException("No response body found.");
        }

        $object = @json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException("Unable to decode Mollie response: '{$body}'.");
        }

        if (!empty($object->error)) {
            $exception = new ApiException("Error executing API call ({$object->error->type}): {$object->error->message}.");

            if (!empty($object->error->field)) {
                $exception->setField($object->error->field);
            }

            throw $exception;
        }

        return $object;
    }
}