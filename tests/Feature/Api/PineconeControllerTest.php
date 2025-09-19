<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Services\PineconeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class PineconeControllerTest extends TestCase
{
    private MockInterface $pineconeServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock of PineconeService
        $this->pineconeServiceMock = $this->mock(PineconeService::class);
    }

    #[Test]
    public function it_returns_debug_info()
    {
        $debugInfo = [
            'pinecone' => [
                'status' => 'connected',
                'index' => 'test-index',
                'dimension' => 768,
                'vector_count' => 1000
            ],
            'ollama' => [
                'status' => 'connected',
                'model' => 'nomic-embed-text'
            ]
        ];

        $this->pineconeServiceMock
            ->shouldReceive('getDebugInfo')
            ->once()
            ->andReturn($debugInfo);

        $response = $this->getJson('/api/pinecone/debug');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $debugInfo
            ]);
    }

    /** @test */
    public function it_performs_semantic_search()
    {
        $query = '¿Quién fue Melquisedec?';
        $expectedResults = [
            [
                'id' => 'AT-genesis-14-018',
                'score' => 0.95,
                'reference' => 'Génesis 14:18',
                'content' => 'Entonces Melquisedec, rey de Salem y sacerdote del Dios Altísimo, sacó pan y vino',
                'metadata' => [
                    'book' => 'Génesis',
                    'chapter' => 14,
                    'verse' => 18
                ]
            ]
        ];

        $this->pineconeServiceMock
            ->shouldReceive('semanticSearch')
            ->once()
            ->with($query, 5, [])
            ->andReturn([
                'query' => $query,
                'count' => 1,
                'results' => $expectedResults
            ]);

        $response = $this->postJson('/api/pinecone/search/semantic', [
            'query' => $query,
            'limit' => 5
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'query' => $query,
                    'count' => 1,
                    'results' => $expectedResults
                ]
            ]);
    }

    #[Test]
    public function it_handles_invalid_semantic_search_request()
    {
        $response = $this->postJson('/api/pinecone/search/semantic', [
            'query' => 'ab' // Too short
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    #[Test]
    public function it_gets_vector_by_id()
    {
        $vectorId = 'AT-genesis-01-001';
        $vectorData = [
            'id' => $vectorId,
            'values' => [0.1, 0.2, 0.3],
            'metadata' => [
                'book' => 'Génesis',
                'chapter' => 1,
                'verse' => 1,
                'content' => 'En el principio creó Dios los cielos y la tierra.'
            ]
        ];

        $this->pineconeServiceMock
            ->shouldReceive('getVector')
            ->once()
            ->with($vectorId, false)
            ->andReturn($vectorData);

        $response = $this->getJson("/api/pinecone/vector/{$vectorId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $vectorData
            ]);
    }

    #[Test]
    public function it_gets_vector_by_reference()
    {
        $reference = 'Génesis 1:1';
        $vectorData = [
            'id' => 'AT-genesis-01-001',
            'values' => [0.1, 0.2, 0.3],
            'metadata' => [
                'book' => 'Génesis',
                'chapter' => 1,
                'verse' => 1,
                'content' => 'En el principio creó Dios los cielos y la tierra.'
            ]
        ];

        $this->pineconeServiceMock
            ->shouldReceive('getVectorByReference')
            ->once()
            ->with($reference, false)
            ->andReturn($vectorData);

        $response = $this->postJson('/api/pinecone/vector/reference', [
            'reference' => $reference
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $vectorData
            ]);
    }

    #[Test]
    public function it_gets_vectors_by_passage()
    {
        $passage = 'Juan 1:1-3';
        $expectedVerses = [
            'Juan 1:1',
            'Juan 1:2',
            'Juan 1:3'
        ];

        $mockResults = [];
        foreach ($expectedVerses as $verse) {
            $mockResults[] = [
                'id' => strtolower(str_replace([' ', ':'], '-', 'AT-' . $verse)),
                'metadata' => [
                    'reference' => $verse,
                    'content' => 'Contenido de ' . $verse
                ]
            ];
        }

        $this->pineconeServiceMock
            ->shouldReceive('getVectorsByPassage')
            ->once()
            ->with($passage, false)
            ->andReturn([
                'passage' => $passage,
                'verse_count' => count($expectedVerses),
                'verses' => $mockResults,
                'concatenated_text' => 'Contenido de Juan 1:1 Contenido de Juan 1:2 Contenido de Juan 1:3',
                'errors' => []
            ]);

        $response = $this->postJson('/api/pinecone/vector/passage', [
            'passage' => $passage
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'passage' => $passage,
                    'verse_count' => 3,
                    'verses' => $mockResults
                ]
            ]);
    }

    #[Test]
    public function it_handles_invalid_passage_format()
    {
        $response = $this->postJson('/api/pinecone/vector/passage', [
            'passage' => 'Invalid-Passage-Format'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['passage']);
    }
}
