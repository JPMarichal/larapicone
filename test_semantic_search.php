<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $service = app('App\Services\PineconeService');
    
    echo "Testing Pinecone integrated semantic search...\n\n";
    
    // Test 1: Melquisedec query
    echo "=== Test 1: ¿Quién fue Melquisedec? ===\n";
    $query = '¿Quién fue Melquisedec?';
    $result = $service->semanticSearch($query, 5);
    echo "Search type: " . ($result['search_type'] ?? 'unknown') . "\n";
    echo "Results count: " . $result['count'] . "\n";
    
    if (!empty($result['results'])) {
        foreach ($result['results'] as $i => $resultItem) {
            echo "Result " . ($i + 1) . ":\n";
            echo "  Book: " . ($resultItem['libro'] ?? 'Unknown') . "\n";
            echo "  Chapter: " . ($resultItem['capitulo'] ?? 'Unknown') . "\n";
            echo "  Verse: " . ($resultItem['versiculo'] ?? 'Unknown') . "\n";
            echo "  Score: " . round($resultItem['score'], 4) . "\n";
            echo "  Content: " . substr($resultItem['contenido'], 0, 150) . "...\n\n";
            echo "  Book: " . ($result['libro'] ?? 'Unknown') . "\n";
            echo "  Chapter: " . ($result['capitulo'] ?? 'Unknown') . "\n";
            echo "  Verse: " . ($result['versiculo'] ?? 'Unknown') . "\n";
            echo "  Score: " . round($result['score'], 4) . "\n";
            echo "  Content: " . substr($result['contenido'], 0, 150) . "...\n\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
    
    // Test 2: Capitán Moroni query
    echo "=== Test 2: ¿Quién fue el capitán Moroni? ===\n";
    $result2 = $service->semanticCharacterSearch('¿Quién fue el capitán Moroni?', 3);
    echo "Search type: " . ($result2['search_type'] ?? 'unknown') . "\n";
    echo "Results count: " . $result2['count'] . "\n";
    
    if (!empty($result2['results'])) {
        foreach ($result2['results'] as $i => $result) {
            echo "Result " . ($i + 1) . ":\n";
            echo "  Book: " . ($result['libro'] ?? 'Unknown') . "\n";
            echo "  Chapter: " . ($result['capitulo'] ?? 'Unknown') . "\n";
            echo "  Verse: " . ($result['versiculo'] ?? 'Unknown') . "\n";
            echo "  Score: " . round($result['score'], 4) . "\n";
            echo "  Content: " . substr($result['contenido'], 0, 150) . "...\n\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
    
    // Test 3: Mirra query
    echo "=== Test 3: ¿Qué dicen las escrituras sobre la mirra? ===\n";
    $result3 = $service->semanticCharacterSearch('¿Qué dicen las escrituras sobre la mirra?', 3);
    echo "Search type: " . ($result3['search_type'] ?? 'unknown') . "\n";
    echo "Results count: " . $result3['count'] . "\n";
    
    if (!empty($result3['results'])) {
        foreach ($result3['results'] as $i => $result) {
            echo "Result " . ($i + 1) . ":\n";
            echo "  Book: " . ($result['libro'] ?? 'Unknown') . "\n";
            echo "  Chapter: " . ($result['capitulo'] ?? 'Unknown') . "\n";
            echo "  Verse: " . ($result['versiculo'] ?? 'Unknown') . "\n";
            echo "  Score: " . round($result['score'], 4) . "\n";
            echo "  Content: " . substr($result['contenido'], 0, 150) . "...\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
