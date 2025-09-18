<?php

namespace Tests\Unit\Services;

use App\Services\SearchService;
use App\Services\Interfaces\PineconeClientInterface;
use App\Services\Interfaces\EmbeddingServiceInterface;
use PHPUnit\Framework\TestCase;

class SearchServiceTest extends TestCase
{
    private $pineconeClientMock;
    private $embeddingServiceMock;
    private $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->pineconeClientMock = $this->createMock(PineconeClientInterface::class);
        $this->embeddingServiceMock = $this->createMock(EmbeddingServiceInterface::class);
        
        // Create the service with mocked dependencies
        $this->searchService = new SearchService(
            $this->pineconeClientMock,
            $this->embeddingServiceMock
        );
    }

    /** @test */
    public function it_performs_semantic_search()
    {
        // Mock embedding generation
        $this->embeddingServiceMock->method('generateEmbedding')
            ->with('test query')
            ->willReturn([0.1, 0.2, 0.3]);
            
        $this->embeddingServiceMock->method('getModelName')
            ->willReturn('test-model');
            
        $this->embeddingServiceMock->method('getDimension')
            ->willReturn(3);
        
        // Mock Pinecone response
        $mockResponse = [
            'matches' => [
                [
                    'id' => 'test-1',
                    'score' => 0.95,
                    'metadata' => ['text' => 'test result']
                ]
            ]
        ];
        
        $this->pineconeClientMock->method('query')
            ->willReturn($mockResponse);
        
        // Execute the search
        $result = $this->searchService->semanticSearch('test query', 5);
        
        // Assertions
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);
        $this->assertEquals('test-1', $result['results'][0]['id']);
        $this->assertEquals(0.95, $result['results'][0]['score']);
        $this->assertArrayHasKey('execution_time_ms', $result);
    }

    /** @test */
    public function it_handles_vector_search()
    {
        // Mock Pinecone response
        $mockResponse = [
            'matches' => [
                [
                    'id' => 'test-1',
                    'score' => 0.95,
                    'metadata' => ['text' => 'test result']
                ]
            ]
        ];
        
        $this->pineconeClientMock->method('query')
            ->willReturn($mockResponse);
            
        $this->embeddingServiceMock->method('getModelName')
            ->willReturn('test-model');
        
        // Execute the search
        $result = $this->searchService->searchByVector([0.1, 0.2, 0.3], 5);
        
        // Assertions
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);
        $this->assertEquals('test-1', $result['results'][0]['id']);
        $this->assertEquals(0.95, $result['results'][0]['score']);
        $this->assertArrayHasKey('execution_time_ms', $result);
    }

    /** @test */
    public function it_returns_empty_results_on_error()
    {
        // Mock embedding generation to throw an exception
        $this->embeddingServiceMock->method('generateEmbedding')
            ->willThrowException(new \RuntimeException('Embedding failed'));
        
        // Execute the search
        $result = $this->searchService->semanticSearch('test query');
        
        // Assertions
        $this->assertArrayHasKey('results', $result);
        $this->assertEmpty($result['results']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Embedding failed', $result['error']);
    }
}
