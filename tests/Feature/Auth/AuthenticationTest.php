<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Logg inn');
    }

    public function test_users_can_authenticate(): void
    {
        $user = User::factory()->create(['password' => Hash::make('passord123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'passord123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('settings.edit'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('passord123')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'feil-passord',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('home'));
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('passord123')]);

        foreach (range(1, 5) as $ignored) {
            $this->post('/login', ['email' => $user->email, 'password' => 'feil']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'feil']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'For mange innloggingsforsøk',
            session('errors')->first('email'),
        );
    }
}
