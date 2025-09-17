<?php

$file = 'd:\myapps\larapicone\versiculos.jsonl';
$mappings = [];
$handle = fopen($file, 'r');

if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $data = json_decode($line, true);
        if ($data && isset($data['id']) && isset($data['metadata']['book'])) {
            $id = $data['id'];
            $book = $data['metadata']['book'];
            $volume_code = $data['metadata']['volume_code'];
            
            // Extract the book slug from the ID
            $parts = explode('-', $id);
            if (count($parts) >= 3) {
                $book_slug = '';
                
                if ($volume_code === 'BM') {
                    // For Book of Mormon: BM-1-nefi-01-001 or BM-mosiah-01-001
                    if (count($parts) >= 5) {
                        // Has number: BM-1-nefi-01-001
                        $book_slug = $parts[1] . '-' . $parts[2];
                    } else if (count($parts) >= 4) {
                        // No number: BM-mosiah-01-001
                        $book_slug = $parts[1];
                    }
                } else {
                    // For other volumes: AT-genesis-01-001
                    $book_slug = $parts[1];
                }
                
                if (!isset($mappings[$book])) {
                    $mappings[$book] = ['slug' => $book_slug, 'volume' => $volume_code];
                }
            }
        }
    }
    fclose($handle);
}

echo json_encode($mappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
