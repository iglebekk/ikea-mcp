<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create(['password' => Hash::make('gammelt-passord')]);

        $this->actingAs($user)
            ->from(route('settings.edit'))
            ->put('/password', [
                'current_password' => 'gammelt-passord',
                'password' => 'nytt-passord123',
                'password_confirmation' => 'nytt-passord123',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.edit'));

        $this->assertTrue(Hash::check('nytt-passord123', $user->fresh()->password));
    }

    public function test_correct_current_password_must_be_provided(): void
    {
        $user = User::factory()->create(['password' => Hash::make('gammelt-passord')]);

        $this->actingAs($user)
            ->from(route('settings.edit'))
            ->put('/password', [
                'current_password' => 'feil-passord',
                'password' => 'nytt-passord123',
                'password_confirmation' => 'nytt-passord123',
            ])
            ->assertSessionHasErrors('current_password', errorBag: 'updatePassword');
    }
}
