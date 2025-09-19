<?php

namespace Tests\Unit\Services;

use App\Services\DebugService;
use App\Services\Interfaces\PineconeClientInterface;
use App\Services\Interfaces\EmbeddingServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class DebugServiceTest extends TestCase
{
    private $pineconeClientMock;
    private $embeddingServiceMock;
    private $debugService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->pineconeClientMock = Mockery::mock(PineconeClientInterface::class);
        $this->embeddingServiceMock = Mockery::mock(EmbeddingServiceInterface::class);
        
        // Create the service with mocked dependencies
        $this->debugService = new DebugService(
            $this->pineconeClientMock,
            $this->embeddingServiceMock
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    #[Test]
    public function it_gets_system_info()
    {
        $result = $this->debugService->getSystemInfo();
        
        $this->assertArrayHasKey('system', $result);
        $this->assertArrayHasKey('services', $result);
        $this->assertArrayHasKey('configuration', $result);
        $this->assertArrayHasKey('performance', $result);
        $this->assertArrayHasKey('environment', $result);
        
        // Verify system info has expected keys
        $system = $result['system'];
        $this->assertArrayHasKey('php_version', $system);
        $this->assertArrayHasKey('laravel_version', $system);
        $this->assertArrayHasKey('environment', $system);
    }

    #[Test]
    public function it_checks_database_connection()
    {
        // Mock DB facade
        DB::shouldReceive('connection->getPdo')
            ->once()
            ->andReturnSelf();
            
        DB::shouldReceive('connection->getDriverName')
            ->once()
            ->andReturn('mysql');
            
        DB::shouldReceive('connection->getDatabaseName')
            ->once()
            ->andReturn('test_db');
            
        DB::shouldReceive('connection->getPdo->getAttribute')
            ->with(\PDO::ATTR_SERVER_VERSION)
            ->once()
            ->andReturn('8.0.0');

        $result = $this->debugService->getSystemInfo();
        $dbInfo = $result['services']['database'];
        
        $this->assertEquals('connected', $dbInfo['status']);
        $this->assertEquals('mysql', $dbInfo['driver']);
        $this->assertEquals('test_db', $dbInfo['database']);
    }

    #[Test]
    public function it_checks_pinecone_connection()
    {
        $mockPineconeInfo = [
            'pinecone' => [
                'environment' => 'us-west1-gcp',
                'index' => 'test-index',
                'namespace' => 'test-ns',
                'stats' => [
                    'totalVectorCount' => 1000,
                    'indexFullness' => 0.5
                ]
            ]
        ];
        
        $this->pineconeClientMock->shouldReceive('getDebugInfo')
            ->once()
            ->andReturn($mockPineconeInfo);
            
        $result = $this->debugService->getSystemInfo();
        $pineconeInfo = $result['services']['pinecone'];
        
        $this->assertEquals('connected', $pineconeInfo['status']);
        $this->assertEquals('us-west1-gcp', $pineconeInfo['environment']);
        $this->assertEquals('test-index', $pineconeInfo['index']);
        $this->assertEquals(1000, $pineconeInfo['vector_count']);
    }

    #[Test]
    public function it_checks_embedding_service()
    {
        $this->embeddingServiceMock->shouldReceive('generateEmbedding')
            ->with('test')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);
            
        $this->embeddingServiceMock->shouldReceive('getModelName')
            ->once()
            ->andReturn('test-model');
            
        $this->embeddingServiceMock->shouldReceive('getDimension')
            ->once()
            ->andReturn(3);
            
        $result = $this->debugService->getSystemInfo();
        $embeddingInfo = $result['services']['embedding_service'];
        
        $this->assertEquals('connected', $embeddingInfo['status']);
        $this->assertEquals('test-model', $embeddingInfo['model']);
        $this->assertEquals(3, $embeddingInfo['dimensions']);
        $this->assertEquals(3, $embeddingInfo['vector_length']);
    }

    #[Test]
    public function it_handles_service_errors_gracefully()
    {
        // Test Pinecone error
        $this->pineconeClientMock->shouldReceive('getDebugInfo')
            ->once()
            ->andThrow(new \Exception('Connection failed'));
            
        // Test Embedding service error
        $this->embeddingServiceMock->shouldReceive('generateEmbedding')
            ->andThrow(new \Exception('Embedding service unavailable'));
            
        $this->embeddingServiceMock->shouldReceive('getModelName')
            ->andReturn('test-model');
            
        $result = $this->debugService->getSystemInfo();
        
        // Verify Pinecone error is handled
        $this->assertEquals('error', $result['services']['pinecone']['status']);
        $this->assertEquals('Connection failed', $result['services']['pinecone']['error']);
        
        // Verify Embedding service error is handled
        $this->assertEquals('error', $result['services']['embedding_service']['status']);
        $this->assertEquals('Embedding service unavailable', $result['services']['embedding_service']['error']);
    }

    #[Test]
    public function it_gets_environment_info()
    {
        $result = $this->debugService->getSystemInfo();
        $envInfo = $result['environment'];
        
        $this->assertArrayHasKey('environment_variables', $envInfo);
        $this->assertArrayHasKey('php', $envInfo);
        $this->assertArrayHasKey('system', $envInfo);
        $this->assertArrayHasKey('timezone', $envInfo);
        $this->assertArrayHasKey('disk', $envInfo);
        
        // Verify PHP info
        $this->assertEquals(PHP_VERSION, $envInfo['php']['version']);
        $this->assertEquals(PHP_OS, $envInfo['php']['os']);
        $this->assertEquals(PHP_SAPI, $envInfo['php']['sapi']);
        $this->assertIsArray($envInfo['php']['extensions']);
        
        // Verify disk info
        $this->assertArrayHasKey('total', $envInfo['disk']);
        $this->assertArrayHasKey('free', $envInfo['disk']);
        $this->assertArrayHasKey('used', $envInfo['disk']);
    }

    #[Test]
    public function it_checks_cache_connection()
    {
        // Mock Cache facade
        Cache::shouldReceive('getStore')
            ->once()
            ->andReturnSelf();
            
        Cache::shouldReceive('getStore->getName')
            ->once()
            ->andReturn('file');
            
        $result = $this->debugService->getSystemInfo();
        $cacheInfo = $result['services']['cache'];
        
        $this->assertEquals('connected', $cacheInfo['status']);
        $this->assertEquals('file', $cacheInfo['driver']);
    }

    #[Test]
    public function it_handles_database_connection_error_gracefully()
    {
        // Test Database error
        DB::shouldReceive('connection->getPdo')
            ->once()
            ->andThrow(new \Exception('Connection failed'));
            
        $result = $this->debugService->getSystemInfo();
        
        // Verify Database error is handled
        $this->assertEquals('error', $result['services']['database']['status']);
        $this->assertEquals('Connection failed', $result['services']['database']['error']);
    }

    #[Test]
    public function it_checks_redis_connection()
    {
        // Mock Redis facade
        Redis::shouldReceive('ping')
            ->once()
            ->andReturn('PONG');
            
        $result = $this->debugService->getSystemInfo();
        $redisInfo = $result['services']['redis'];
        
        $this->assertEquals('connected', $redisInfo['status']);
    }

    #[Test]
    public function it_formats_bytes_correctly()
    {
        $debugService = new class($this->pineconeClientMock, $this->embeddingServiceMock) extends DebugService {
            public function testFormatBytes($bytes, $precision = 2) {
                return $this->formatBytes($bytes, $precision);
            }
        };
        
        $this->assertEquals('1 B', $debugService->testFormatBytes(1));
        $this->assertEquals('1 KB', $debugService->testFormatBytes(1024));
        $this->assertEquals('1.5 KB', $debugService->testFormatBytes(1536));
        $this->assertEquals('1 MB', $debugService->testFormatBytes(1024 * 1024));
        $this->assertEquals('1 GB', $debugService->testFormatBytes(1024 * 1024 * 1024));
        $this->assertEquals('1 TB', $debugService->testFormatBytes(1024 * 1024 * 1024 * 1024));
    }
}
