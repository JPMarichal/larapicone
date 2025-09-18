<?php

namespace App\Services;

use App\Services\Interfaces\EmbeddingServiceInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

abstract class EmbeddingService implements EmbeddingServiceInterface
{
    protected Client $client;
    protected string $modelName;
    protected int $dimension;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->timeout = config('services.ollama.timeout', 30);
        $this->initializeClient();
    }

    /**
     * Initialize the HTTP client
     */
    protected function initializeClient(): void
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * @inheritDoc
     */
    public function getDimension(): int
    {
        return $this->dimension;
    }

    /**
     * Handle API exceptions
     *
     * @param string $operation
     * @param \Throwable $e
     * @param array $context
     * @throws \RuntimeException
     */
    protected function handleException(string $operation, \Throwable $e, array $context = []): void
    {
        $errorMessage = "Embedding {$operation} failed: " . $e->getMessage();
        
        if (method_exists($e, 'getResponse')) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 'unknown';
            $errorBody = $response ? $response->getBody()->getContents() : 'No response body';
            
            Log::error($errorMessage, array_merge($context, [
                'status_code' => $statusCode,
                'response' => $errorBody,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]));
            
            throw new \RuntimeException(
                "Embedding service error ({$statusCode}): " . ($errorBody ?: $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
        
        Log::error($errorMessage, array_merge($context, [
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]));
        
        throw new \RuntimeException($errorMessage, $e->getCode(), $e);
    }
}
