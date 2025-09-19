<?php

namespace Tests\Unit\Services\Clients;

use App\Services\Clients\PineconeClient as BasePineconeClient;
use GuzzleHttp\Client;

class TestPineconeClient extends BasePineconeClient
{
    /** @var Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    protected function initializeClient(): void
    {
        // Skip the actual client initialization since we're injecting a mock
    }
}
