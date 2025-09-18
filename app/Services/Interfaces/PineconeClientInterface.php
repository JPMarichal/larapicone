<?php

namespace App\Services\Interfaces;

interface PineconeClientInterface
{
    /**
     * Query the Pinecone index
     *
     * @param array $vector The query vector
     * @param int $topK Number of results to return
     * @param array $filter Optional filter conditions
     * @return array Query results
     */
    public function query(array $vector, int $topK = 10, array $filter = []): array;

    /**
     * Upsert vectors to the index
     *
     * @param array $vectors Array of vectors to upsert
     * @return array Response from Pinecone
     */
    public function upsertVectors(array $vectors): array;

    /**
     * Get a vector by ID
     *
     * @param string $id Vector ID
     * @param bool $includeValues Whether to include vector values
     * @return array Vector data
     */
    public function getVector(string $id, bool $includeValues = false): array;

    /**
     * Get debug information about the Pinecone service
     *
     * @return array Debug information
     */
    public function getDebugInfo(): array;
}
