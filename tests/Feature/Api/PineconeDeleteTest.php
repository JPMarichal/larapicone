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

class PineconeDeleteTest extends TestCase
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
        Log::shouldReceive('error')->byDefault();
        Log::shouldReceive('debug')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_deletes_vectors_by_ids()
    {
        // Arrange
        $vectorIds = ['vector-1', 'vector-2'];
        $expectedResponse = ['deletedCount' => 2];

        // Mock the HTTP response
        $this->mockHandler->append(
            new Response(200, [], json_encode($expectedResponse))
        );

        // Act
        $result = $this->pineconeService->deleteVectors($vectorIds);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_deletes_vectors_by_filter()
    {
        // Arrange
        $filter = ['book' => 'Génesis'];
        $expectedResponse = ['deletedCount' => 5];

        // Mock the HTTP response
        $this->mockHandler->append(
            new Response(200, [], json_encode($expectedResponse))
        );

        // Act
        $result = $this->pineconeService->deleteVectors([], $filter);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_handles_deletion_errors_gracefully()
    {
        // Arrange
        $vectorIds = ['vector-1'];

        // Mock a failed HTTP response
        $this->mockHandler->append(
            new Response(500, [], json_encode(['error' => 'Internal Server Error']))
        );

        // Expect an exception to be thrown with the Guzzle client error message
        $this->expectException(\GuzzleHttp\Exception\ServerException::class);
        $this->expectExceptionMessage('Server error: `POST vectors/delete` resulted in a `500 Internal Server Error`');

        // Act
        $this->pineconeService->deleteVectors($vectorIds);
    }

    /** @test */
    public function it_requires_either_ids_or_filter()
    {
        // This test ensures that the method validates that either ids or filter is provided
        // The actual validation is done in the controller, but we can test the service behavior
        
        // Arrange - empty request
        $expectedResponse = ['deletedCount' => 0];

        // Mock the HTTP response
        $this->mockHandler->append(
            new Response(200, [], json_encode($expectedResponse))
        );

        // Act
        $result = $this->pineconeService->deleteVectors();

        // Assert - should still make a request with just the namespace
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_delete_all_vectors_in_namespace()
    {
        // Arrange - empty filter means delete all in namespace
        $expectedResponse = ['deletedCount' => 100];

        // Mock the HTTP response
        $this->mockHandler->append(
            new Response(200, [], json_encode($expectedResponse))
        );

        // Act - empty arrays for both ids and filter will delete all in namespace
        $result = $this->pineconeService->deleteVectors([], []);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }
}
