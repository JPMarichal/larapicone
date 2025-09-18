<?php

namespace App\Services;

use App\Services\Interfaces\EmbeddingServiceInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class OllamaEmbeddingService extends EmbeddingService implements EmbeddingServiceInterface
{
    protected string $modelName = 'nomic-embed-text';
    protected int $dimension = 768; // nomic-embed-text uses 768 dimensions
    protected string $endpoint = '/api/embeddings';

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ollama.base_url', 'http://localhost:11434/'), '/') . '/';
        $this->modelName = config('services.ollama.embed_model', $this->modelName);
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public function generateEmbedding(string $text): array
    {
        try {
            Log::debug('Generating Ollama embedding', [
                'model' => $this->modelName,
                'text_length' => mb_strlen($text),
                'endpoint' => $this->endpoint
            ]);

            $response = $this->client->post($this->endpoint, [
                'json' => [
                    'model' => $this->modelName,
                    'prompt' => $text
                ],
                'timeout' => $this->timeout,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException("Ollama API returned status code: " . $response->getStatusCode());
            }

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (empty($data['embedding']) || !is_array($data['embedding'])) {
                throw new \RuntimeException('Invalid embedding format in Ollama response');
            }
            
            $embedding = $data['embedding'];
            
            // Ensure the embedding has the correct dimension
            if (count($embedding) !== $this->dimension) {
                Log::warning("Embedding dimension mismatch", [
                    'expected' => $this->dimension,
                    'actual' => count($embedding),
                    'model' => $this->modelName
                ]);
                
                // Truncate or pad the vector to match the expected dimension
                if (count($embedding) > $this->dimension) {
                    $embedding = array_slice($embedding, 0, $this->dimension);
                } else {
                    $embedding = array_pad($embedding, $this->dimension, 0);
                }
            }
            
            Log::debug('Generated Ollama embedding', [
                'model' => $this->modelName,
                'dimensions' => count($embedding),
                'text_length' => mb_strlen($text)
            ]);
            
            return $embedding;

        } catch (GuzzleException $e) {
            $this->handleException('generation', $e, [
                'model' => $this->modelName,
                'endpoint' => $this->endpoint
            ]);
        }
    }
}
