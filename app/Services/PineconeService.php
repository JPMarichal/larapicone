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

    /**
     * Get debug information about the Pinecone index
     * 
     * @return array
     * @throws \Exception
     */
    public function getDebugInfo(): array
    {
        try {
            // Get index stats
            $statsResponse = $this->client->get('describe_index_stats');
            $stats = json_decode($statsResponse->getBody()->getContents(), true);
            
            // Get list of vectors (just the first few)
            $listResponse = $this->client->post('vectors/list', [
                'json' => [
                    'namespace' => $this->namespace,
                    'includeMetadata' => true,
                    'limit' => 5  // Just get a few for debugging
                ]
            ]);
            
            $listResult = json_decode($listResponse->getBody()->getContents(), true);
            
            return [
                'stats' => $stats,
                'sample_vectors' => $listResult['vectors'] ?? []
            ];
        } catch (\Exception $e) {
            Log::error('Pinecone debug error: ' . $e->getMessage());
            throw new \Exception('Error getting debug info from Pinecone: ' . $e->getMessage(), 0, $e);
        }
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
    public function getVector(string $id, bool $includeValues = false): array
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
    /**
     * Get vector by reference from metadata
     *
     * @param string $reference
     * @param bool $includeValues
     * @return array
     * @throws \Exception
     */
    /**
     * Get a sample vector to use for queries
     */
    private function getSampleVector(string $namespace): array
    {
        try {
            // First try to list vectors to get a sample
            $response = $this->client->post('vectors/list', [
                'json' => [
                    'namespace' => $namespace,
                    'limit' => 1,
                    'includeMetadata' => true,
                    'includeValues' => true
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            if (!empty($result['vectors'])) {
                // Convert the vector format to match what we expect
                $vector = $result['vectors'][0];
                return [
                    'id' => $vector['id'],
                    'values' => $vector['values'] ?? [],
                    'metadata' => $vector['metadata'] ?? []
                ];
            }
            
            // If no vectors found, try to get index stats to find a vector ID
            $statsResponse = $this->client->get('describe_index_stats');
            $stats = json_decode($statsResponse->getBody()->getContents(), true);
            
            if (!empty($stats['namespaces'][$namespace]['vectorCount'])) {
                // If we have vectors but can't list them, we need to know at least one ID
                // For now, we'll throw an error with instructions
                throw new \Exception(sprintf(
                    'Vectors exist in namespace %s but could not be listed. ' . 
                    'Please provide at least one vector ID to use as a sample.',
                    $namespace
                ));
            }
            
            throw new \Exception('No vectors found in namespace: ' . $namespace);
        } catch (\Exception $e) {
            Log::error('Error getting sample vector: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Convert a reference like 'Génesis 6:10' to a vector ID like 'AT-genesis-06-010'
     */
    private function referenceToId(string $reference): string
    {
        // Example: 'Génesis 6:10' -> 'AT-genesis-06-010'
        $parts = explode(' ', $reference, 2);
        if (count($parts) < 2) {
            throw new \Exception('Invalid reference format. Expected format: "Libro 1:1"');
        }
        
        $book = $this->normalizeBookName($parts[0]);
        $chapterVerse = $parts[1];
        
        // Handle chapter:verse format
        if (str_contains($chapterVerse, ':')) {
            list($chapter, $verse) = explode(':', $chapterVerse);
            $chapter = str_pad($chapter, 2, '0', STR_PAD_LEFT);
            $verse = str_pad($verse, 3, '0', STR_PAD_LEFT);
            return sprintf('AT-%s-%s-%s', $book, $chapter, $verse);
        }
        
        // If no verse, just use chapter
        $chapter = str_pad($chapterVerse, 2, '0', STR_PAD_LEFT);
        return sprintf('AT-%s-%s-000', $book, $chapter);
    }
    
    /**
     * Normalize book name to ID format (e.g., 'Génesis' -> 'genesis')
     */
    private function normalizeBookName(string $book): string
    {
        $book = mb_strtolower($book);
        $book = iconv('UTF-8', 'ASCII//TRANSLIT', $book); // Remove accents
        $book = preg_replace('/[^a-z0-9]/', '', $book); // Remove non-alphanumeric
        return $book;
    }

    public function getVectorByReference(string $reference, bool $includeValues = false): array
    {
        try {
            // Convert reference to ID format (e.g., 'Génesis 6:10' -> 'AT-genesis-06-010')
            $vectorId = $this->referenceToId($reference);
            
            Log::info('Searching for vector by reference', [
                'reference' => $reference,
                'vector_id' => $vectorId,
                'namespace' => $this->namespace
            ]);
            
            // First, try with the main namespace
            try {
                $result = $this->getVector($vectorId, $includeValues);
                
                // Check if we got a valid vector
                if (!empty($result['vectors'][$vectorId])) {
                    return $result;
                }
            } catch (\Exception $e) {
                Log::warning('Error getting vector from main namespace: ' . $e->getMessage());
            }
            
            // If not found, try the test namespace
            try {
                $currentNamespace = $this->namespace;
                $this->namespace = 'test'; // Temporarily change namespace
                $result = $this->getVector($vectorId, $includeValues);
                $this->namespace = $currentNamespace; // Restore original namespace
                
                // Check if we got a valid vector
                if (!empty($result['vectors'][$vectorId])) {
                    return $result;
                }
            } catch (\Exception $e) {
                Log::warning('Error getting vector from test namespace: ' . $e->getMessage());
                // Restore original namespace if there was an error
                if (isset($currentNamespace)) {
                    $this->namespace = $currentNamespace;
                }
            }
            
            throw new \Exception('No vector found with reference: ' . $reference);
        } catch (\Exception $e) {
            Log::error('Pinecone get vector by reference error: ' . $e->getMessage());
            throw new \Exception('Error getting vector by reference from Pinecone: ' . $e->getMessage(), 0, $e);
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
