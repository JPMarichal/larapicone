<?php

namespace Tests\Unit\Services\Clients;

use App\Services\Clients\PineconeClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PineconeClientTest extends TestCase
{
    use RefreshDatabase;

    private $pineconeClient;
    private $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock handler for Guzzle
        $this->mockHandler = new MockHandler();
        
        // Create a Guzzle client with the mock handler
        $client = new Client([
            'handler' => HandlerStack::create($this->mockHandler),
            'base_uri' => 'https://test-index-test-env.svc.test-env.pinecone.io/'
        ]);
        
        // Use reflection to create a PineconeClient with our mock client
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
            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);
                $property->setAccessible(true);
                $property->setValue($this->pineconeClient, $value);
            }
        }
    }

    #[Test]
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
        
        // Assert the request was made correctly
        $this->assertCount(0, $this->mockHandler, 'All requests should be consumed');
        
        // Assert the expected results
        $this->assertIsArray($result, 'Result should be an array');
        $this->assertArrayHasKey('matches', $result, 'Result should have a matches key');
        $this->assertIsArray($result['matches'], 'Matches should be an array');
        $this->assertCount(1, $result['matches'], 'Should have exactly one match');
        $this->assertArrayHasKey('id', $result['matches'][0], 'Match should have an id');
        $this->assertEquals('test-vector-1', $result['matches'][0]['id'], 'ID should match expected value');
    }

    #[Test]
    public function it_can_upsert_vectors()
    {
        $mockResponse = ['upsertedCount' => 1];
        $this->mockHandler->append(new Response(
            200, 
            ['Content-Type' => 'application/json'],
            json_encode($mockResponse)
        ));
        
        $vectors = [
            [
                'id' => 'test-vector-1',
                'values' => [0.1, 0.2, 0.3],
                'metadata' => ['key' => 'value']
            ]
        ];
        
        $result = $this->pineconeClient->upsertVectors($vectors);
        
        // Assert the request was made correctly
        $this->assertCount(0, $this->mockHandler, 'All requests should be consumed');
        
        // Assert the results
        $this->assertIsArray($result, 'Result should be an array');
        $this->assertArrayHasKey('upsertedCount', $result, 'Result should have an upsertedCount key');
        $this->assertEquals(1, $result['upsertedCount'], 'Upserted count should be 1');
    }

    #[Test]
    public function it_can_get_vector_by_id()
    {
        $mockResponse = [
            'vectors' => [
                'test-vector-1' => [
                    'id' => 'test-vector-1',
                    'values' => [0.1, 0.2, 0.3],
                    'metadata' => ['key' => 'value']
                ]
            ]
        ];
        
        $this->mockHandler->append(new Response(
            200, 
            ['Content-Type' => 'application/json'],
            json_encode($mockResponse)
        ));
        
        $result = $this->pineconeClient->getVector('test-vector-1', true);
        
        // Assert the request was made correctly
        $this->assertCount(0, $this->mockHandler, 'All requests should be consumed');
        
        // Assert the results
        $this->assertIsArray($result, 'Result should be an array');
        $this->assertArrayHasKey('vectors', $result, 'Result should have a vectors key');
        $this->assertIsArray($result['vectors'], 'Vectors should be an array');
        $this->assertArrayHasKey('test-vector-1', $result['vectors'], 'Should have the test vector');
    }

    #[Test]
    public function it_can_get_debug_info()
    {
        $mockStatsResponse = [
            'namespaces' => ['test-namespace' => ['vectorCount' => 100]],
            'dimension' => 3,
            'indexFullness' => 0.5,
            'totalVectorCount' => 100
        ];
        
        $mockListResponse = [
            'vectors' => [
                [
                    'id' => 'test-vector-1',
                    'metadata' => ['key1' => 'value1', 'key2' => 'value2']
                ]
            ]
        ];
        
        // Add the mock responses to the handler queue
        $this->mockHandler->append(new Response(
            200, 
            ['Content-Type' => 'application/json'],
            json_encode($mockStatsResponse)
        ));
        
        $this->mockHandler->append(new Response(
            200, 
            ['Content-Type' => 'application/json'],
            json_encode($mockListResponse)
        ));
        
        $result = $this->pineconeClient->getDebugInfo();
        
        // Assert all requests were consumed
        $this->assertCount(0, $this->mockHandler, 'All requests should be consumed');
        
        // Assert the results
        $this->assertIsArray($result, 'Result should be an array');
        $this->assertArrayHasKey('pinecone', $result, 'Should have pinecone key');
        $this->assertIsArray($result['pinecone'], 'Pinecone data should be an array');
        $this->assertArrayHasKey('stats', $result['pinecone'], 'Should have stats in pinecone data');
        $this->assertArrayHasKey('sample_vectors', $result, 'Should have sample_vectors');
        $this->assertArrayHasKey('metadata_keys', $result, 'Should have metadata_keys');
        $this->assertContains('key1', $result['metadata_keys'], 'Should contain key1');
        $this->assertContains('key2', $result['metadata_keys'], 'Should contain key2');
    }
}
