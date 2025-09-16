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
     * Normalize text by converting to lowercase and handling special cases
     */
    private function normalizeText(string $text): string
    {
        // Special case for Mosíah to match the ID format in the database
        if (stripos($text, 'mosíah') !== false || stripos($text, 'mosiah') !== false) {
            return 'mosiah';
        }
        
        // Convert to lowercase
        $text = mb_strtolower(trim($text));
        
        // Keep only letters, numbers, and spaces
        $text = preg_replace('/[^a-z0-9\s]/u', '', $text);
        
        // Normalize spaces (multiple spaces to single space)
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Get volume code based on book name
     */
    private function getVolumeCode(string $book): string
    {
        $originalBook = $book;
        
        // Handle common typos in the original input before normalization
        if (preg_match('/^1\s*nefi/i', $book)) {
            $book = '1 nefi';
        } elseif (preg_match('/^2\s*nefi/i', $book)) {
            $book = '2 nefi';
        } elseif (preg_match('/^3\s*nefi/i', $book)) {
            $book = '3 nefi';
        }
        
        // Now normalize the book name
        $book = $this->normalizeText($book);
        
        Log::debug('Volume code lookup', [
            'original_book' => $originalBook,
            'normalized_book' => $book
        ]);
        
        // Check for Book of Mormon books (1 Nefi, 2 Nefi, etc.)
        $bomPatterns = [
            '/^1\s*nephi/', '/^1\s*nefi/', '/^2\s*nephi/', '/^2\s*nefi/', 
            '/^3\s*nephi/', '/^3\s*nefi/', '/^4\s*nephi/',
            '/^jacob/', '/^enos/', '/^jarom/', '/^omni/', 
            '/^palabrasdemormon/', '/^mormon/', '/^eter/', '/^moroni/'
        ];
        
        // Also check for exact matches after normalization
        $normalizedBookNames = [
            '1nephi', '1nefi', '2nephi', '2nefi', '3nephi', '3nefi', '4nephi',
            'jacob', 'enos', 'jarom', 'omni', 'palabrasdemormon', 'mormon', 'eter', 'moroni'
        ];
        
        if (in_array($book, $normalizedBookNames)) {
            return 'BM';
        }
        
        foreach ($bomPatterns as $pattern) {
            if (preg_match($pattern, $book)) {
                Log::debug('Matched Book of Mormon book', ['book' => $book, 'pattern' => $pattern]);
                return 'BM';
            }
        }
        
        // Check for Doctrine and Covenants
        if (preg_match('/^doctrina y convenios|^d\s?y\s?c/', $book)) {
            return 'DC';
        }
        
        // Check for Pearl of Great Price books
        $pgpPatterns = [
            '/^mos/', '/^abrah/', '/^jose smith/', '/^jose smithmat/', 
            '/^jose smithhist/', '/^articulos de fe/'
        ];
        
        foreach ($pgpPatterns as $pattern) {
            if (preg_match($pattern, $book)) {
                return 'PGP';
            }
        }
        
        // List of New Testament books (normalized without diacritics)
        $newTestamentBooks = [
            'mateo', 'marcos', 'lucas', 'juan', 'hechos', 'romanos', '1 corintios', '2 corintios',
            'galatas', 'efesios', 'filipenses', 'colosenses', '1 tesalonicenses', '2 tesalonicenses',
            '1 timoteo', '2 timoteo', 'tito', 'filemon', 'hebreos', 'santiago', '1 pedro', '2 pedro',
            '1 juan', '2 juan', '3 juan', 'judas', 'apocalipsis', 'revelacion'
        ];
        
        foreach ($newTestamentBooks as $ntBook) {
            if (str_starts_with($book, $ntBook)) {
                return 'NT';
            }
        }
        
        // List of Old Testament books (normalized without diacritics)
        $oldTestamentBooks = [
            'genesis', 'exodo', 'levitico', 'numeros', 'deuteronomio', 'josue', 'jueces', 'rut',
            '1 samuel', '2 samuel', '1 reyes', '2 reyes', '1 cronicas', '2 cronicas', 'esdras',
            'nehemias', 'ester', 'job', 'salmos', 'proverbios', 'eclesiastes', 'cantares',
            'isaias', 'jeremias', 'lamentaciones', 'ezequiel', 'daniel', 'oseas', 'joel',
            'amos', 'abdias', 'jonas', 'miqueas', 'nahum', 'habacuc', 'sofonias', 'hageo',
            'zacarias', 'malaquias', 'tobias', 'judit', 'ester griego',
            'sabiduria', 'eclesiastico', 'baruc', '1 macabeos', '2 macabeos', 'daniel griego'
        ];
        
        foreach ($oldTestamentBooks as $otBook) {
            if (str_starts_with($book, $otBook)) {
                return 'AT';
            }
        }
        
        // If we get here, the book wasn't recognized
        $errorMessage = "Libro no reconocido: " . $originalBook . ". Normalized as: " . $book;
        Log::error($errorMessage, [
            'original_book' => $originalBook,
            'normalized_book' => $book,
            'available_volumes' => ['AT', 'NT', 'BM', 'DC', 'PGP']
        ]);
        throw new \Exception($errorMessage);
    }
    
    /**
     * Convert a reference like '1 Nefi 1:1' to a vector ID like 'BM-1-nefi-01-001'
     */
    private function referenceToId(string $reference): string
    {
        // Example: 'Mosíah 3:8' -> 'BM-mosiah-03-008'
        $pattern = '/^((?:\d+\s+)?[\w\s]+?)\s+(\d+)(?::(\d+))?/u';
        
        Log::debug('Extracting book and reference', [
            'reference' => $reference,
            'pattern' => $pattern
        ]);
        
        if (!preg_match($pattern, $reference, $matches)) {
            throw new \Exception('Formato de referencia inválido. Formato esperado: "Libro 1:1" o "Libro 1"');
        }
        
        $book = trim($matches[1]);
        $chapter = $matches[2];
        $verse = $matches[3] ?? '001'; // Default to verse 1 if not specified
        
        Log::debug('Extracted components', [
            'book' => $book,
            'chapter' => $chapter,
            'verse' => $verse
        ]);
        
        $volumeCode = $this->getVolumeCode($book);
        
        // Special handling for Book of Mormon books
        if ($volumeCode === 'BM') {
            // Format: BM-{book_slug}-{chapter_padded}-{verse_padded}
            // Example: BM-mosiah-03-008 for Mosíah 3:8
            
            // Special case for Mosíah
            $bookSlug = strtolower($this->normalizeText($book));
            
            // Format chapter and verse with leading zeros
            $chapterPadded = str_pad($chapter, 2, '0', STR_PAD_LEFT);
            $versePadded = str_pad($verse, 3, '0', STR_PAD_LEFT);
            
            $vectorId = sprintf('BM-%s-%s-%s', 
                $bookSlug,
                $chapterPadded,
                $versePadded
            );
            
            Log::debug('Generated Book of Mormon ID', [
                'original_reference' => $reference,
                'vector_id' => $vectorId,
                'book_slug' => $bookSlug,
                'chapter_padded' => $chapterPadded,
                'verse_padded' => $versePadded
            ]);
            
            return $vectorId;
        }
        
        // Handle other volumes (Old Testament, New Testament, etc.)
        $bookSlug = strtolower($this->normalizeText($book));
        $chapter = str_pad($chapter, 2, '0', STR_PAD_LEFT);
        $verse = str_pad($verse, 3, '0', STR_PAD_LEFT);
        
        return sprintf('%s-%s-%s-%s', $volumeCode, $bookSlug, $chapter, $verse);
    }
    /**
     * Normalize book name to ID format (e.g., '1 Nefi' -> '1nephi')
     */
    private function normalizeBookName(string $book): string
    {
        // First, normalize the text (lowercase, remove accents, etc.)
        $book = $this->normalizeText($book);
        
        // Handle specific book name mappings
        $bookMappings = [
            '/^1\s*nephi/' => '1nephi',
            '/^2\s*nephi/' => '2nephi',
            '/^3\s*nephi/' => '3nephi',
            '/^4\s*nephi/' => '4nephi',
            '/^1\s*samuel/' => '1samuel',
            '/^2\s*samuel/' => '2samuel',
            '/^1\s*reyes/' => '1reyes',
            '/^2\s*reyes/' => '2reyes',
            '/^1\s*cronicas/' => '1cronicas',
            '/^2\s*cronicas/' => '2cronicas',
            '/^1\s*corintios/' => '1corintios',
            '/^2\s*corintios/' => '2corintios',
            '/^1\s*tesalonicenses/' => '1tesalonicenses',
            '/^2\s*tesalonicenses/' => '2tesalonicenses',
            '/^1\s*timoteo/' => '1timoteo',
            '/^2\s*timoteo/' => '2timoteo',
            '/^1\s*pedro/' => '1pedro',
            '/^2\s*pedro/' => '2pedro',
            '/^1\s*juan/' => '1juan',
            '/^2\s*juan/' => '2juan',
            '/^3\s*juan/' => '3juan',
            '/^1\s*macabeos/' => '1macabeos',
            '/^2\s*macabeos/' => '2macabeos'
        ];
        
        foreach ($bookMappings as $pattern => $replacement) {
            if (preg_match($pattern, $book)) {
                return $replacement;
            }
        }
        
        // For books without numbers, just remove all non-alphanumeric characters
        return preg_replace('/[^a-z0-9]/', '', $book);
    }

    public function getVectorByReference(string $reference, bool $includeValues = false): array
    {
        try {
            Log::debug('Processing reference', ['original_reference' => $reference]);
            
            // Convert reference to ID format (e.g., 'Génesis 6:10' -> 'AT-genesis-06-010')
            $vectorId = $this->referenceToId($reference);
            Log::debug('Generated vector ID', ['vector_id' => $vectorId]);
            
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
