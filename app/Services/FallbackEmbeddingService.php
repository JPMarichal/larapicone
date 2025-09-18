<?php

namespace App\Services;

use App\Services\Interfaces\EmbeddingServiceInterface;
use Illuminate\Support\Str;

class FallbackEmbeddingService extends EmbeddingService implements EmbeddingServiceInterface
{
    protected string $modelName = 'fallback-bow';
    protected int $dimension = 768; // Match the dimension of nomic-embed-text

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public function generateEmbedding(string $text): array
    {
        // Convert text to lowercase and remove punctuation
        $text = strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $text)));
        
        // Split into words and count frequencies
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCounts = array_count_values($words);
        
        // Create a simple bag-of-words vector with the target dimension
        $vector = array_fill(0, $this->dimension, 0);
        
        // Simple hashing trick to map words to vector indices
        foreach ($wordCounts as $word => $count) {
            // Use a combination of hashing and modulo to distribute words across dimensions
            $index = abs(crc32($word)) % $this->dimension;
            $vector[$index] += $count * $this->getWordWeight($word);
        }
        
        // Normalize the vector to unit length
        $length = sqrt(array_sum(array_map(function($x) { 
            return $x * $x; 
        }, $vector)));
        
        if ($length > 0) {
            $vector = array_map(function($x) use ($length) { 
                return $x / $length; 
            }, $vector);
        }
        
        return $vector;
    }
    
    /**
     * Get weight for a word (simple IDF-like weighting)
     * 
     * @param string $word
     * @return float
     */
    protected function getWordWeight(string $word): float
    {
        // Give more weight to longer, more specific words
        $length = mb_strlen($word);
        
        // Common words get lower weight
        $commonWords = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'any', 'can', 'had', 'her', 'was', 'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'man', 'new', 'now', 'old', 'see', 'two', 'way', 'who', 'boy', 'did', 'its', 'let', 'put', 'say', 'she', 'too', 'use'];
        
        if (in_array($word, $commonWords)) {
            return 0.1;
        }
        
        // Longer words get more weight
        return min(1.0, $length / 5.0);
    }
}
