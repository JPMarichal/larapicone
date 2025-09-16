<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class PineconeService
{
    protected Client $client;
    protected string $apiKey;
    protected string $environment;
    protected string $index;
    protected string $namespace;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('pinecone.api_key');
        $this->environment = config('pinecone.environment');
        $this->index = config('pinecone.index');
        $this->namespace = config('pinecone.namespace');
        $this->timeout = config('pinecone.timeout', 30);

        $this->client = new Client([
            'base_uri' => "https://{$this->index}-{$this->environment}.svc.{$this->environment}.pinecone.io/",
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => $this->timeout,
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
     * @return array
     * @throws \Exception
     */
    public function getVector(string $id): array
    {
        try {
            $response = $this->client->get("vectors/fetch?ids={$id}&namespace={$this->namespace}");
            return json_decode($response->getBody()->getContents(), true);
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
