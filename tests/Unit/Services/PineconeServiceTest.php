<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PineconeService;
use PHPUnit\Framework\Attributes\Test;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class PineconeServiceTest extends TestCase
{
    private $mockHandler;
    private $pineconeService;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure test settings
        Config::set('pinecone.api_key', 'test-api-key');
        Config::set('pinecone.environment', 'test-env');
        Config::set('pinecone.index', 'test-index');
        Config::set('pinecone.namespace', 'test-namespace');
        Config::set('pinecone.timeout', 30);
        Config::set('services.ollama.base_url', 'http://localhost:11434/');
        Config::set('services.ollama.embed_model', 'nomic-embed-text');
        Config::set('services.ollama.embeddings_endpoint', '/api/embeddings');

        // Create a mock handler for Guzzle
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        // Create service instance with the mocked client
        $this->pineconeService = new PineconeService();
        
        // Inject the mocked client using reflection
        $reflection = new \ReflectionClass($this->pineconeService);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->pineconeService, $client);
    }

    #[Test]
    public function it_initializes_correctly()
    {
        $this->assertInstanceOf(PineconeService::class, $this->pineconeService);
    }

    #[Test]
    public function it_gets_debug_info()
    {
        // Mock Pinecone index stats response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'dimension' => 768,
                'indexFullness' => 0.1,
                'namespaces' => [
                    'test-namespace' => ['vectorCount' => 1000]
                ]
            ]))
        );

        $debugInfo = $this->pineconeService->getDebugInfo();

        $this->assertIsArray($debugInfo);
        $this->assertArrayHasKey('pinecone', $debugInfo);
        $this->assertArrayHasKey('ollama', $debugInfo);
        $this->assertTrue($debugInfo['pinecone']['api_key_configured']);
    }

    #[Test]
    public function it_performs_semantic_search()
    {
        // Mock Ollama embedding response
        Http::fake([
            'localhost:11434/api/embeddings' => Http::response([
                'embedding' => array_fill(0, 768, 0.1)
            ]),
        ]);

        // Mock Pinecone query response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'matches' => [
                    [
                        'id' => 'AT-genesis-14-018',
                        'score' => 0.95,
                        'metadata' => [
                            'libro' => 'Génesis',
                            'capitulo' => 14,
                            'versiculo' => 18,
                            'contenido' => 'Entonces Melquisedec, rey de Salem y sacerdote del Dios Altísimo, sacó pan y vino'
                        ]
                    ]
                ]
            ]))
        );

        $results = $this->pineconeService->semanticSearch('¿Quién fue Melquisedec?');

        $this->assertIsArray($results);
        $this->assertArrayHasKey('query', $results);
        $this->assertArrayHasKey('results', $results);
        $this->assertArrayHasKey('count', $results);
        $this->assertGreaterThan(0, $results['count']);
        $this->assertEquals('Génesis 14:18', $results['results'][0]['reference']);
    }

    #[Test]
    public function it_gets_vector_by_id()
    {
        $vectorId = 'AT-genesis-01-001';
        $expectedVector = [
            'id' => $vectorId,
            'values' => [0.1, 0.2, 0.3],
            'metadata' => [
                'book' => 'Génesis',
                'chapter' => 1,
                'verse' => 1,
                'content' => 'En el principio creó Dios los cielos y la tierra.'
            ]
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'vectors' => [
                    $vectorId => $expectedVector
                ]
            ]))
        );

        $result = $this->pineconeService->getVector($vectorId, true);
        $this->assertEquals($expectedVector, $result);
    }

    #[Test]
    public function it_gets_vector_by_reference()
    {
        $reference = 'Génesis 1:1';
        $expectedVector = [
            'id' => 'AT-genesis-01-001',
            'values' => [0.1, 0.2, 0.3],
            'metadata' => [
                'book' => 'Génesis',
                'chapter' => 1,
                'verse' => 1,
                'content' => 'En el principio creó Dios los cielos y la tierra.'
            ]
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'matches' => [
                    [
                        'id' => 'AT-genesis-01-001',
                        'score' => 0.99,
                        'metadata' => [
                            'book' => 'Génesis',
                            'chapter' => 1,
                            'verse' => 1,
                            'content' => 'En el principio creó Dios los cielos y la tierra.'
                        ]
                    ]
                ]
            ]))
        );

        $result = $this->pineconeService->getVectorByReference($reference, true);
        $this->assertEquals($expectedVector, $result);
    }

    #[Test]
    public function it_gets_vectors_by_passage()
    {
        $passage = 'Juan 1:1-3';
        
        // Mock response for multiple verses
        $this->mockHandler->append(
            // Response for Juan 1:1
            new Response(200, [], json_encode([
                'matches' => [
                    [
                        'id' => 'AT-juan-01-001',
                        'score' => 0.99,
                        'metadata' => [
                            'book' => 'Juan',
                            'chapter' => 1,
                            'verse' => 1,
                            'content' => 'En el principio era el Verbo, y el Verbo era con Dios, y el Verbo era Dios.'
                        ]
                    ]
                ]
            ])),
            // Response for Juan 1:2
            new Response(200, [], json_encode([
                'matches' => [
                    [
                        'id' => 'AT-juan-01-002',
                        'score' => 0.99,
                        'metadata' => [
                            'book' => 'Juan',
                            'chapter' => 1,
                            'verse' => 2,
                            'content' => 'Este era en el principio con Dios.'
                        ]
                    ]
                ]
            ])),
            // Response for Juan 1:3
            new Response(200, [], json_encode([
                'matches' => [
                    [
                        'id' => 'AT-juan-01-003',
                        'score' => 0.99,
                        'metadata' => [
                            'book' => 'Juan',
                            'chapter' => 1,
                            'verse' => 3,
                            'content' => 'Todas las cosas por él fueron hechas, y sin él nada de lo que ha sido hecho, fue hecho.'
                        ]
                    ]
                ]
            ]))
        );

        $result = $this->pineconeService->getVectorsByPassage($passage, true);
        
        $this->assertIsArray($result);
        $this->assertEquals($passage, $result['passage']);
        $this->assertEquals(3, $result['verse_count']);
        $this->assertCount(3, $result['verses']);
        $this->assertStringContainsString('En el principio era el Verbo', $result['concatenated_text']);
    }

    #[Test]
    public function it_handles_errors_gracefully()
    {
        // Test error handling for semantic search
        $this->mockHandler->append(
            new Response(500, [], 'Internal Server Error')
        );

        $results = $this->pineconeService->semanticSearch('test query');
        $this->assertArrayHasKey('error', $results);
    }
}
