<?php

namespace Database\Seeders;

use App\Models\Market;
use Illuminate\Database\Seeder;

class MarketSeeder extends Seeder
{
    /**
     * Known IKEA.com retail markets with their site languages and currencies.
     * Compiled from IKEA.com's country selector; adjust as IKEA adds or drops markets.
     */
    public function run(): void
    {
        $markets = [
            ['at', 'Austria', ['de', 'en'], 'EUR'],
            ['au', 'Australia', ['en'], 'AUD'],
            ['be', 'Belgium', ['nl', 'fr', 'en'], 'EUR'],
            ['ca', 'Canada', ['en', 'fr'], 'CAD'],
            ['ch', 'Switzerland', ['de', 'fr', 'it', 'en'], 'CHF'],
            ['cz', 'Czech Republic', ['cs', 'en'], 'CZK'],
            ['de', 'Germany', ['de', 'en'], 'EUR'],
            ['dk', 'Denmark', ['da', 'en'], 'DKK'],
            ['es', 'Spain', ['es', 'ca', 'eu', 'gl', 'en'], 'EUR'],
            ['fi', 'Finland', ['fi', 'sv', 'en'], 'EUR'],
            ['fr', 'France', ['fr'], 'EUR'],
            ['gb', 'United Kingdom', ['en'], 'GBP'],
            ['hu', 'Hungary', ['hu', 'en'], 'HUF'],
            ['ie', 'Ireland', ['en'], 'EUR'],
            ['it', 'Italy', ['it', 'en'], 'EUR'],
            ['jp', 'Japan', ['ja', 'en'], 'JPY'],
            ['kr', 'South Korea', ['ko', 'en'], 'KRW'],
            ['nl', 'Netherlands', ['nl', 'en'], 'EUR'],
            ['no', 'Norway', ['no', 'en'], 'NOK'],
            ['pl', 'Poland', ['pl', 'en'], 'PLN'],
            ['pt', 'Portugal', ['pt', 'en'], 'EUR'],
            ['se', 'Sweden', ['sv', 'en'], 'SEK'],
            ['sg', 'Singapore', ['en'], 'SGD'],
            ['sk', 'Slovakia', ['sk', 'en'], 'EUR'],
            ['us', 'United States', ['en', 'es'], 'USD'],
        ];

        foreach ($markets as [$country, $name, $languages, $currency]) {
            Market::query()->updateOrCreate(
                ['country' => $country],
                ['name' => $name, 'languages' => $languages, 'currency' => $currency, 'is_active' => true],
            );
        }
    }
}
