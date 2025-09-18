<?php

namespace App\Services;

use App\Services\Interfaces\SearchServiceInterface;
use App\Services\Interfaces\PineconeClientInterface;
use App\Services\Interfaces\EmbeddingServiceInterface;
use Illuminate\Support\Facades\Log;

class SearchService implements SearchServiceInterface
{
    protected PineconeClientInterface $pineconeClient;
    protected EmbeddingServiceInterface $embeddingService;
    protected ?float $lastExecutionTime = null;

    public function __construct(
        PineconeClientInterface $pineconeClient,
        EmbeddingServiceInterface $embeddingService
    ) {
        $this->pineconeClient = $pineconeClient;
        $this->embeddingService = $embeddingService;
    }

    /**
     * @inheritDoc
     */
    public function semanticSearch(string $query, int $topK = 10, array $filters = []): array
    {
        $startTime = microtime(true);
        
        try {
            Log::info('Starting semantic search', [
                'query' => $query,
                'topK' => $topK,
                'model' => $this->embeddingService->getModelName(),
                'dimensions' => $this->embeddingService->getDimension()
            ]);
            
            // Generate embedding for the query
            $queryVector = $this->embeddingService->generateEmbedding($query);
            
            if (empty($queryVector)) {
                throw new \RuntimeException('Failed to generate embedding for the query');
            }
            
            // Execute the search
            $results = $this->searchByVector($queryVector, $topK, $filters);
            
            $this->lastExecutionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            Log::info('Semantic search completed', [
                'query' => $query,
                'results_count' => count($results['results'] ?? []),
                'execution_time_ms' => $this->lastExecutionTime,
                'model' => $this->embeddingService->getModelName()
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('Semantic search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'query' => $query,
                'results' => [],
                'count' => 0,
                'error' => $e->getMessage(),
                'search_type' => 'semantic',
                'execution_time_ms' => (microtime(true) - $startTime) * 1000
            ];
        }
    }
    
    /**
     * @inheritDoc
     */
    public function searchByVector(array $vector, int $topK = 10, array $filters = []): array
    {
        $startTime = microtime(true);
        
        try {
            // Execute the query
            $response = $this->pineconeClient->query($vector, $topK, $filters);
            
            if (!isset($response['matches'])) {
                Log::warning('Invalid response format from Pinecone', [
                    'response' => $response
                ]);
                
                return [
                    'results' => [],
                    'count' => 0,
                    'search_type' => 'vector',
                    'execution_time_ms' => (microtime(true) - $startTime) * 1000
                ];
            }
            
            // Process results
            $results = [];
            foreach ($response['matches'] as $match) {
                $metadata = $match['metadata'] ?? [];
                $score = $match['score'] ?? 0;
                
                // Skip results with very low scores
                if ($score < 0.1) {
                    continue;
                }
                
                $results[] = [
                    'id' => $match['id'],
                    'score' => $score,
                    'metadata' => $metadata
                ];
            }
            
            $this->lastExecutionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            return [
                'results' => $results,
                'count' => count($results),
                'search_type' => 'vector',
                'execution_time_ms' => $this->lastExecutionTime,
                'model' => $this->embeddingService->getModelName()
            ];
            
        } catch (\Exception $e) {
            Log::error('Vector search failed', [
                'vector_size' => count($vector),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'results' => [],
                'count' => 0,
                'error' => $e->getMessage(),
                'search_type' => 'vector',
                'execution_time_ms' => (microtime(true) - $startTime) * 1000
            ];
        }
    }
    
    /**
     * @inheritDoc
     */
    public function getLastExecutionTime(): ?float
    {
        return $this->lastExecutionTime;
    }
}
