<?php

namespace Mollie\Api\Endpoints;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\BaseCollection;
use Mollie\Api\Resources\Payment;
use Psr\Http\Message\StreamInterface;

abstract class EndpointAbstract
{
    const REST_CREATE = MollieApiClient::HTTP_POST;
    const REST_UPDATE = MollieApiClient::HTTP_POST;
    const REST_READ = MollieApiClient::HTTP_GET;
    const REST_LIST = MollieApiClient::HTTP_GET;
    const REST_DELETE = MollieApiClient::HTTP_DELETE;

    /**
     * Default number of objects to retrieve when listing all objects.
     */
    const DEFAULT_LIMIT = 50;

    /**
     * @var MollieApiClient
     */
    protected $api;

    /**
     * @var string
     */
    protected $resourcePath;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @param MollieApiClient $api
     */
    public function __construct(MollieApiClient $api)
    {
        $this->api = $api;
    }

    /**
     * @param array $filters
     * @return string
     */
    private function buildQueryString(array $filters)
    {
        if (empty($filters)) {
            return "";
        }

        return "?" . http_build_query($filters, "");
    }

    /**
     * @param string $restResource
     * @param string|null|resource|StreamInterface $body
     * @param array $filters
     * @return object
     * @throws ApiException
     */
    private function rest_create($restResource, $body, array $filters)
    {
        $result = $this->api->performHttpCall(
            self::REST_CREATE,
            $restResource . $this->buildQueryString($filters),
            $body
        );

        return $this->copy($result, $this->getResourceObject());
    }

    /**
     * Retrieves a single object from the REST API.
     *
     * @param string $restResource Resource name.
     * @param string $id Id of the object to retrieve.
     * @param array $filters
     * @return object
     * @throws ApiException
     */
    private function rest_read($restResource, $id, array $filters)
    {
        if (empty($id)) {
            throw new ApiException("Invalid resource id.");
        }

        $id = urlencode($id);
        $result = $this->api->performHttpCall(
            self::REST_READ,
            "{$restResource}/{$id}" . $this->buildQueryString($filters)
        );

        return $this->copy($result, $this->getResourceObject());
    }

    /**
     * Sends a DELETE request to a single Molle API object.
     *
     * @param string $restResource
     * @param string $id
     *
     * @return object
     * @throws ApiException
     */
    private function rest_delete($restResource, $id)
    {
        if (empty($id)) {
            throw new ApiException("Invalid resource id.");
        }

        $id = urlencode($id);
        $result = $this->api->performHttpCall(
            self::REST_DELETE,
            "{$restResource}/{$id}"
        );

        if ($result === null) {
            return null;
        }

        return $this->copy($result, $this->getResourceObject());
    }

    /**
     * Sends a POST request to a single Molle API object to update it.
     *
     * @param string $restResource
     * @param string $id
     * @param string|null|resource|StreamInterface $body
     *
     * @return object
     * @throws ApiException
     */
    protected function rest_update($restResource, $id, $body)
    {
        if (empty($id)) {
            throw new ApiException("Invalid resource id.");
        }

        $id = urlencode($id);
        $result = $this->api->performHttpCall(
            self::REST_UPDATE,
            "{$restResource}/{$id}",
            $body
        );

        return $this->copy($result, $this->getResourceObject());
    }

    /**
     * Get a collection of objects from the REST API.
     *
     * @param $restResource
     * @param string $from The first resource ID you want to include in your list.
     * @param int $limit
     * @param array $filters
     *
     * @return BaseCollection
     * @throws ApiException
     */
    private function rest_list($restResource, $from = null, $limit = self::DEFAULT_LIMIT, array $filters)
    {
        $filters = array_merge(["from" => $from, "limit" => $limit], $filters);

        $apiPath = $restResource . $this->buildQueryString($filters);

        $result = $this->api->performHttpCall(self::REST_LIST, $apiPath);

        /** @var BaseCollection $collection */
        $collection = $this->getResourceCollectionObject($result->count, $result->_links);

        foreach ($result->_embedded->{$collection->getCollectionResourceName()} as $dataResult) {
            $collection[] = $this->copy($dataResult, $this->getResourceObject());
        }

        return $collection;
    }

    /**
     * Copy the results received from the API into the PHP objects that we use.
     *
     * @param object $apiResult
     * @param object $object
     *
     * @return object
     */
    protected function copy($apiResult, $object)
    {
        foreach ($apiResult as $property => $value) {
            $object->$property = $value;
        }

        return $object;
    }

    /**
     * Get the object that is used by this API. Every API uses one type of object.
     *
     * @return object
     */
    abstract protected function getResourceObject();

    /**
     * Get the collection object that is used by this API. Every API uses one type of collection object.
     *
     * @param int $count
     * @param object[] $_links
     *
     * @return BaseCollection
     */
    abstract protected function getResourceCollectionObject($count, $_links);

    /**
     * Create a resource with the remote API.
     *
     * @param array $data An array containing details on the resource. Fields supported depend on the resource created.
     * @param array $filters
     *
     * @return object
     * @throws ApiException
     */
    public function create(array $data = [], array $filters = [])
    {
        $encoded = json_encode($data);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException("Error encoding parameters into JSON: '" . json_last_error() . "'.");
        }

        return $this->rest_create($this->getResourcePath(), $encoded, $filters);
    }

    /**
     * Retrieve information on a single resource from Mollie.
     *
     * Will throw a ApiException if the resource cannot be found.
     *
     * @param string $resourceId
     * @param array $filters
     *
     * @return object
     * @throws ApiException
     */
    public function get($resourceId, array $filters = [])
    {
        return $this->rest_read($this->getResourcePath(), $resourceId, $filters);
    }

    /**
     * Delete a single resource from Mollie.
     *
     * Will throw a ApiException if the resource cannot be found.
     *
     * @param string $resourceId
     *
     * @return object
     * @throws ApiException
     */
    public function delete($resourceId)
    {
        return $this->rest_delete($this->getResourcePath(), $resourceId);
    }

    /**
     * Retrieve all objects of a certain resource.
     *
     * @param string $from The first resource ID you want to include in your list.
     * @param int $limit
     * @param array $filters
     *
     * @return BaseCollection
     * @throws ApiException
     */
    public function all($from = null, $limit = self::DEFAULT_LIMIT, array $filters = [])
    {
        return $this->rest_list($this->getResourcePath(), $from, $limit, $filters);
    }

    /**
     * @param string $resourcePath
     */
    public function setResourcePath($resourcePath)
    {
        $this->resourcePath = strtolower($resourcePath);
    }

    /**
     * @return string
     * @throws ApiException
     */
    public function getResourcePath()
    {
        if (strpos($this->resourcePath, "_") !== false) {
            list($parentResource, $childResource) = explode("_", $this->resourcePath, 2);

            if (empty($this->parentId)) {
                throw new ApiException("Subresource '{$this->resourcePath}' used without parent '$parentResource' ID.");
            }

            return "$parentResource/{$this->parentId}/$childResource";
        }

        return $this->resourcePath;
    }

    /**
     * @param string $parentId
     * @return $this
     */
    public function withParentId($parentId)
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * Set the resource to use a certain parent. Use this method before performing a get() or all() call.
     *
     * @param Payment|object $parent An object with an 'id' property
     * @return $this
     */
    public function with($parent)
    {
        return $this->withParentId($parent->id);
    }
}