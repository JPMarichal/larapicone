<?php

namespace Tests\Unit\Services;

use App\Services\EmbeddingService;
use App\Services\Interfaces\EmbeddingServiceInterface;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    private EmbeddingService $embeddingService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the HTTP client
        Http::fake();
        
        $this->embeddingService = new EmbeddingService();
    }

    /** @test */
    public function it_implements_embedding_service_interface()
    {
        $this->assertInstanceOf(EmbeddingServiceInterface::class, $this->embeddingService);
    }

    /** @test */
    public function it_generates_embeddings()
    {
        $testText = "This is a test text";
        $fakeEmbedding = array_fill(0, 768, 0.1);
        
        // Mock the HTTP response
        Http::fake([
            'localhost:11434/api/embeddings' => Http::response([
                'embedding' => $fakeEmbedding
            ])
        ]);

        $result = $this->embeddingService->generateEmbedding($testText);
        
        $this->assertEquals($fakeEmbedding, $result);
        
        // Verify the request was made
        Http::assertSent(function ($request) use ($testText) {
            return $request->url() === 'http://localhost:11434/api/embeddings' &&
                   $request['model'] === 'nomic-embed-text' &&
                   $request['prompt'] === $testText;
        });
    }

    /** @test */
    public function it_handles_embedding_errors()
    {
        $testText = "This is a test text";
        
        // Mock a failed HTTP response
        Http::fake([
            'localhost:11434/api/embeddings' => Http::response([
                'error' => 'Embedding generation failed'
            ], 500)
        ]);

        $result = $this->embeddingService->generateEmbedding($testText);
        
        // Should return an empty array on error
        $this->assertEquals([], $result);
    }

    /** @test */
    public function it_handles_http_exceptions()
    {
        $testText = "This is a test text";
        
        // Mock a connection error
        Http::fake([
            'localhost:11434/api/embeddings' => Http::response([
                'error' => 'Connection error'
            ], 500)
        ]);

        $result = $this->embeddingService->generateEmbedding($testText);
        
        // Should return an empty array on exception
        $this->assertEquals([], $result);
    }
}
