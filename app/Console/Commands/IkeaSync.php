<?php

namespace App\Console\Commands;

use App\Exceptions\IkeaException;
use App\Models\Category;
use App\Models\SyncRun;
use App\Services\IkeaApi;
use App\Services\IkeaImporter;
use Illuminate\Console\Command;

class IkeaSync extends Command
{
    protected $signature = 'ikea:sync
        {--market= : Market (country code), defaults to config ikea.default_market}
        {--language= : Language, defaults to config ikea.default_language}
        {--query= : Import products matching a free-text search (good for seeding the catalog)}
        {--category= : Sync one IKEA category id}
        {--product= : Sync one product by item number}
        {--all-categories : Re-sync every locally known category for the market}
        {--mark-discontinued : After --all-categories, mark unobserved products as discontinued}';

    protected $description = 'Synchronize products from IKEA.com into the local catalog, gradually and rate limited';

    public function handle(IkeaApi $api, IkeaImporter $importer): int
    {
        $market = strtolower((string) ($this->option('market') ?: config('ikea.default_market')));
        $language = strtolower((string) ($this->option('language') ?: config('ikea.default_language')));

        $run = SyncRun::query()->create([
            'type' => $this->syncType(),
            'market' => $market,
            'language' => $language,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $api->validateMarket($market, $language);
            $observed = [];

            if ($this->option('product') !== null) {
                $details = $api->productDetails($market, $language, (string) $this->option('product'));
                $importer->importDetails($details, $market, $language);
                $observed[] = $details['item_no'];
            }

            if ($this->option('query') !== null) {
                $observed = [...$observed, ...$this->importPages(
                    fn (int $page) => $api->searchProducts($market, $language, (string) $this->option('query'), config('ikea.sync.page_size')),
                    $importer, $market, $language,
                )];
            }

            if ($this->option('category') !== null) {
                $observed = [...$observed, ...$this->syncCategory((string) $this->option('category'), $api, $importer, $market, $language)];
            }

            if ($this->option('all-categories')) {
                $categoryIds = Category::query()
                    ->where('market', $market)
                    ->where('language', $language)
                    ->where('is_active', true)
                    ->pluck('ikea_id');

                if ($categoryIds->isEmpty()) {
                    $this->warn('No local categories yet. Seed the catalog first, e.g.: php artisan ikea:sync --query=billy');
                }

                foreach ($categoryIds as $categoryId) {
                    $observed = [...$observed, ...$this->syncCategory($categoryId, $api, $importer, $market, $language)];
                }

                if ($this->option('mark-discontinued') && $observed !== []) {
                    $count = $importer->markUnobservedAsDiscontinued($market, array_unique($observed));
                    $this->info("Marked {$count} products as discontinued in {$market}.");
                }
            }

            if ($observed === [] && ! $this->option('all-categories')) {
                $this->warn('Nothing to do. Pass --query, --category, --product or --all-categories.');
            }

            $run->update([
                'status' => 'completed',
                'stats' => [...$importer->stats, 'observed' => count(array_unique($observed))],
                'finished_at' => now(),
            ]);

            $this->info(sprintf(
                'Sync completed for %s/%s: %d new, %d changed, %d unchanged.',
                $market, $language, $importer->stats['new'], $importer->stats['changed'], $importer->stats['unchanged'],
            ));

            return self::SUCCESS;
        } catch (IkeaException $e) {
            $run->update([
                'status' => 'failed',
                'stats' => $importer->stats,
                'error' => "[{$e->reason}] {$e->getMessage()}",
                'finished_at' => now(),
            ]);

            $this->error("Sync failed [{$e->reason}]: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * @return array<int, string>
     */
    private function syncCategory(string $categoryId, IkeaApi $api, IkeaImporter $importer, string $market, string $language): array
    {
        $this->line("Syncing category {$categoryId}...");

        return $this->importPages(
            fn (int $page) => $api->categoryProducts($market, $language, $categoryId, config('ikea.sync.page_size'), $page),
            $importer, $market, $language,
        );
    }

    /**
     * Page through a search-style endpoint until it runs dry or max_pages is hit.
     *
     * @param  callable(int): array{products: array<int, array<string, mixed>>, total: int|null}  $fetch
     * @return array<int, string>
     */
    private function importPages(callable $fetch, IkeaImporter $importer, string $market, string $language): array
    {
        $observed = [];

        for ($page = 1; $page <= (int) config('ikea.sync.max_pages'); $page++) {
            $result = $fetch($page);

            if ($result['products'] === []) {
                break;
            }

            foreach ($result['products'] as $card) {
                $importer->importCard($card, $market, $language);
                $observed[] = $card['item_no'];
            }

            if ($result['total'] !== null && count($observed) >= $result['total']) {
                break;
            }
        }

        return $observed;
    }

    private function syncType(): string
    {
        return match (true) {
            $this->option('product') !== null => 'product',
            $this->option('category') !== null => 'category',
            $this->option('query') !== null => 'query',
            (bool) $this->option('all-categories') => 'market',
            default => 'noop',
        };
    }
}
