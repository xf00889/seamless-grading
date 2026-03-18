<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_unavailable_when_public_registration_is_disabled(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_public_registration_submission_is_blocked_when_disabled(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_new_users_can_register_only_when_public_registration_is_enabled(): void
    {
        config()->set('auth.allow_public_registration', true);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registration was received. You can sign in after an administrator assigns your role.');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
        $this->assertNull(User::where('email', 'test@example.com')->first()?->roles()->first());
    }
}
