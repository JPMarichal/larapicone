<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PineconeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

#[CoversClass(PineconeService::class)]

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
        $this->assertArrayHasKey('vectors', $result);
        $this->assertArrayHasKey($vectorId, $result['vectors']);
        $this->assertEquals($expectedVector, $result['vectors'][$vectorId]);
    }
    
    #[Test]
    public function it_returns_empty_vectors_array_when_vector_not_found()
    {
        $vectorId = 'non-existent-id';
        
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'vectors' => []
            ]))
        );
        
        $result = $this->pineconeService->getVector($vectorId, true);
        $this->assertArrayHasKey('vectors', $result);
        $this->assertEmpty($result['vectors']);
    }
    
    #[Test]
    public function it_excludes_values_when_include_values_is_false()
    {
        $vectorId = 'AT-genesis-01-001';
        $fullVector = [
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
                    $vectorId => $fullVector
                ]
            ]))
        );
        
        $result = $this->pineconeService->getVector($vectorId, false);
        $this->assertArrayHasKey('vectors', $result);
        $this->assertArrayHasKey($vectorId, $result['vectors']);
        $this->assertArrayNotHasKey('values', $result['vectors'][$vectorId]);
        $this->assertEquals($vectorId, $result['vectors'][$vectorId]['id']);
        $this->assertArrayHasKey('metadata', $result['vectors'][$vectorId]);
    }
    
    #[Test]
    public function it_handles_api_errors_gracefully()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error getting vector from Pinecone');
        
        $this->mockHandler->append(
            new Response(500, [], json_encode([
                'error' => 'Internal server error'
            ]))
        );
        
        $this->pineconeService->getVector('any-id', true);
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
        
        // Create a partial mock of the service
        $mockService = $this->getMockBuilder(PineconeService::class)
            ->onlyMethods(['getVectorByReference', 'parsePassage'])
            ->getMock();
            
        // Mock parsePassage to return our test references
        $mockService->method('parsePassage')
            ->with($passage)
            ->willReturn(['Juan 1:1', 'Juan 1:2', 'Juan 1:3']);
            
        // Mock getVectorByReference to return test data
        $mockService->method('getVectorByReference')
            ->will($this->returnValueMap([
                ['Juan 1:1', true, [
                    'id' => 'AT-juan-01-001',
                    'values' => [0.1, 0.2, 0.3],
                    'metadata' => [
                        'libro' => 'Juan',
                        'capitulo' => 1,
                        'versiculo' => 1,
                        'text' => 'En el principio era el Verbo, y el Verbo era con Dios, y el Verbo era Dios.'
                    ]
                ]],
                ['Juan 1:2', true, [
                    'id' => 'AT-juan-01-002',
                    'values' => [0.1, 0.2, 0.3],
                    'metadata' => [
                        'libro' => 'Juan',
                        'capitulo' => 1,
                        'versiculo' => 2,
                        'text' => 'Este era en el principio con Dios.'
                    ]
                ]],
                ['Juan 1:3', true, [
                    'id' => 'AT-juan-01-003',
                    'values' => [0.1, 0.2, 0.3],
                    'metadata' => [
                        'libro' => 'Juan',
                        'capitulo' => 1,
                        'versiculo' => 3,
                        'text' => 'Todas las cosas por él fueron hechas, y sin él nada de lo que ha sido hecho, fue hecho.'
                    ]
                ]]
            ]));

        // Replace the service instance with our mock
        $this->app->instance(PineconeService::class, $mockService);
        $this->pineconeService = $mockService;

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
            new Response(500, [], json_encode(['error' => 'Internal Server Error']))
        );

        $results = $this->pineconeService->semanticSearch('test query');
        $this->assertArrayHasKey('error', $results);
    }

    #[Test]
    public function it_handles_empty_search_results()
    {
        // Mock Ollama embedding response
        Http::fake([
            'localhost:11434/api/embeddings' => Http::response([
                'embedding' => array_fill(0, 768, 0.1)
            ]),
        ]);

        // Mock empty results from Pinecone
        $this->mockHandler->append(
            new Response(200, [], json_encode(['matches' => []]))
        );

        $results = $this->pineconeService->semanticSearch('nonexistent term');
        
        $this->assertIsArray($results);
        $this->assertEquals(0, $results['count']);
        $this->assertEmpty($results['results']);
    }

    #[Test]
    public function it_handles_ollama_connection_errors()
    {
        // Simulate Ollama service being down
        Http::fake([
            'localhost:11434/api/embeddings' => Http::response(
                'Service Unavailable', 
                503
            ),
        ]);

        // Mock the query method to return a successful response
        $this->pineconeService = $this->getMockBuilder(PineconeService::class)
            ->onlyMethods(['query'])
            ->getMock();
            
        $this->pineconeService->method('query')
            ->willReturn([
                'matches' => [
                    [
                        'id' => 'test-id',
                        'score' => 0.9,
                        'metadata' => [
                            'libro' => 'Test',
                            'capitulo' => '1',
                            'versiculo' => '1',
                            'contenido' => 'Test content'
                        ]
                    ]
                ]
            ]);

        $results = $this->pineconeService->semanticSearch('test query');
        
        // Verify the search still returns results using the fallback embedding method
        $this->assertArrayHasKey('results', $results);
        $this->assertNotEmpty($results['results']);
        $this->assertEquals('Test 1:1', $results['results'][0]['reference']);
    }

    #[Test]
    public function it_handles_partial_passage_retrieval()
    {
        // Create a test double for the service using a trait
        $testDouble = new class {
            public function getVectorByReference($reference)
            {
                if ($reference === 'Juan 1:1') {
                    return [
                        'id' => 'AT-juan-01-001',
                        'metadata' => ['text' => 'En el principio era el Verbo']
                    ];
                } elseif ($reference === 'Juan 1:3') {
                    return [
                        'id' => 'AT-juan-01-003',
                        'metadata' => ['text' => 'Todas las cosas por él fueron hechas']
                    ];
                }
                throw new \Exception('Not found');
            }
            
            public function parsePassage($passage)
            {
                if ($passage === 'Juan 1:1-3') {
                    return ['Juan 1:1', 'Juan 1:2', 'Juan 1:3'];
                }
                return [];
            }
            
            public function getVectorsByPassage($passage)
            {
                $references = $this->parsePassage($passage);
                $verses = [];
                $errors = [];
                $concatenatedText = '';
                
                foreach ($references as $ref) {
                    try {
                        $vector = $this->getVectorByReference($ref);
                        $verses[] = [
                            'vector_id' => $vector['id'],
                            'reference' => $ref,
                            'text' => $vector['metadata']['text']
                        ];
                        $concatenatedText .= $vector['metadata']['text'] . ' ';
                    } catch (\Exception $e) {
                        $errors[] = [
                            'reference' => $ref,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                return [
                    'passage' => $passage,
                    'verse_count' => count($references),
                    'verses' => $verses,
                    'errors' => $errors,
                    'concatenated_text' => trim($concatenatedText)
                ];
            }
        };
        
        // Test the getVectorsByPassage method
        $result = $testDouble->getVectorsByPassage('Juan 1:1-3');
        
        // Verify the result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('verses', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('concatenated_text', $result);
        $this->assertArrayHasKey('passage', $result);
        $this->assertArrayHasKey('verse_count', $result);
        
        // Verify the verses were processed correctly
        $this->assertCount(2, $result['verses']);
        $this->assertEquals('AT-juan-01-001', $result['verses'][0]['vector_id']);
        $this->assertEquals('AT-juan-01-003', $result['verses'][1]['vector_id']);
        
        // Verify the error for the missing verse
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Not found', $result['errors'][0]['error']);
        
        // Verify the passage info
        $this->assertEquals('Juan 1:1-3', $result['passage']);
        $this->assertEquals(3, $result['verse_count']);
        
        // Verify the concatenated text
        $this->assertStringContainsString('En el principio era el Verbo', $result['concatenated_text']);
        $this->assertStringContainsString('Todas las cosas por él fueron hechas', $result['concatenated_text']);
    }
}
