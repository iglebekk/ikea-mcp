<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\IkeaServer;
use App\Mcp\Tools\ListMarketsTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesIkea;
use Tests\TestCase;

class UserMarketDefaultsTest extends TestCase
{
    use FakesIkea, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarkets();
    }

    public function test_defaults_reflect_the_authenticated_users_chosen_market(): void
    {
        $user = User::factory()->create(['market' => 'no', 'language' => 'no']);

        IkeaServer::actingAs($user)->tool(ListMarketsTool::class)
            ->assertOk()
            ->assertSee('"market":"no"')
            ->assertSee('"language":"no"');
    }

    public function test_a_user_without_a_chosen_market_falls_back_to_an_active_market_not_the_environment(): void
    {
        $user = User::factory()->create(['market' => null, 'language' => null]);

        // The fallback is the first active market from the database (ordered by
        // country code -> "at"), never the IKEA_MARKET environment value.
        IkeaServer::actingAs($user)->tool(ListMarketsTool::class)
            ->assertOk()
            ->assertSee('"market":"at"');
    }
}
