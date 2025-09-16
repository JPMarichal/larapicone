<?php

return [
    'api_key' => env('PINECONE_API_KEY'),
    'environment' => env('PINECONE_ENVIRONMENT', 'production'),
    'index' => env('PINECONE_INDEX', 'kanoniko'),
    'namespace' => env('PINECONE_NAMESPACE', 'scriptures'),
    'timeout' => 30,
];
