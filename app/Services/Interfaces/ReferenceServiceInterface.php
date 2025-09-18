<?php

namespace App\Services\Interfaces;

interface ReferenceServiceInterface
{
    /**
     * Format a reference from metadata
     * 
     * @param array $metadata The metadata containing reference information
     * @return string Formatted reference string
     */
    public function formatReference(array $metadata): string;
    
    /**
     * Parse a reference string into its components
     * 
     * @param string $reference The reference string (e.g., "Juan 1:1-3,14-15,20")
     * @return array Parsed reference components
     */
    public function parseReference(string $reference): array;
    
    /**
     * Convert a reference to a vector ID
     * 
     * @param string $reference The reference string
     * @return string The vector ID
     */
    public function referenceToVectorId(string $reference): string;
    
    /**
     * Get all verses for a given reference
     * 
     * @param string $reference The reference string
     * @return array Array of verse references
     */
    public function expandReference(string $reference): array;
}
