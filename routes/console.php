<?php

use App\Models\MarketProduct;
use App\Models\StockStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Nightly catalog refresh: every market that already has synchronized data is
 * re-synced category by category, spread out to stay gentle towards IKEA.
 * Stock statuses are short-lived working data and are pruned daily.
 */

Schedule::call(function (): void {
    MarketProduct::query()
        ->distinct()
        ->pluck('market')
        ->each(fn (string $market) => Artisan::queue('ikea:sync', [
            '--market' => $market,
            '--all-categories' => true,
            '--mark-discontinued' => true,
        ]));
})->dailyAt('02:30')->name('ikea-nightly-sync')->onOneServer();

Schedule::call(fn () => StockStatus::query()->where('checked_at', '<', now()->subDay())->delete())
    ->dailyAt('04:00')
    ->name('ikea-prune-stock')
    ->onOneServer();
