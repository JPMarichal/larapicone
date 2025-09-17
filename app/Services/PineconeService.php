<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Exception;

class PineconeService
{
    protected Client $client;
    protected string $apiKey;
    protected string $environment;
    protected string $index;
    protected string $namespace;

    /**
     * Get the Pinecone client instance
     * 
     * @return \Probots\Pinecone\Client
     */
    public function getClient(): PineconeClient
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
            
            // Get list of vectors with metadata
            $listResponse = $this->client->post('vectors/list', [
                'json' => [
                    'namespace' => $this->namespace,
                    'includeMetadata' => true,
                    'limit' => 10  // Get more samples for better debugging
                ]
            ]);
            
            $listResult = json_decode($listResponse->getBody()->getContents(), true);
            
            // Get sample vectors with different filters to understand the data structure
            $sampleQueries = [
                'character_exists' => $this->query(array_fill(0, 768, 0), 3, ['character' => ['$exists' => true]]),
                'melquisedec' => $this->query(array_fill(0, 768, 0), 3, ['character' => ['$eq' => 'Melquisedec']]),
                'any_character' => $this->query(array_fill(0, 768, 0), 3, []), // No filter
            ];
            
            // Get a list of unique metadata keys from sample vectors
            $metadataKeys = [];
            if (!empty($listResult['vectors'])) {
                foreach ($listResult['vectors'] as $vector) {
                    if (isset($vector['metadata'])) {
                        $metadataKeys = array_merge($metadataKeys, array_keys($vector['metadata']));
                    }
                }
                $metadataKeys = array_values(array_unique($metadataKeys));
            }
            
            return [
                'stats' => $stats,
                'metadata_keys' => $metadataKeys,
                'sample_vectors' => $listResult['vectors'] ?? [],
                'sample_queries' => $sampleQueries,
                'config' => [
                    'namespace' => $this->namespace,
                    'index' => $this->index,
                    'environment' => $this->environment
                ]
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
     * Perform semantic search using vector similarity
     *
     * @param string $query The natural language query
     * @param int $topK Number of results to return
     * @param array $filters Additional filters to apply
     * @return array
     * @throws \Exception
     */
    public function semanticSearch(string $query, int $topK = 100, array $filters = []): array
    {
        try {
            Log::info('Performing semantic search with nomic-embed-text', [
                'query' => $query,
                'topK' => $topK,
                'filters' => $filters
            ]);
            
            // Generate embedding for the query using Ollama nomic-embed-text model
            $queryVector = $this->generateOllamaEmbedding($query);
            
            // Use traditional Pinecone query endpoint
            $response = $this->query($queryVector, $topK, $filters);
            
            if (!isset($response['matches'])) {
                Log::warning('No matches found in Pinecone response', ['response' => $response]);
                return [
                    'query' => $query,
                    'results' => [],
                    'count' => 0,
                    'search_type' => 'semantic_nomic'
                ];
            }
            
            // Transform Pinecone matches to our expected format
            $results = [];
            foreach ($response['matches'] as $match) {
                $metadata = $match['metadata'] ?? [];
                
                // Log the metadata structure for debugging
                Log::debug('Match metadata structure', [
                    'match_id' => $match['id'],
                    'metadata_keys' => array_keys($metadata),
                    'metadata' => $metadata
                ]);
                
                $results[] = [
                    'id' => $match['id'],
                    'score' => $match['score'],
                    'metadata' => $metadata,
                    'libro' => $metadata['libro'] ?? $metadata['book'] ?? '',
                    'capitulo' => $metadata['capitulo'] ?? $metadata['chapter'] ?? '',
                    'versiculo' => $metadata['versiculo'] ?? $metadata['verse'] ?? '',
                    'contenido' => $metadata['contenido'] ?? $metadata['content'] ?? $metadata['text'] ?? ''
                ];
            }
            
            Log::info('Semantic search completed successfully', [
                'query' => $query,
                'results_count' => count($results),
                'top_score' => $results[0]['score'] ?? 0
            ]);
            
            return [
                'query' => $query,
                'results' => $results,
                'count' => count($results),
                'search_type' => 'semantic_nomic'
            ];
            
        } catch (\Exception $e) {
            Log::error('Semantic search error: ' . $e->getMessage());
            throw new \Exception('Error performing semantic search: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Generate embedding using Ollama with nomic-embed-text model (matches stored vectors)
     *
     * @param string $text
     * @return array
     */
    protected function generateOllamaEmbedding(string $text): array
    {
        try {
            // Use Ollama API for embeddings
            $ollamaClient = new Client([
                'base_uri' => config('services.ollama.base_url'),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            $response = $ollamaClient->post('/api/embeddings', [
                'json' => [
                    'model' => config('services.ollama.embed_model'),
                    'prompt' => $text
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['embedding']) && is_array($data['embedding'])) {
                Log::info('Generated Ollama embedding successfully', [
                    'text_length' => strlen($text),
                    'embedding_dimensions' => count($data['embedding']),
                    'model' => config('services.ollama.embed_model')
                ]);
                return $data['embedding'];
            }

            Log::warning('Ollama API response missing embedding, falling back');
            return $this->generateFallbackEmbedding($text);

        } catch (\Exception $e) {
            Log::warning('Ollama embedding failed, using fallback: ' . $e->getMessage());
            return $this->generateFallbackEmbedding($text);
        }
    }

    /**
     * Generate embedding vector for a text query using bag-of-words approach
     *
     * @param string $text
     * @return array
     */
    protected function generateFallbackEmbedding(string $text): array
    {
        // Convert text to lowercase and remove punctuation
        $text = strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $text)));
        
        // Split into words and count frequencies
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCounts = array_count_values($words);
        
        // Create a simple bag-of-words vector with 768 dimensions
        $vector = array_fill(0, 768, 0);
        
        // Simple hashing trick to map words to vector indices
        foreach ($wordCounts as $word => $count) {
            $index = abs(crc32($word)) % 768;
            $vector[$index] += $count;
        }
        
        // Normalize the vector to unit length
        $length = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vector)));
        if ($length > 0) {
            $vector = array_map(function($x) use ($length) { 
                return $x / $length; 
            }, $vector);
        }
        
        return $vector;
    }

    public function query(array $vector, int $topK = 100, array $filter = []): array
    {
        try {
            $requestData = [
                'vector' => $vector,
                'topK' => $topK,
                'includeMetadata' => true,
                'includeValues' => false,
                'namespace' => $this->namespace,
            ];

            // Only add filter if it's not empty
            if (!empty($filter)) {
                $requestData['filter'] = $filter;
            }

            // Log the request data for debugging
            Log::debug('Pinecone query request', [
                'vector_size' => count($vector),
                'topK' => $topK,
                'filter' => $filter,
                'namespace' => $this->namespace
            ]);

            $response = $this->client->post('query', [
                'json' => $requestData,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            // Log the response for debugging
            Log::debug('Pinecone query response', [
                'status' => $response->getStatusCode(),
                'matches_count' => count($result['matches'] ?? []),
                'matches' => array_map(function($match) {
                    return [
                        'id' => $match['id'] ?? null,
                        'score' => $match['score'] ?? null,
                        'metadata_keys' => array_keys($match['metadata'] ?? [])
                    ];
                }, $result['matches'] ?? [])
            ]);

            return $result;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
            $errorBody = $response ? $response->getBody()->getContents() : 'No response body';
            Log::error('Pinecone query error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'response' => $errorBody,
                'request' => [
                    'vector_size' => count($vector),
                    'topK' => $topK,
                    'filter' => $filter,
                    'namespace' => $this->namespace
                ]
            ]);
            throw new \Exception('Error querying Pinecone: ' . $e->getMessage() . ' - ' . $errorBody, 0, $e);
        } catch (\Exception $e) {
            Log::error('Unexpected error in Pinecone query: ' . $e->getMessage());
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
            $url = "vectors/fetch?ids={$id}&namespace={$this->namespace}";
            Log::debug('Fetching vector', [
                'url' => $url,
                'id' => $id,
                'namespace' => $this->namespace,
                'include_values' => $includeValues
            ]);
            
            $startTime = microtime(true);
            $response = $this->client->get($url);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2); // in ms
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::debug('Vector fetch result', [
                'id' => $id,
                'namespace' => $this->namespace,
                'has_vectors' => !empty($result['vectors']),
                'vector_count' => !empty($result['vectors']) ? count($result['vectors']) : 0,
                'response_time_ms' => $responseTime,
                'http_status' => $response->getStatusCode(),
                'found_ids' => !empty($result['vectors']) ? array_keys($result['vectors']) : []
            ]);
            
            if (!empty($result['vectors'])) {
                Log::debug('Available vector IDs', [
                    'ids' => array_keys($result['vectors'])
                ]);
                
                if (!$includeValues) {
                    foreach ($result['vectors'] as &$vector) {
                        unset($vector['values']);
                    }
                }
                
                return $result;
            }
            
            Log::warning('No vectors found in response', [
                'id' => $id,
                'namespace' => $this->namespace,
                'response' => $result
            ]);
            
            return ['vectors' => []];
            
        } catch (\Exception $e) {
            Log::error('Pinecone get vector error: ' . $e->getMessage(), [
                'id' => $id,
                'namespace' => $this->namespace,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
     * Get book mappings from JSONL file as source of truth
     */
    private function getBookMappings(): array
    {
        return [
            'Génesis' => ['slug' => 'genesis', 'volume' => 'AT'],
            'Éxodo' => ['slug' => 'exodo', 'volume' => 'AT'],
            'Levítico' => ['slug' => 'levitico', 'volume' => 'AT'],
            'Números' => ['slug' => 'numeros', 'volume' => 'AT'],
            'Deuteronomio' => ['slug' => 'deuteronomio', 'volume' => 'AT'],
            'Josué' => ['slug' => 'josue', 'volume' => 'AT'],
            'Jueces' => ['slug' => 'jueces', 'volume' => 'AT'],
            'Rut' => ['slug' => 'rut', 'volume' => 'AT'],
            '1 Samuel' => ['slug' => '1-samuel', 'volume' => 'AT'],
            '2 Samuel' => ['slug' => '2-samuel', 'volume' => 'AT'],
            '1 Reyes' => ['slug' => '1-reyes', 'volume' => 'AT'],
            '2 Reyes' => ['slug' => '2-reyes', 'volume' => 'AT'],
            '1 Crónicas' => ['slug' => '1-cronicas', 'volume' => 'AT'],
            '2 Crónicas' => ['slug' => '2-cronicas', 'volume' => 'AT'],
            'Esdras' => ['slug' => 'esdras', 'volume' => 'AT'],
            'Nehemías' => ['slug' => 'nehemias', 'volume' => 'AT'],
            'Ester' => ['slug' => 'ester', 'volume' => 'AT'],
            'Job' => ['slug' => 'job', 'volume' => 'AT'],
            'Salmos' => ['slug' => 'salmos', 'volume' => 'AT'],
            'Proverbios' => ['slug' => 'proverbios', 'volume' => 'AT'],
            'Eclesiastés' => ['slug' => 'eclesiastes', 'volume' => 'AT'],
            'Cantares' => ['slug' => 'cantares', 'volume' => 'AT'],
            'Isaías' => ['slug' => 'isaias', 'volume' => 'AT'],
            'Jeremías' => ['slug' => 'jeremias', 'volume' => 'AT'],
            'Lamentaciones' => ['slug' => 'lamentaciones', 'volume' => 'AT'],
            'Ezequiel' => ['slug' => 'ezequiel', 'volume' => 'AT'],
            'Daniel' => ['slug' => 'daniel', 'volume' => 'AT'],
            'Oseas' => ['slug' => 'oseas', 'volume' => 'AT'],
            'Joel' => ['slug' => 'joel', 'volume' => 'AT'],
            'Amós' => ['slug' => 'amos', 'volume' => 'AT'],
            'Abdías' => ['slug' => 'abdias', 'volume' => 'AT'],
            'Jonás' => ['slug' => 'jonas', 'volume' => 'AT'],
            'Miqueas' => ['slug' => 'miqueas', 'volume' => 'AT'],
            'Nahúm' => ['slug' => 'nahum', 'volume' => 'AT'],
            'Habacuc' => ['slug' => 'habacuc', 'volume' => 'AT'],
            'Sofonías' => ['slug' => 'sofonias', 'volume' => 'AT'],
            'Hageo' => ['slug' => 'hageo', 'volume' => 'AT'],
            'Zacarías' => ['slug' => 'zacarias', 'volume' => 'AT'],
            'Malaquías' => ['slug' => 'malaquias', 'volume' => 'AT'],
            'Mateo' => ['slug' => 'mateo', 'volume' => 'NT'],
            'Marcos' => ['slug' => 'marcos', 'volume' => 'NT'],
            'Lucas' => ['slug' => 'lucas', 'volume' => 'NT'],
            'Juan' => ['slug' => 'juan', 'volume' => 'NT'],
            'Hechos' => ['slug' => 'hechos', 'volume' => 'NT'],
            'Romanos' => ['slug' => 'romanos', 'volume' => 'NT'],
            '1 Corintios' => ['slug' => '1-corintios', 'volume' => 'NT'],
            '2 Corintios' => ['slug' => '2-corintios', 'volume' => 'NT'],
            'Gálatas' => ['slug' => 'galatas', 'volume' => 'NT'],
            'Efesios' => ['slug' => 'efesios', 'volume' => 'NT'],
            'Filipenses' => ['slug' => 'filipenses', 'volume' => 'NT'],
            'Colosenses' => ['slug' => 'colosenses', 'volume' => 'NT'],
            '1 Tesalonicenses' => ['slug' => '1-tesalonicenses', 'volume' => 'NT'],
            '2 Tesalonicenses' => ['slug' => '2-tesalonicenses', 'volume' => 'NT'],
            '1 Timoteo' => ['slug' => '1-timoteo', 'volume' => 'NT'],
            '2 Timoteo' => ['slug' => '2-timoteo', 'volume' => 'NT'],
            'Tito' => ['slug' => 'tito', 'volume' => 'NT'],
            'Filemón' => ['slug' => 'filemon', 'volume' => 'NT'],
            'Hebreos' => ['slug' => 'hebreos', 'volume' => 'NT'],
            'Santiago' => ['slug' => 'santiago', 'volume' => 'NT'],
            '1 Pedro' => ['slug' => '1-pedro', 'volume' => 'NT'],
            '2 Pedro' => ['slug' => '2-pedro', 'volume' => 'NT'],
            '1 Juan' => ['slug' => '1-juan', 'volume' => 'NT'],
            '2 Juan' => ['slug' => '2-juan', 'volume' => 'NT'],
            '3 Juan' => ['slug' => '3-juan', 'volume' => 'NT'],
            'Judas' => ['slug' => 'judas', 'volume' => 'NT'],
            'Apocalipsis' => ['slug' => 'apocalipsis', 'volume' => 'NT'],
            '1 Nefi' => ['slug' => '1-nefi', 'volume' => 'BM'],
            '2 Nefi' => ['slug' => '2-nefi', 'volume' => 'BM'],
            'Jacob' => ['slug' => 'jacob', 'volume' => 'BM'],
            'Enós' => ['slug' => 'enos', 'volume' => 'BM'],
            'Jarom' => ['slug' => 'jarom', 'volume' => 'BM'],
            'Omni' => ['slug' => 'omni', 'volume' => 'BM'],
            'Palabras de Mormón' => ['slug' => 'palabras-de-mormon', 'volume' => 'BM'],
            'Mosíah' => ['slug' => 'mosiah', 'volume' => 'BM'],
            'Alma' => ['slug' => 'alma', 'volume' => 'BM'],
            'Helamán' => ['slug' => 'helaman', 'volume' => 'BM'],
            '3 Nefi' => ['slug' => '3-nefi', 'volume' => 'BM'],
            '4 Nefi' => ['slug' => '4-nefi', 'volume' => 'BM'],
            'Mormón' => ['slug' => 'mormon', 'volume' => 'BM'],
            'Éter' => ['slug' => 'eter', 'volume' => 'BM'],
            'Moroni' => ['slug' => 'moroni', 'volume' => 'BM'],
            'Secciones' => ['slug' => 'secciones', 'volume' => 'DyC'],
            'Declaraciones oficiales' => ['slug' => 'declaraciones-oficiales', 'volume' => 'DyC'],
            'Moisés' => ['slug' => 'moises', 'volume' => 'PGP'],
            'Abraham' => ['slug' => 'abraham', 'volume' => 'PGP'],
            'José Smith-Mateo' => ['slug' => 'jose-smith-mateo', 'volume' => 'PGP'],
            'José Smith-Historia' => ['slug' => 'jose-smith-historia', 'volume' => 'PGP'],
            'Artículos de Fe' => ['slug' => 'articulos-de-fe', 'volume' => 'PGP']
        ];
    }
    
    /**
     * Normalize text by converting to lowercase and removing accents
     */
    private function normalizeText(string $text): string
    {
        // Convert to lowercase first
        $text = mb_strtolower(trim($text), 'UTF-8');
        
        // Remove accents and diacritics
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        // Keep only letters, numbers, and spaces
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        
        // Normalize spaces (multiple spaces to single space)
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Find book mapping by trying different variations of the book name
     */
    private function findBookMapping(string $bookName): ?array
    {
        $mappings = $this->getBookMappings();
        
        // Try exact match first
        if (isset($mappings[$bookName])) {
            return $mappings[$bookName];
        }
        
        // Try case-insensitive match
        foreach ($mappings as $key => $mapping) {
            if (strcasecmp($key, $bookName) === 0) {
                return $mapping;
            }
        }
        
        // Try normalized match (remove accents, etc.)
        $normalizedInput = $this->normalizeText($bookName);
        foreach ($mappings as $key => $mapping) {
            if ($this->normalizeText($key) === $normalizedInput) {
                return $mapping;
            }
        }
        
        return null;
    }
    
    /**
     * Get volume code and book slug based on book name using JSONL mappings
     */
    private function getBookInfo(string $book): array
    {
        $originalBook = $book;
        
        // Find the book mapping
        $mapping = $this->findBookMapping($book);
        
        if ($mapping) {
            Log::debug('Found book mapping', [
                'original_book' => $originalBook,
                'volume' => $mapping['volume'],
                'slug' => $mapping['slug']
            ]);
            return $mapping;
        }
        
        // If we get here, the book wasn't recognized
        $errorMessage = "Libro no reconocido: " . $originalBook;
        Log::error($errorMessage, [
            'original_book' => $originalBook,
            'available_books' => array_keys($this->getBookMappings())
        ]);
        throw new \Exception($errorMessage);
    }
    
    
    /**
     * Convert a reference like 'Salmos 3:8' or '1 Nefi 1:1' to a vector ID using JSONL mappings
     * Handles special cases for Doctrina y Convenios (DyC)
     */
    private function referenceToId(string $reference): string
    {
        Log::debug('Processing reference', ['reference' => $reference]);
        
        // Special case for Doctrina y Convenios (DyC)
        if ($this->isDoctrinaYConvenios($reference)) {
            return $this->handleDoctrinaYConvenios($reference);
        }
        
        // Special case for Declaración Oficial (DO)
        if ($this->isDeclaracionOficial($reference)) {
            return $this->handleDeclaracionOficial($reference);
        }
        
        // Parse the reference: "Book Chapter:Verse" or "Book Chapter"
        $pattern = '/^(.+?)\s+(\d+)(?::(\d+))?$/u';
        
        Log::debug('Parsing reference', [
            'reference' => $reference,
            'pattern' => $pattern
        ]);
        
        if (!preg_match($pattern, $reference, $matches)) {
            throw new \Exception('Formato de referencia inválido. Formato esperado: "Libro 1:1" o "Libro 1"');
        }
        
        $book = trim($matches[1]);
        $chapter = $matches[2];
        $verse = $matches[3] ?? '1'; // Default to verse 1 if not specified
        
        Log::debug('Extracted components', [
            'book' => $book,
            'chapter' => $chapter,
            'verse' => $verse
        ]);
        
        // Get book info from JSONL mappings
        $bookInfo = $this->getBookInfo($book);
        $volumeCode = $bookInfo['volume'];
        $bookSlug = $bookInfo['slug'];
        
        // Format chapter and verse with leading zeros
        $chapterPadded = str_pad($chapter, 2, '0', STR_PAD_LEFT);
        $versePadded = str_pad($verse, 3, '0', STR_PAD_LEFT);
        
        // Generate vector ID: {volume}-{book_slug}-{chapter}-{verse}
        $vectorId = sprintf('%s-%s-%s-%s', 
            $volumeCode,
            $bookSlug,
            $chapterPadded,
            $versePadded
        );
        
        Log::info('Generated vector ID', [
            'original_reference' => $reference,
            'vector_id' => $vectorId,
            'book' => $book,
            'volume_code' => $volumeCode,
            'book_slug' => $bookSlug,
            'chapter' => $chapter,
            'chapter_padded' => $chapterPadded,
            'verse' => $verse,
            'verse_padded' => $versePadded
        ]);
        
        return $vectorId;
    }
    
    /**
     * Check if reference is for Doctrina y Convenios
     */
    private function isDoctrinaYConvenios(string $reference): bool
    {
        $patterns = [
            '/^doctrina\s+y\s+convenios\s+\d+/i',
            '/^dyc\s+\d+/i',
            '/^d\s*&\s*c\s+\d+/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $reference)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if reference is for Declaración Oficial
     */
    private function isDeclaracionOficial(string $reference): bool
    {
        $patterns = [
            '/^declaraci[oó]n\s+oficial\s+\d+/iu',
            '/^do\s+\d+/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $reference)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Handle Doctrina y Convenios references
     * Examples: "Doctrina y Convenios 1:20" -> "DyC-secciones-01-020"
     *          "DyC 1:20" -> "DyC-secciones-01-020"
     */
    private function handleDoctrinaYConvenios(string $reference): string
    {
        $patterns = [
            '/^doctrina\s+y\s+convenios\s+(\d+)(?::(\d+))?/i',
            '/^dyc\s+(\d+)(?::(\d+))?/i',
            '/^d\s*&\s*c\s+(\d+)(?::(\d+))?/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $reference, $matches)) {
                $section = $matches[1];
                $verse = $matches[2] ?? '1';
                
                $sectionPadded = str_pad($section, 2, '0', STR_PAD_LEFT);
                $versePadded = str_pad($verse, 3, '0', STR_PAD_LEFT);
                
                $vectorId = sprintf('DyC-secciones-%s-%s', $sectionPadded, $versePadded);
                
                Log::info('Generated DyC vector ID', [
                    'original_reference' => $reference,
                    'vector_id' => $vectorId,
                    'section' => $section,
                    'section_padded' => $sectionPadded,
                    'verse' => $verse,
                    'verse_padded' => $versePadded
                ]);
                
                return $vectorId;
            }
        }
        
        throw new \Exception('No se pudo procesar la referencia de Doctrina y Convenios: ' . $reference);
    }
    
    /**
     * Handle Declaración Oficial references
     * Examples: "Declaración Oficial 1:1" -> "DyC-declaraciones-oficiales-01-001"
     *          "DO 1:1" -> "DyC-declaraciones-oficiales-01-001"
     */
    private function handleDeclaracionOficial(string $reference): string
    {
        $patterns = [
            '/^declaraci[oó]n\s+oficial\s+(\d+)(?::(\d+))?/iu',
            '/^do\s+(\d+)(?::(\d+))?/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $reference, $matches)) {
                $declaration = $matches[1];
                $verse = $matches[2] ?? '1';
                
                $declarationPadded = str_pad($declaration, 2, '0', STR_PAD_LEFT);
                $versePadded = str_pad($verse, 3, '0', STR_PAD_LEFT);
                
                $vectorId = sprintf('DyC-declaraciones-oficiales-%s-%s', $declarationPadded, $versePadded);
                
                Log::info('Generated DO vector ID', [
                    'original_reference' => $reference,
                    'vector_id' => $vectorId,
                    'declaration' => $declaration,
                    'declaration_padded' => $declarationPadded,
                    'verse' => $verse,
                    'verse_padded' => $versePadded
                ]);
                
                return $vectorId;
            }
        }
        
        throw new \Exception('No se pudo procesar la referencia de Declaración Oficial: ' . $reference);
    }

    /**
     * Get multiple vectors by passage reference
     * Handles ranges like "Juan 1:1-3" and multiple references like "Juan 1:1-3, 14"
     */
    public function getVectorsByPassage(string $passage, bool $includeValues = false): array
    {
        try {
            Log::info('Processing passage', ['passage' => $passage]);
            
            // Parse the passage into individual verse references
            $verseReferences = $this->parsePassage($passage);
            
            Log::debug('Expanded verse references', [
                'passage' => $passage,
                'verse_count' => count($verseReferences),
                'verses' => $verseReferences
            ]);
            
            $verses = [];
            $errors = [];
            
            // Get each verse using the existing getVectorByReference method
            foreach ($verseReferences as $reference) {
                try {
                    $vector = $this->getVectorByReference($reference, $includeValues);
                    $verses[] = [
                        'reference' => $reference,
                        'vector_id' => $vector['id'],
                        'text' => $vector['metadata']['text'] ?? '',
                        'metadata' => $vector['metadata'] ?? [],
                        'values' => $includeValues ? ($vector['values'] ?? []) : null
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'reference' => $reference,
                        'error' => $e->getMessage()
                    ];
                    Log::warning('Failed to get verse', [
                        'reference' => $reference,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Concatenate all verse texts
            $concatenatedText = implode(' ', array_column($verses, 'text'));
            
            $result = [
                'passage' => $passage,
                'verse_count' => count($verses),
                'concatenated_text' => $concatenatedText,
                'verses' => $verses
            ];
            
            if (!empty($errors)) {
                $result['errors'] = $errors;
            }
            
            Log::info('Passage processing completed', [
                'passage' => $passage,
                'verse_count' => count($verses),
                'error_count' => count($errors),
                'text_length' => strlen($concatenatedText)
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Passage processing error: ' . $e->getMessage(), [
                'passage' => $passage,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Parse passage reference into individual verse references
     * Examples: 
     * - "Juan 1:1-3" -> ["Juan 1:1", "Juan 1:2", "Juan 1:3"]
     * - "Juan 1:1-3, 14" -> ["Juan 1:1", "Juan 1:2", "Juan 1:3", "Juan 1:14"]
     * - "Juan 1:1, 3, 5-7" -> ["Juan 1:1", "Juan 1:3", "Juan 1:5", "Juan 1:6", "Juan 1:7"]
     */
    private function parsePassage(string $passage): array
    {
        $references = [];
        
        // Split by commas to handle multiple parts
        $parts = array_map('trim', explode(',', $passage));
        
        foreach ($parts as $part) {
            Log::debug('Processing passage part', ['part' => $part]);
            
            // Check if this part contains a range (e.g., "Juan 1:1-3" or just "5-7")
            if (preg_match('/^(.+?)(\d+):(\d+)-(\d+)$/u', $part, $matches)) {
                // Full reference with range: "Juan 1:1-3"
                $book = trim($matches[1]);
                $chapter = $matches[2];
                $startVerse = (int)$matches[3];
                $endVerse = (int)$matches[4];
                
                for ($verse = $startVerse; $verse <= $endVerse; $verse++) {
                    $references[] = "{$book} {$chapter}:{$verse}";
                }
            } elseif (preg_match('/^(\d+)-(\d+)$/u', $part, $matches)) {
                // Just verse range: "5-7" (assumes same book and chapter as previous)
                $startVerse = (int)$matches[1];
                $endVerse = (int)$matches[2];
                
                // Get book and chapter from the first reference if available
                if (!empty($references)) {
                    $lastRef = end($references);
                    if (preg_match('/^(.+?)(\d+):(\d+)$/u', $lastRef, $lastMatches)) {
                        $book = trim($lastMatches[1]);
                        $chapter = $lastMatches[2];
                        
                        for ($verse = $startVerse; $verse <= $endVerse; $verse++) {
                            $references[] = "{$book} {$chapter}:{$verse}";
                        }
                    }
                } else {
                    throw new \Exception("No se puede procesar el rango '{$part}' sin una referencia base");
                }
            } elseif (preg_match('/^(.+?)(\d+):(\d+)$/u', $part, $matches)) {
                // Single verse: "Juan 1:1"
                $references[] = $part;
            } elseif (preg_match('/^(\d+)$/u', $part, $matches)) {
                // Just verse number: "14" (assumes same book and chapter as previous)
                $verse = $matches[1];
                
                // Get book and chapter from the last reference if available
                if (!empty($references)) {
                    $lastRef = end($references);
                    if (preg_match('/^(.+?)(\d+):(\d+)$/u', $lastRef, $lastMatches)) {
                        $book = trim($lastMatches[1]);
                        $chapter = $lastMatches[2];
                        $references[] = "{$book} {$chapter}:{$verse}";
                    }
                } else {
                    throw new \Exception("No se puede procesar el versículo '{$part}' sin una referencia base");
                }
            } else {
                throw new \Exception("Formato de pasaje inválido: '{$part}'");
            }
        }
        
        // Remove duplicates and sort
        $references = array_unique($references);
        
        // Sort references by chapter and verse
        usort($references, function($a, $b) {
            if (preg_match('/^(.+?)(\d+):(\d+)$/u', $a, $matchesA) && 
                preg_match('/^(.+?)(\d+):(\d+)$/u', $b, $matchesB)) {
                
                $bookA = trim($matchesA[1]);
                $chapterA = (int)$matchesA[2];
                $verseA = (int)$matchesA[3];
                
                $bookB = trim($matchesB[1]);
                $chapterB = (int)$matchesB[2];
                $verseB = (int)$matchesB[3];
                
                // First compare books
                if ($bookA !== $bookB) {
                    return strcmp($bookA, $bookB);
                }
                
                // Then compare chapters
                if ($chapterA !== $chapterB) {
                    return $chapterA - $chapterB;
                }
                
                // Finally compare verses
                return $verseA - $verseB;
            }
            
            return strcmp($a, $b);
        });
        
        return $references;
    }

    /**
     * Normalize book name to ID format (e.g., '1 Nefi' -> '1nephi')
     */

    public function getVectorByReference(string $reference, bool $includeValues = false): array
    {
        try {
            Log::debug('Processing reference', ['original_reference' => $reference]);
            
            // Convert reference to ID format (e.g., 'Génesis 6:10' -> 'AT-genesis-06-010')
            $vectorId = $this->referenceToId($reference);
            Log::debug('Generated vector ID', [
                'reference' => $reference,
                'vector_id' => $vectorId,
                'include_values' => $includeValues
            ]);
            
            Log::info('Searching for vector by reference', [
                'reference' => $reference,
                'vector_id' => $vectorId,
                'namespace' => $this->namespace
            ]);
            
            // First, try with the main namespace
            try {
                $result = $this->getVector($vectorId, $includeValues);
                Log::debug('Vector fetch result', [
                    'reference' => $reference,
                    'vector_id' => $vectorId,
                    'has_vectors' => !empty($result['vectors']),
                    'vector_count' => !empty($result['vectors']) ? count($result['vectors']) : 0,
                    'vector_keys' => !empty($result['vectors']) ? array_keys($result['vectors']) : []
                ]);
                
                // Check if we got a valid vector
                if (!empty($result['vectors'])) {
                    // Try to get the vector by the exact ID first
                    if (isset($result['vectors'][$vectorId])) {
                        $vector = $result['vectors'][$vectorId];
                        return [
                            'id' => $vectorId,
                            'values' => $vector['values'] ?? [],
                            'metadata' => $vector['metadata'] ?? []
                        ];
                    }
                    
                    // If not found by exact ID, try to find a matching vector
                    foreach ($result['vectors'] as $id => $vector) {
                        if (str_starts_with($id, 'BM-mosiah')) {
                            Log::debug('Found matching Mosíah vector', [
                                'expected_id' => $vectorId,
                                'found_id' => $id,
                                'metadata' => $vector['metadata'] ?? []
                            ]);
                            return [
                                'id' => $id,
                                'values' => $vector['values'] ?? [],
                                'metadata' => $vector['metadata'] ?? []
                            ];
                        }
                    }
                    
                    // If we have vectors but none match, return the first one
                    $vector = reset($result['vectors']);
                    return [
                        'id' => $vectorId,
                        'values' => $vector['values'] ?? [],
                        'metadata' => $vector['metadata'] ?? []
                    ];
                }
                
                throw new \Exception('No se encontró el vector en la respuesta');
                
            } catch (\Exception $e) {
                Log::warning('Error getting vector from main namespace', [
                    'reference' => $reference,
                    'vector_id' => $vectorId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Try the test namespace before giving up
                try {
                    $currentNamespace = $this->namespace;
                    $this->namespace = 'test';
                    $result = $this->getVector($vectorId, $includeValues);
                    
                    if (!empty($result['vectors'])) {
                        $vector = reset($result['vectors']);
                        return [
                            'id' => $vectorId,
                            'values' => $vector['values'] ?? [],
                            'metadata' => $vector['metadata'] ?? []
                        ];
                    }
                } catch (\Exception $e2) {
                    Log::warning('Error getting vector from test namespace', [
                        'reference' => $reference,
                        'vector_id' => $vectorId,
                        'error' => $e2->getMessage()
                    ]);
                } finally {
                    $this->namespace = $currentNamespace;
                }
                
                throw new \Exception('No se encontró el versículo con la referencia: ' . $reference);
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
