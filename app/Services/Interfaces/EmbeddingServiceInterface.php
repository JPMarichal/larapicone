<?php

namespace App\Services\Interfaces;

interface EmbeddingServiceInterface
{
    /**
     * Generate an embedding vector for the given text
     *
     * @param string $text The text to generate embedding for
     * @return array The embedding vector
     * @throws \Exception If embedding generation fails
     */
    public function generateEmbedding(string $text): array;
    
    /**
     * Get the name of the embedding model/strategy being used
     * 
     * @return string Model/strategy name
     */
    public function getModelName(): string;
    
    /**
     * Get the dimension size of the embedding vectors
     * 
     * @return int Vector dimension size
     */
    public function getDimension(): int;
}
