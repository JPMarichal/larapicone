<?php

namespace Tests\Unit\Services;

use App\Services\EmbeddingServiceFactory;
use App\Services\EmbeddingService;
use App\Services\OllamaEmbeddingService;
use App\Services\FallbackEmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingServiceFactoryTest extends TestCase
{
    private EmbeddingServiceFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock HTTP client for Ollama service
        Http::fake();
        
        $this->factory = new EmbeddingServiceFactory();
    }

    /** @test */
    public function it_creates_ollama_embedding_service()
    {
        // Set config to use Ollama
        config(['services.ollama.enabled' => true]);
        
        $service = $this->factory->create();
        
        $this->assertInstanceOf(OllamaEmbeddingService::class, $service);
    }

    /** @test */
    public function it_creates_fallback_embedding_service_when_ollama_disabled()
    {
        // Disable Ollama in config
        config(['services.ollama.enabled' => false]);
        
        $service = $this->factory->create();
        
        $this->assertInstanceOf(FallbackEmbeddingService::class, $service);
    }

    /** @test */
    public function it_creates_specified_service_type()
    {
        // Test creating each service type explicitly
        $ollamaService = $this->factory->create('ollama');
        $fallbackService = $this->factory->create('fallback');
        
        $this->assertInstanceOf(OllamaEmbeddingService::class, $ollamaService);
        $this->assertInstanceOf(FallbackEmbeddingService::class, $fallbackService);
    }

    /** @test */
    public function it_returns_fallback_for_unknown_service_type()
    {
        $service = $this->factory->create('unknown_service');
        
        $this->assertInstanceOf(FallbackEmbeddingService::class, $service);
    }

    /** @test */
    public function it_creates_service_with_custom_config()
    {
        $customConfig = [
            'ollama' => [
                'base_url' => 'http://custom-ollama:11434',
                'model' => 'custom-model',
                'timeout' => 60
            ]
        ];
        
        $service = $this->factory->create('ollama', $customConfig);
        
        $this->assertInstanceOf(OllamaEmbeddingService::class, $service);
        
        // Verify the service was created with custom config
        $reflection = new \ReflectionClass($service);
        $baseUrl = $reflection->getProperty('baseUrl');
        $baseUrl->setAccessible(true);
        
        $this->assertEquals('http://custom-ollama:11434', $baseUrl->getValue($service));
    }

    /** @test */
    public function it_creates_service_with_default_config_when_invalid()
    {
        $service = $this->factory->create('ollama', ['invalid' => 'config']);
        
        $this->assertInstanceOf(OllamaEmbeddingService::class, $service);
    }
}
