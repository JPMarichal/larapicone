<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionMethod;

class PineconeVectorReferenceTest extends TestCase
{
    private $mockHandler;
    private $pineconeService;
    private $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock handler for Guzzle
        $this->mockHandler = new MockHandler();
        
        // Create a new Guzzle client with the mock handler
        $handlerStack = HandlerStack::create($this->mockHandler);
        $this->httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        // Configure test settings
        Config::set('pinecone.api_key', 'test-api-key');
        Config::set('pinecone.environment', 'test-env');
        Config::set('pinecone.index', 'test-index');
        Config::set('pinecone.namespace', 'test-namespace');
        Config::set('pinecone.timeout', 30);

        // Create service instance
        $this->pineconeService = new \App\Services\PineconeService();
        
        // Use reflection to inject our mock HTTP client
        $reflection = new \ReflectionClass($this->pineconeService);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->pineconeService, $this->httpClient);

        // Mock the logger
        Log::shouldReceive('debug')->byDefault();
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('error')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_gets_vector_by_reference()
    {
        // Arrange
        $reference = 'Génesis 1:1';
        $vectorId = 'AT-genesis-01-001';
        $mockVector = [
            'id' => $vectorId,
            'values' => [0.1, 0.2, 0.3],
            'metadata' => [
                'book' => 'Génesis',
                'chapter' => 1,
                'verse' => 1,
                'content' => 'En el principio creó Dios los cielos y la tierra.'
            ]
        ];

        // Create a partial mock of the service
        $mockService = Mockery::mock($this->pineconeService)->makePartial();
        
        // Mock the getVector method to return our test vector
        $mockService->shouldReceive('getVector')
            ->once()
            ->with($vectorId, false)
            ->andReturn([
                'vectors' => [
                    $vectorId => $mockVector
                ],
                'namespace' => 'test-namespace'
            ]);

        // Mock the referenceToId method using reflection since it's private
        $this->setPrivateProperty($mockService, 'bookMappings', [
            'genesis' => [
                'volume' => 'AT',
                'slug' => 'genesis',
                'name' => 'Génesis'
            ]
        ]);

        // Replace the service in the container with our mock
        $this->app->instance(\App\Services\PineconeService::class, $mockService);

        // Act
        $response = $this->postJson('/api/pinecone/vector/reference', [
            'reference' => $reference
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'vectors' => [
                        $vectorId => $mockVector
                    ],
                    'namespace' => 'test-namespace'
                ]
            ]);
    }

    /** @test */
    public function it_includes_vector_values_when_requested()
    {
        // Arrange
        $reference = 'Génesis 1:1';
        $vectorId = 'AT-genesis-01-001';
        $mockVector = [
            'id' => $vectorId,
            'values' => [0.1, 0.2, 0.3],
            'metadata' => [
                'book' => 'Génesis',
                'chapter' => 1,
                'verse' => 1,
                'content' => 'En el principio creó Dios los cielos y la tierra.'
            ]
        ];

        // Create a partial mock of the service
        $mockService = Mockery::mock($this->pineconeService)->makePartial();
        
        // Mock the getVector method to return our test vector with values
        $mockService->shouldReceive('getVector')
            ->once()
            ->with($vectorId, true)
            ->andReturn([
                'vectors' => [
                    $vectorId => $mockVector
                ],
                'namespace' => 'test-namespace'
            ]);

        // Mock the referenceToId method using reflection since it's private
        $this->setPrivateProperty($mockService, 'bookMappings', [
            'genesis' => [
                'volume' => 'AT',
                'slug' => 'genesis',
                'name' => 'Génesis'
            ]
        ]);

        // Replace the service in the container with our mock
        $this->app->instance(\App\Services\PineconeService::class, $mockService);

        // Act
        $response = $this->postJson('/api/pinecone/vector/reference', [
            'reference' => $reference,
            'include_values' => true
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'vectors' => [
                        $vectorId => $mockVector
                    ],
                    'namespace' => 'test-namespace'
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_when_vector_not_found()
    {
        // Arrange
        $reference = 'Génesis 1:999'; // Non-existent verse
        $vectorId = 'AT-genesis-01-999';

        // Create a partial mock of the service
        $mockService = Mockery::mock($this->pineconeService)->makePartial();
        
        // Mock the getVector method to return an empty result
        $mockService->shouldReceive('getVector')
            ->once()
            ->with($vectorId, false)
            ->andReturn([
                'vectors' => [],
                'namespace' => 'test-namespace'
            ]);

        // Mock the referenceToId method using reflection since it's private
        $this->setPrivateProperty($mockService, 'bookMappings', [
            'genesis' => [
                'volume' => 'AT',
                'slug' => 'genesis',
                'name' => 'Génesis'
            ]
        ]);

        // Replace the service in the container with our mock
        $this->app->instance(\App\Services\PineconeService::class, $mockService);

        // Act
        $response = $this->postJson('/api/pinecone/vector/reference', [
            'reference' => $reference
        ]);

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Vector not found for reference: ' . $reference
            ]);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        // Act
        $response = $this->postJson('/api/pinecone/vector/reference', []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reference']);
    }

    /** @test */
    public function it_handles_invalid_reference_format()
    {
        // Arrange
        $reference = 'Invalid Reference Format';
        
        // Create a partial mock of the service
        $mockService = Mockery::mock($this->pineconeService)->makePartial();
        
        // Mock the getVector method to throw an exception for invalid format
        $mockService->shouldReceive('getVector')
            ->andThrow(new \InvalidArgumentException('Invalid reference format'));

        // Replace the service in the container with our mock
        $this->app->instance(\App\Services\PineconeService::class, $mockService);

        // Act
        $response = $this->postJson('/api/pinecone/vector/reference', [
            'reference' => $reference
        ]);

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid reference format'
            ]);
    }

    /** @test */
    public function it_handles_pinecone_errors()
    {
        // Arrange
        $reference = 'Génesis 1:1';

        // Create a partial mock of the service
        $mockService = Mockery::mock($this->pineconeService)->makePartial();
        
        // Mock the getVector method to throw an exception
        $mockService->shouldReceive('getVector')
            ->andThrow(new \Exception('Pinecone error'));

        // Mock the referenceToId method using reflection since it's private
        $this->setPrivateProperty($mockService, 'bookMappings', [
            'genesis' => [
                'volume' => 'AT',
                'slug' => 'genesis',
                'name' => 'Génesis'
            ]
        ]);

        // Replace the service in the container with our mock
        $this->app->instance(\App\Services\PineconeService::class, $mockService);

        // Act
        $response = $this->postJson('/api/pinecone/vector/reference', [
            'reference' => $reference
        ]);

        // Assert
        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to get vector by reference: Pinecone error'
            ]);
    }
    
    /**
     * Helper method to set private properties using reflection
     */
    private function setPrivateProperty($object, $property, $value)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
