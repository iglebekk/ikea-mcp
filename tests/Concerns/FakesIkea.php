<?php

namespace Tests\Concerns;

use Database\Seeders\MarketSeeder;
use Illuminate\Support\Facades\Http;

/**
 * Test helpers: seeded markets and Http fakes for the IKEA endpoints.
 */
trait FakesIkea
{
    protected function seedMarkets(): void
    {
        $this->seed(MarketSeeder::class);
    }

    protected function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/{$name}.json")), true);
    }

    protected function fakeIkea(array $overrides = []): void
    {
        Http::fake(array_merge([
            'sik.search.blue.cdtapps.com/*' => Http::response($this->fixture('search-response')),
            'www.ikea.com/*/meta-data/navigation/stores-detailed.json' => Http::response($this->fixture('stores-response')),
            'www.ikea.com/*/products/*' => Http::response($this->fixture('pip-response')),
            'api.ingka.ikea.com/cia/availabilities/*' => Http::response($this->fixture('availability-response')),
        ], $overrides));
    }
}
