<?php

namespace App\Services\Interfaces;

interface SearchServiceInterface
{
    /**
     * Perform a semantic search
     *
     * @param string $query The search query
     * @param int $topK Number of results to return
     * @param array $filters Optional filters to apply
     * @return array Search results
     */
    public function semanticSearch(string $query, int $topK = 10, array $filters = []): array;
    
    /**
     * Get search results by vector
     * 
     * @param array $vector The query vector
     * @param int $topK Number of results to return
     * @param array $filters Optional filters to apply
     * @return array Search results
     */
    public function searchByVector(array $vector, int $topK = 10, array $filters = []): array;
    
    /**
     * Get the last execution time in milliseconds
     * 
     * @return float|null Execution time in milliseconds or null if no search was performed
     */
    public function getLastExecutionTime(): ?float;
}
