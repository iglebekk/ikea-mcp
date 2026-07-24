<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The MCP endpoint is protected by the Passport (auth:api) guard, which
        // needs both encryption keys to resolve. Regenerate them (forcing over
        // any partial/corrupt pair) whenever either is missing, so this test
        // stands on its own on a fresh checkout (e.g. CI).
        if (! file_exists(storage_path('oauth-private.key')) || ! file_exists(storage_path('oauth-public.key'))) {
            Artisan::call('passport:keys', ['--no-interaction' => true, '--force' => true]);
        }
    }

    public function test_unauthenticated_mcp_request_is_rejected_with_401_and_www_authenticate(): void
    {
        $response = $this->postJson('/mcp/ikea', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

        $response->assertStatus(401);
        $this->assertNotEmpty(
            $response->headers->get('WWW-Authenticate'),
            'A 401 from the MCP endpoint must advertise how to authenticate.',
        );
    }
}
