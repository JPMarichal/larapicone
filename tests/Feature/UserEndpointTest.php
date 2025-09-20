<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_authenticated_user_data()
    {
        // Create a test user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'google_id' => '12345',
            'avatar' => 'https://example.com/avatar.jpg'
        ]);

        // Authenticate the user using Sanctum
        $token = $user->createToken('test-token')->plainTextToken;

        // Make the request with the token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/user');

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'google_id' => '12345',
                'avatar' => 'https://example.com/avatar.jpg'
            ])
            ->assertJsonMissing(['password', 'remember_token']);
    }

    /** @test */
    public function it_returns_unauthorized_for_guests()
    {
        // Make request without authentication
        $response = $this->getJson('/api/user');
        
        // Assert unauthorized response
        $response->assertStatus(401);
    }
}
