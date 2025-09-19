<?php

namespace Tests\Unit\Services\Clients;

use App\Services\Clients\PineconeClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class PineconeClientNewTest extends TestCase
{
    private $mockHandler;
    private $pineconeClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock handler for Guzzle
        $this->mockHandler = new MockHandler();
        
        // Create a Guzzle client with the mock handler
        $client = new Client([
            'handler' => HandlerStack::create($this->mockHandler)
        ]);
        
        // Create a test-specific PineconeClient that extends the original
        $reflection = new \ReflectionClass(PineconeClient::class);
        $this->pineconeClient = $reflection->newInstanceWithoutConstructor();
        
        // Set up the required properties
        $properties = [
            'apiKey' => 'test-api-key',
            'environment' => 'test-env',
            'index' => 'test-index',
            'namespace' => 'test-namespace',
            'timeout' => 30,
            'client' => $client
        ];
        
        foreach ($properties as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($this->pineconeClient, $value);
        }
    }
    
    /** @test */
    public function it_can_query_vectors()
    {
        // Mock response for query
        $mockResponse = [
            'matches' => [
                [
                    'id' => 'test-vector-1',
                    'score' => 0.95,
                    'metadata' => ['key' => 'value']
                ]
            ]
        ];
        
        $this->mockHandler->append(new Response(
            200, 
            ['Content-Type' => 'application/json'],
            json_encode($mockResponse)
        ));
        
        // Call the method being tested
        $result = $this->pineconeClient->query([0.1, 0.2, 0.3], 1);
        
        // Assert the expected results
        $this->assertArrayHasKey('matches', $result);
        $this->assertCount(1, $result['matches']);
        $this->assertEquals('test-vector-1', $result['matches'][0]['id']);
    }
}
