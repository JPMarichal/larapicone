<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PineconeService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Mockery;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class PineconeUpsertTest extends TestCase
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
        $this->httpClient = new Client(['handler' => $handlerStack]);

        // Configure test settings
        Config::set('pinecone.api_key', 'test-api-key');
        Config::set('pinecone.environment', 'test-env');
        Config::set('pinecone.index', 'test-index');
        Config::set('pinecone.namespace', 'test-namespace');
        Config::set('pinecone.timeout', 30);

        // Create service instance
        $this->pineconeService = new PineconeService();
        
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
    public function it_upserts_vectors_successfully()
    {
        // Arrange
        $vectors = [
            [
                'id' => 'test-vector-1',
                'values' => [0.1, 0.2, 0.3],
                'metadata' => [
                    'book' => 'Génesis',
                    'chapter' => 1,
                    'verse' => 1,
                    'content' => 'En el principio creó Dios los cielos y la tierra.'
                ]
            ]
        ];

        $expectedResponse = ['upsertedCount' => 1];

        // Mock the HTTP response
        $this->mockHandler->append(
            new Response(200, [], json_encode($expectedResponse))
        );

        // Act
        $result = $this->pineconeService->upsertVectors($vectors);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_handles_multiple_vectors_in_batch()
    {
        // Arrange
        $vectors = [
            [
                'id' => 'test-vector-1',
                'values' => [0.1, 0.2, 0.3],
                'metadata' => ['book' => 'Génesis', 'chapter' => 1, 'verse' => 1]
            ],
            [
                'id' => 'test-vector-2',
                'values' => [0.4, 0.5, 0.6],
                'metadata' => ['book' => 'Génesis', 'chapter' => 1, 'verse' => 2]
            ]
        ];

        $expectedResponse = ['upsertedCount' => 2];

        // Mock the HTTP response
        $this->mockHandler->append(
            new Response(200, [], json_encode($expectedResponse))
        );

        // Act
        $result = $this->pineconeService->upsertVectors($vectors);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_handles_upsert_errors_gracefully()
    {
        // Arrange
        $vectors = [
            [
                'id' => 'test-vector-1',
                'values' => [0.1, 0.2, 0.3],
                'metadata' => ['book' => 'Génesis', 'chapter' => 1, 'verse' => 1]
            ]
        ];

        // Mock a failed HTTP response
        $this->mockHandler->append(
            new Response(500, [], json_encode(['error' => 'Internal Server Error']))
        );

        // Expect an exception to be thrown with the Guzzle client error message
        $this->expectException(\GuzzleHttp\Exception\ServerException::class);
        $this->expectExceptionMessage('Server error: `POST vectors/upsert` resulted in a `500 Internal Server Error`');

        // Act
        $this->pineconeService->upsertVectors($vectors);
    }

    /** @test */
    public function it_includes_namespace_in_upsert_request()
    {
        // Arrange
        $vectors = [
            [
                'id' => 'test-vector-1',
                'values' => [0.1, 0.2, 0.3],
                'metadata' => ['book' => 'Génesis', 'chapter' => 1, 'verse' => 1]
            ]
        ];

        $expectedResponse = ['upsertedCount' => 1];

        // Mock the HTTP response
        $this->mockHandler->append(
            new Response(200, [], json_encode($expectedResponse))
        );

        // Act
        $result = $this->pineconeService->upsertVectors($vectors);

        // Assert
        $this->assertEquals($expectedResponse, $result);
        
        // Verify the request was made with the correct namespace
        $request = $this->mockHandler->getLastRequest();
        $requestBody = json_decode($request->getBody()->getContents(), true);
        $this->assertEquals('test-namespace', $requestBody['namespace']);
    }

    /** @test */
    public function it_handles_empty_vectors_array()
    {
        // Arrange
        $vectors = [];
        $expectedResponse = ['upsertedCount' => 0];

        // Mock the HTTP response for empty array
        $this->mockHandler->append(
            new Response(200, [], json_encode($expectedResponse))
        );

        // Act
        $result = $this->pineconeService->upsertVectors($vectors);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }
}
