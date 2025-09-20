<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResponseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_model_has_expected_attributes()
    {
        // Create a user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'google_id' => '12345',
            'avatar' => 'https://example.com/avatar.jpg'
        ]);

        // Check the user has the expected attributes
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('12345', $user->google_id);
        $this->assertEquals('https://example.com/avatar.jpg', $user->avatar);
        
        // Check password is hashed
        $this->assertNotNull($user->password);
        $this->assertNotEquals('password', $user->password);
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('password', $user->password),
            'The password should be hashed'
        );
    }
}
