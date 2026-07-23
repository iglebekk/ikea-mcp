<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesIkea;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use FakesIkea, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarkets();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/settings')->assertRedirect(route('login'));
    }

    public function test_settings_page_lists_markets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertSee('Norway')
            ->assertSee('Innstillinger');
    }

    public function test_user_can_choose_market_and_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings', [
            'market' => 'no',
            'language' => 'no',
        ])->assertRedirect(route('settings.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'market' => 'no',
            'language' => 'no',
        ]);
    }

    public function test_market_must_be_a_supported_market(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings', [
            'market' => 'zz',
            'language' => 'en',
        ])->assertSessionHasErrors('market');
    }

    public function test_language_must_be_supported_by_the_market(): void
    {
        $user = User::factory()->create();

        // Norway supports no/en, not German.
        $this->actingAs($user)->put('/settings', [
            'market' => 'no',
            'language' => 'de',
        ])->assertSessionHasErrors('language');
    }
}
