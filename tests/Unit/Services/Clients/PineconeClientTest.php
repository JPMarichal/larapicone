<?php

namespace Tests\Unit\Services\Clients;

use App\Services\Clients\PineconeClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PineconeClientTest extends TestCase
{
    use RefreshDatabase;

    private PineconeClient $pineconeClient;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock handler for Guzzle
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        
        // Create a Guzzle client with the mock handler
        $client = new Client(['handler' => $handlerStack]);
        
        // Create the PineconeClient with test configuration
        $this->pineconeClient = new PineconeClient();
        
        // Replace the HTTP client with our mock
        $reflection = new \ReflectionClass($this->pineconeClient);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->pineconeClient, $client);
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
        
        $this->mockHandler->append(new Response(200, [], json_encode($mockResponse)));
        
        $result = $this->pineconeClient->query([0.1, 0.2, 0.3], 1);
        
        $this->assertArrayHasKey('matches', $result);
        $this->assertCount(1, $result['matches']);
        $this->assertEquals('test-vector-1', $result['matches'][0]['id']);
    }

    /** @test */
    public function it_can_upsert_vectors()
    {
        $mockResponse = ['upsertedCount' => 1];
        $this->mockHandler->append(new Response(200, [], json_encode($mockResponse)));
        
        $vectors = [
            [
                'id' => 'test-vector-1',
                'values' => [0.1, 0.2, 0.3],
                'metadata' => ['key' => 'value']
            ]
        ];
        
        $result = $this->pineconeClient->upsertVectors($vectors);
        
        $this->assertArrayHasKey('upsertedCount', $result);
        $this->assertEquals(1, $result['upsertedCount']);
    }

    /** @test */
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
        
        $this->mockHandler->append(new Response(200, [], json_encode($mockResponse)));
        
        $result = $this->pineconeClient->getVector('test-vector-1', true);
        
        $this->assertArrayHasKey('vectors', $result);
        $this->assertArrayHasKey('test-vector-1', $result['vectors']);
    }

    /** @test */
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
        
        $this->mockHandler->append(new Response(200, [], json_encode($mockStatsResponse)));
        $this->mockHandler->append(new Response(200, [], json_encode($mockListResponse)));
        
        $result = $this->pineconeClient->getDebugInfo();
        
        $this->assertArrayHasKey('pinecone', $result);
        $this->assertArrayHasKey('stats', $result['pinecone']);
        $this->assertArrayHasKey('sample_vectors', $result);
        $this->assertArrayHasKey('metadata_keys', $result);
        $this->assertContains('key1', $result['metadata_keys']);
        $this->assertContains('key2', $result['metadata_keys']);
    }
}
