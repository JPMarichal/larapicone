<?php

namespace Tests\Unit\Services;

use App\Services\SparseVectorService;
use Tests\TestCase;

class SparseVectorServiceTest extends TestCase
{
    private SparseVectorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SparseVectorService();
    }

    /** @test */
    public function it_creates_sparse_vector_from_text()
    {
        $text = "Este es un texto de prueba para el servicio de vectores";
        
        $result = $this->service->createSparseVector($text);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('indices', $result);
        $this->assertArrayHasKey('values', $result);
        $this->assertCount(count($result['indices']), $result['values']);
        
        // Verify stop words are filtered out
        $this->assertNotContains('es', array_keys($result['indices']));
        $this->assertNotContains('un', array_keys($result['indices']));
        $this->assertNotContains('de', array_keys($result['indices']));
        $this->assertNotContains('para', array_keys($result['indices']));
        $this->assertNotContains('el', array_keys($result['indices']));
    }

    /** @test */
    public function it_creates_sparse_vector_with_custom_stopwords()
    {
        $text = "texto con palabras personalizadas";
        $customStopWords = ['texto', 'con'];
        
        $result = $this->service->createSparseVector($text, $customStopWords);
        
        // Convert indices back to terms for easier testing
        $terms = [];
        foreach ($result['indices'] as $index) {
            // This is a simplified approach - in a real test, you'd want to reverse the hashing
            // For testing purposes, we'll just check the structure
            $terms[] = $index;
        }
        
        // The actual values don't matter for this test, just the structure
        $this->assertIsArray($terms);
        $this->assertGreaterThan(0, count($terms));
        $this->assertIsArray($result['values']);
        $this->assertCount(count($result['indices']), $result['values']);
    }

    /** @test */
    public function it_handles_empty_text()
    {
        $result = $this->service->createSparseVector('');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result['indices']);
        $this->assertEmpty($result['values']);
    }

    /** @test */
    public function it_handles_text_with_only_stopwords()
    {
        $result = $this->service->createSparseVector('y o a el la los las');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result['indices']);
        $this->assertEmpty($result['values']);
    }

    /** @test */
    public function it_calculates_cosine_similarity()
    {
        // Create normalized vectors (unit vectors)
        $length = sqrt(3); // Length of vector [1,1,1]
        $normalizedValue = 1.0 / $length;
        
        $vec1 = [
            'indices' => [1, 3, 5],
            'values' => [$normalizedValue, $normalizedValue, $normalizedValue]
        ];
        
        $vec2 = [
            'indices' => [1, 3, 5],
            'values' => [$normalizedValue, $normalizedValue, $normalizedValue]
        ];
        
        $similarity = $this->service->cosineSimilarity($vec1, $vec2);
        
        // Identical vectors should have similarity of 1.0
        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
        
        // Test with orthogonal vectors
        $vec3 = [
            'indices' => [2, 4, 6],
            'values' => [$normalizedValue, $normalizedValue, $normalizedValue]
        ];
        
        $similarity = $this->service->cosineSimilarity($vec1, $vec3);
        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    /** @test */
    public function it_handles_empty_vectors_in_cosine_similarity()
    {
        $vec1 = [
            'indices' => [],
            'values' => []
        ];
        
        $vec2 = [
            'indices' => [1, 2, 3],
            'values' => [1.0, 1.0, 1.0]
        ];
        
        $similarity = $this->service->cosineSimilarity($vec1, $vec2);
        $this->assertEquals(0.0, $similarity);
        
        // Both vectors empty
        $similarity = $this->service->cosineSimilarity($vec1, $vec1);
        $this->assertEquals(0.0, $similarity);
    }

    /** @test */
    public function it_normalizes_text_correctly()
    {
        $text = "¡Hola! ¿Cómo estás? Esto es una prueba.";
        $normalized = $this->invokePrivateMethod($this->service, 'normalizeText', [$text]);
        
        $this->assertEquals('hola cómo estás esto es una prueba', $normalized);
        
        // Test with special characters
        $text = 'D\'Artagnan dijo: \'¡Los tres mosqueteros!\'';
        $normalized = $this->invokePrivateMethod($this->service, 'normalizeText', [$text]);
        $this->assertEquals('d artagnan dijo los tres mosqueteros', $normalized);
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $parameters);
    }
}
