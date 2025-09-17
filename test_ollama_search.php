<?php

require_once 'vendor/autoload.php';

use App\Services\PineconeService;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Ollama-based semantic search...\n\n";

$service = new PineconeService();

// Test the Melquisedec query
$query = "¿Quién fue Melquisedec?";
echo "=== Query: $query ===\n";

try {
    $result = $service->semanticCharacterSearch($query, 5);
    
    echo "Search type: " . $result['search_type'] . "\n";
    echo "Results count: " . count($result['results']) . "\n\n";
    
    foreach ($result['results'] as $i => $r) {
        echo "Result " . ($i + 1) . ":\n";
        echo "  Reference: " . $r['libro'] . " " . $r['capitulo'] . ":" . $r['versiculo'] . "\n";
        echo "  Score: " . round($r['score'], 4) . "\n";
        echo "  Content: " . substr($r['contenido'], 0, 150) . "...\n\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
