<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

// Define CURL_SSLVERSION_TLSv1_2 if not defined
if (!defined('CURL_SSLVERSION_TLSv1_2')) {
    define('CURL_SSLVERSION_TLSv1_2', 6);
}

class PineconeService
{
    protected Client $client;
    protected string $apiKey;
    protected string $environment;
    protected string $index;
    protected string $namespace;
    protected int $timeout;

    /**
     * Get the HTTP client instance
     * 
     * @return \GuzzleHttp\Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    public function __construct()
    {
        $this->apiKey = config('pinecone.api_key');
        $this->environment = config('pinecone.environment');
        $this->index = config('pinecone.index');
        $this->namespace = config('pinecone.namespace');
        $this->timeout = config('pinecone.timeout', 30);

        // Use the exact hostname from Pinecone dashboard
        $this->client = new Client([
            'base_uri' => "https://escrituras-i17oiaw.svc.aped-4627-b74a.pinecone.io/",
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => $this->timeout,
            'verify' => false, // Disable SSL verification
            'http_errors' => true, // Enable HTTP errors
            'debug' => false, // Disable debug to avoid output issues
        ]);
    }

    /**
     * Query the Pinecone index for similar vectors
     *
     * @param array $vector
     * @param int $topK
     * @param array $filter
     * @return array
     * @throws \Exception
     */
    public function query(array $vector, int $topK = 5, array $filter = []): array
    {
        try {
            $response = $this->client->post('query', [
                'json' => [
                    'vector' => $vector,
                    'topK' => $topK,
                    'includeMetadata' => true,
                    'includeValues' => false,
                    'namespace' => $this->namespace,
                    'filter' => $filter,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Pinecone query error: ' . $e->getMessage());
            throw new \Exception('Error querying Pinecone: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Upsert vectors to the index
     *
     * @param array $vectors
     * @return array
     * @throws \Exception
     */
    public function upsertVectors(array $vectors): array
    {
        try {
            $response = $this->client->post('vectors/upsert', [
                'json' => [
                    'vectors' => $vectors,
                    'namespace' => $this->namespace,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Pinecone upsert error: ' . $e->getMessage());
            throw new \Exception('Error upserting to Pinecone: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get vector by ID
     *
     * @param string $id
     * @param bool $includeValues Whether to include the vector values in the response
     * @return array
     * @throws \Exception
     */
    public function getVector(string $id, bool $includeValues = true): array
    {
        try {
            $response = $this->client->get("vectors/fetch?ids={$id}&namespace={$this->namespace}");
            $result = json_decode($response->getBody()->getContents(), true);
            
            if (!$includeValues && isset($result['vectors'][$id])) {
                unset($result['vectors'][$id]['values']);
            }
            
            return $result;
        } catch (GuzzleException $e) {
            Log::error('Pinecone get vector error: ' . $e->getMessage());
            throw new \Exception('Error getting vector from Pinecone: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete vectors by ID or filter
     *
     * @param array $ids
     * @param array $filter
     * @return array
     * @throws \Exception
     */
    public function deleteVectors(array $ids = [], array $filter = []): array
    {
        try {
            $data = ['namespace' => $this->namespace];
            
            if (!empty($ids)) {
                $data['ids'] = $ids;
            }
            
            if (!empty($filter)) {
                $data['filter'] = $filter;
            }

            $response = $this->client->post('vectors/delete', ['json' => $data]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Pinecone delete vectors error: ' . $e->getMessage());
            throw new \Exception('Error deleting vectors from Pinecone: ' . $e->getMessage(), 0, $e);
        }
    }
}
