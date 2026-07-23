<?php

namespace App\Console\Commands;

use App\Models\MarketProduct;
use App\Models\Product;
use App\Models\SyncRun;
use Illuminate\Console\Command;

class IkeaStatus extends Command
{
    protected $signature = 'ikea:status';

    protected $description = 'Show catalog health: products per market, sync history and stale data';

    public function handle(): int
    {
        $staleBefore = now()->subDays(config('ikea.product_stale_after_days'));

        $this->info('Products per market');
        $this->table(
            ['Market', 'Active', 'Discontinued', 'Stale (not checked recently)'],
            MarketProduct::query()
                ->selectRaw('market')
                ->selectRaw("sum(case when status = 'active' then 1 else 0 end) as active")
                ->selectRaw("sum(case when status = 'discontinued' then 1 else 0 end) as discontinued")
                ->selectRaw('sum(case when last_checked_at < ? then 1 else 0 end) as stale', [$staleBefore])
                ->groupBy('market')
                ->orderBy('market')
                ->get()
                ->map(fn (MarketProduct $row) => [$row->market, $row->active, $row->discontinued, $row->stale])
                ->all(),
        );

        $this->newLine();
        $this->info('Total products: '.Product::query()->count());

        $this->newLine();
        $this->info('Last 10 sync runs');
        $this->table(
            ['When', 'Type', 'Market', 'Status', 'Stats', 'Error'],
            SyncRun::query()->latest('started_at')->limit(10)->get()
                ->map(fn (SyncRun $run) => [
                    $run->started_at->toDateTimeString(),
                    $run->type,
                    $run->market,
                    $run->status,
                    json_encode($run->stats),
                    str($run->error ?? '')->limit(60),
                ])
                ->all(),
        );

        $failures = SyncRun::query()->where('status', 'failed')->where('started_at', '>=', now()->subDay())->count();

        if ($failures > 0) {
            $this->warn("{$failures} failed sync runs in the last 24 hours.");
        }

        return self::SUCCESS;
    }
}
