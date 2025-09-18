<?php

namespace App\Providers;

use App\Services\Clients\PineconeClient;
use App\Services\EmbeddingServiceFactory;
use App\Services\ReferenceService;
use App\Services\SearchService;
use App\Services\DebugService;
use App\Services\OllamaEmbeddingService;
use App\Services\FallbackEmbeddingService;
use App\Services\Interfaces\PineconeClientInterface;
use App\Services\Interfaces\EmbeddingServiceInterface;
use App\Services\Interfaces\ReferenceServiceInterface;
use App\Services\Interfaces\SearchServiceInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PineconeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerPineconeClient();
        $this->registerEmbeddingService();
        $this->registerReferenceService();
        $this->registerSearchService();
        $this->registerDebugService();
    }

    /**
     * Register the PineconeClient service
     */
    protected function registerPineconeClient(): void
    {
        $this->app->singleton(PineconeClientInterface::class, function ($app) {
            return new PineconeClient(
                config('pinecone.api_key'),
                config('pinecone.environment'),
                config('pinecone.index'),
                config('pinecone.namespace', ''),
                config('pinecone.request_timeout', 30),
                config('pinecone.connect_timeout', 10)
            );
        });
    }

    /**
     * Register the EmbeddingService with fallback
     */
    protected function registerEmbeddingService(): void
    {
        $this->app->singleton(EmbeddingServiceInterface::class, function ($app) {
            $factory = new EmbeddingServiceFactory(
                config('services.ollama.base_url', 'http://localhost:11434'),
                config('services.ollama.embed_model', 'nomic-embed-text'),
                config('services.ollama.timeout', 30),
                config('services.ollama.dimension', 768)
            );

            return $factory->create();
        });
    }

    /**
     * Register the ReferenceService
     */
    protected function registerReferenceService(): void
    {
        $this->app->singleton(ReferenceServiceInterface::class, function ($app) {
            return new ReferenceService();
        });
    }

    /**
     * Register the SearchService
     */
    protected function registerSearchService(): void
    {
        $this->app->singleton(SearchServiceInterface::class, function ($app) {
            return new SearchService(
                $app->make(PineconeClientInterface::class),
                $app->make(EmbeddingServiceInterface::class)
            );
        });
    }

    /**
     * Register the DebugService
     */
    protected function registerDebugService(): void
    {
        $this->app->singleton(DebugService::class, function ($app) {
            return new DebugService(
                $app->make(PineconeClientInterface::class),
                $app->make(EmbeddingServiceInterface::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/pinecone.php' => config_path('pinecone.php'),
        ], 'config');
        
        // Register console commands if needed
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add console commands here if needed
            ]);
        }
    }
}
