<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_with_registration_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Opprett bruker');
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ola Nordmann',
            'email' => 'ola@eksempel.no',
            'password' => 'passord123',
            'password_confirmation' => 'passord123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('settings.edit'));

        $this->assertDatabaseHas('users', [
            'email' => 'ola@eksempel.no',
            'name' => 'Ola Nordmann',
        ]);
    }

    public function test_registration_requires_matching_passwords(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ola Nordmann',
            'email' => 'ola@eksempel.no',
            'password' => 'passord123',
            'password_confirmation' => 'noe-annet',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ola@eksempel.no']);

        $response = $this->post('/register', [
            'name' => 'Ola Nordmann',
            'email' => 'ola@eksempel.no',
            'password' => 'passord123',
            'password_confirmation' => 'passord123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
