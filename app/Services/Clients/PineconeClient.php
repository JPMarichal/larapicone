<?php

namespace App\Services\Clients;

use App\Services\Interfaces\PineconeClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class PineconeClient implements PineconeClientInterface
{
    protected Client $client;
    protected string $apiKey;
    protected string $environment;
    protected string $index;
    protected string $namespace;
    protected int $timeout;

    /**
     * PineconeClient constructor.
     */
    public function __construct()
    {
        $this->apiKey = config('pinecone.api_key');
        $this->environment = config('pinecone.environment');
        $this->index = config('pinecone.index');
        $this->namespace = config('pinecone.namespace', '');
        $this->timeout = config('pinecone.timeout', 30);

        $this->initializeClient();
    }

    /**
     * Initialize the HTTP client
     */
    protected function initializeClient(): void
    {
        $this->client = new Client([
            'base_uri' => "https://{$this->index}-{$this->environment}.svc.{$this->environment}.pinecone.io/",
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => $this->timeout,
            'verify' => false, // Consider using proper SSL certificates in production
            'http_errors' => true,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function query(array $vector, int $topK = 10, array $filter = []): array
    {
        try {
            $requestData = [
                'vector' => $vector,
                'topK' => $topK,
                'includeMetadata' => true,
                'includeValues' => false,
                'namespace' => $this->namespace,
            ];

            if (!empty($filter)) {
                $requestData['filter'] = $filter;
            }

            Log::debug('Pinecone query request', [
                'vector_size' => count($vector),
                'topK' => $topK,
                'namespace' => $this->namespace
            ]);

            $response = $this->client->post('query', [
                'json' => $requestData,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::debug('Pinecone query response', [
                'status' => $response->getStatusCode(),
                'matches_count' => count($result['matches'] ?? []),
            ]);

            return $result;

        } catch (GuzzleException $e) {
            $this->handleException('query', $e, [
                'vector_size' => count($vector),
                'topK' => $topK,
                'filter' => $filter,
            ]);
        }
    }

    /**
     * @inheritDoc
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
            $this->handleException('upsert', $e, [
                'vectors_count' => count($vectors),
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function getVector(string $id, bool $includeValues = false): array
    {
        try {
            $url = "vectors/fetch?ids={$id}";
            if ($this->namespace) {
                $url .= "&namespace={$this->namespace}";
            }

            Log::debug('Fetching vector', [
                'id' => $id,
                'namespace' => $this->namespace,
                'include_values' => $includeValues
            ]);

            $response = $this->client->get($url);
            $result = json_decode($response->getBody()->getContents(), true);

            if (!empty($result['vectors'][$id]) && !$includeValues) {
                unset($result['vectors'][$id]['values']);
            }

            return $result;

        } catch (GuzzleException $e) {
            $this->handleException('getVector', $e, [
                'id' => $id,
                'include_values' => $includeValues,
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function getDebugInfo(): array
    {
        $result = [
            'pinecone' => [
                'api_key_configured' => !empty($this->apiKey),
                'environment' => $this->environment,
                'index' => $this->index,
                'namespace' => $this->namespace,
                'timeout' => $this->timeout,
            ],
            'environment' => [
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
            ]
        ];

        try {
            // Get index stats
            $statsResponse = $this->client->get('describe_index_stats');
            $result['pinecone']['stats'] = json_decode($statsResponse->getBody()->getContents(), true);
            
            // Get sample vectors (limited to 5 for performance)
            $listResponse = $this->client->post('vectors/list', [
                'json' => [
                    'namespace' => $this->namespace,
                    'includeMetadata' => true,
                    'limit' => 5
                ]
            ]);
            
            $listResult = json_decode($listResponse->getBody()->getContents(), true);
            
            if (!empty($listResult['vectors'])) {
                $result['sample_vectors'] = $listResult['vectors'];
                $metadataKeys = [];
                foreach ($listResult['vectors'] as $vector) {
                    if (isset($vector['metadata'])) {
                        $metadataKeys = array_merge($metadataKeys, array_keys($vector['metadata']));
                    }
                }
                $result['metadata_keys'] = array_values(array_unique($metadataKeys));
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Pinecone debug error: ' . $e->getMessage());
            $result['error'] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Handle API exceptions
     *
     * @param string $operation
     * @param \Throwable $e
     * @param array $context
     * @throws \RuntimeException
     */
    protected function handleException(string $operation, \Throwable $e, array $context = []): void
    {
        $errorMessage = "Pinecone {$operation} failed: " . $e->getMessage();
        
        if (method_exists($e, 'getResponse')) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 'unknown';
            $errorBody = $response ? $response->getBody()->getContents() : 'No response body';
            
            Log::error($errorMessage, array_merge($context, [
                'status_code' => $statusCode,
                'response' => $errorBody,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]));
            
            throw new \RuntimeException(
                "Pinecone API error ({$statusCode}): " . ($errorBody ?: $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
        
        Log::error($errorMessage, array_merge($context, [
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]));
        
        throw new \RuntimeException("Pinecone operation failed: " . $e->getMessage(), $e->getCode(), $e);
    }
}
