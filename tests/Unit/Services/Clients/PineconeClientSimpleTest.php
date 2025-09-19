<?php

namespace Tests\Unit\Services\Clients;

use App\Services\Clients\PineconeClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class PineconeClientSimpleTest extends TestCase
{
    public function test_simple_query()
    {
        // Create a mock handler
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'matches' => [
                    ['id' => 'test-vector-1', 'score' => 0.95, 'metadata' => ['key' => 'value']]
                ]
            ]))
        ]);

        // Create a client with the mock handler
        $client = new Client([
            'handler' => HandlerStack::create($mockHandler),
            'base_uri' => 'https://test-index-test-env.svc.test-env.pinecone.io/'
        ]);

        // Create a test client that extends PineconeClient
        $testClient = new class($client) extends PineconeClient {
            private $client;
            
            public function __construct($client)
            {
                $this->client = $client;
            }
            
            protected function initializeClient(): void
            {
                $this->client = $this->client;
            }
            
            public function getClient()
            {
                return $this->client;
            }
        };

        // Perform the test
        $result = $testClient->query([0.1, 0.2, 0.3], 1);
        
        // Assertions
        $this->assertArrayHasKey('matches', $result);
        $this->assertCount(1, $result['matches']);
        $this->assertEquals('test-vector-1', $result['matches'][0]['id']);
    }
}
