<?php

namespace App\Services;

use App\Services\Interfaces\EmbeddingServiceInterface;
use Illuminate\Support\Facades\Log;

class EmbeddingServiceFactory
{
    /**
     * Create an embedding service instance
     * 
     * @param string|null $preferredService Preferred service to use (ollama, fallback, or null for auto)
     * @return EmbeddingServiceInterface
     */
    public static function create(?string $preferredService = null): EmbeddingServiceInterface
    {
        $services = [];
        
        // Try Ollama first if it's preferred or auto
        if (!$preferredService || $preferredService === 'ollama') {
            try {
                $ollamaService = new OllamaEmbeddingService();
                // Test the service with a small request
                $ollamaService->generateEmbedding('test');
                return $ollamaService;
            } catch (\Exception $e) {
                Log::warning('Ollama embedding service unavailable, falling back to fallback service', [
                    'error' => $e->getMessage()
                ]);
                
                // If Ollama was explicitly requested but failed, rethrow the exception
                if ($preferredService === 'ollama') {
                    throw $e;
                }
            }
        }
        
        // Use fallback service
        return new FallbackEmbeddingService();
    }
    
    /**
     * Get all available embedding services
     * 
     * @return array Array of available service names
     */
    public static function getAvailableServices(): array
    {
        $services = ['fallback' => 'Fallback'];
        
        try {
            $ollama = new OllamaEmbeddingService();
            $ollama->generateEmbedding('test');
            $services['ollama'] = 'Ollama (' . $ollama->getModelName() . ')';
        } catch (\Exception $e) {
            // Ollama is not available
        }
        
        return $services;
    }
}
