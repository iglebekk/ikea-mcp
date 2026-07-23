<?php

namespace Tests\Feature;

use App\Models\MarketProduct;
use App\Models\Product;
use App\Services\IkeaApi;
use App\Services\IkeaImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesIkea;
use Tests\TestCase;

class IkeaImporterTest extends TestCase
{
    use FakesIkea, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarkets();
    }

    private function billyCard(array $overrides = []): array
    {
        $this->fakeIkea();

        $card = app(IkeaApi::class)->searchProducts('us', 'en', 'billy')['products'][0];

        return array_merge($card, $overrides);
    }

    public function test_a_search_card_creates_product_translation_market_row_categories_and_variants(): void
    {
        app(IkeaImporter::class)->importCard($this->billyCard(), 'us', 'en');

        $product = Product::query()->where('item_no', '00263850')->firstOrFail();

        $this->assertSame('BILLY', $product->translations()->where('language', 'en')->value('name'));
        $this->assertEquals(89.99, (float) $product->marketProducts()->where('market', 'us')->value('price'));
        $this->assertSame('active', $product->marketProducts()->where('market', 'us')->value('status'));
        $this->assertNotNull($product->first_observed_at);
        $this->assertSame(['90404097'], $product->variants()->pluck('related_item_no')->all());
        $this->assertSame(1, $product->assets()->where('type', 'image')->count());

        $categories = $product->categories()->orderBy('ikea_id')->get();
        $this->assertSame(['10382', 'bc001'], $categories->pluck('ikea_id')->all());
        $this->assertSame('bc001', $categories->firstWhere('ikea_id', '10382')->parent->ikea_id);
    }

    public function test_reimporting_the_same_card_does_not_duplicate_anything(): void
    {
        $importer = app(IkeaImporter::class);
        $importer->importCard($this->billyCard(), 'us', 'en');
        $importer->importCard($this->billyCard(), 'us', 'en');

        $product = Product::query()->where('item_no', '00263850')->firstOrFail();

        $this->assertSame(1, Product::query()->where('item_no', '00263850')->count());
        $this->assertSame(1, $product->translations()->count());
        $this->assertSame(1, $product->marketProducts()->count());
        $this->assertSame(1, $product->variants()->count());
        $this->assertSame(1, $product->categories()->where('ikea_id', '10382')->count());
    }

    public function test_price_changes_are_detected_and_timestamped(): void
    {
        $importer = app(IkeaImporter::class);
        $importer->importCard($this->billyCard(), 'us', 'en');

        $this->travel(1)->days();
        $importer->importCard($this->billyCard(['price' => 79.99]), 'us', 'en');

        $marketProduct = MarketProduct::query()->where('market', 'us')->firstOrFail();

        $this->assertEquals(79.99, (float) $marketProduct->price);
        $this->assertTrue($marketProduct->last_changed_at->isToday());
        $this->assertSame(1, $importer->stats['changed']);
    }

    public function test_empty_upstream_values_never_overwrite_good_data(): void
    {
        $importer = app(IkeaImporter::class);
        $importer->importCard($this->billyCard(), 'us', 'en');

        $importer->importCard($this->billyCard(['description' => null, 'price' => null]), 'us', 'en');

        $product = Product::query()->where('item_no', '00263850')->firstOrFail();

        $this->assertNotNull($product->translations()->value('description'));
        $this->assertEquals(89.99, (float) $product->marketProducts()->value('price'));
    }

    public function test_a_product_in_two_markets_keeps_separate_market_data(): void
    {
        $importer = app(IkeaImporter::class);
        $importer->importCard($this->billyCard(), 'us', 'en');
        $importer->importCard($this->billyCard(['price' => 899.0, 'currency' => 'NOK']), 'no', 'no');

        $product = Product::query()->where('item_no', '00263850')->firstOrFail();

        $this->assertSame(1, Product::query()->where('item_no', '00263850')->count());
        $this->assertSame(2, $product->marketProducts()->count());
        $this->assertEquals(899.0, (float) $product->marketProducts()->where('market', 'no')->value('price'));
        $this->assertSame(2, $product->translations()->count());
    }

    public function test_unobserved_products_are_marked_discontinued_without_deleting_history(): void
    {
        $importer = app(IkeaImporter::class);
        $importer->importCard($this->billyCard(), 'us', 'en');
        $importer->importCard($this->billyCard(['item_no' => '90404097', 'name' => 'BILLY 40']), 'us', 'en');

        $count = $importer->markUnobservedAsDiscontinued('us', ['00263850']);

        $this->assertSame(1, $count);
        $this->assertSame('discontinued', MarketProduct::query()
            ->whereRelation('product', 'item_no', '90404097')
            ->value('status'));
        $this->assertNotNull(Product::query()->where('item_no', '90404097')->first());
    }

    public function test_a_discontinued_product_becomes_active_again_when_observed(): void
    {
        $importer = app(IkeaImporter::class);
        $importer->importCard($this->billyCard(), 'us', 'en');
        $importer->markUnobservedAsDiscontinued('us', ['99999999']);

        $this->assertSame('discontinued', MarketProduct::query()->where('market', 'us')->value('status'));

        $importer->importCard($this->billyCard(), 'us', 'en');

        $this->assertSame('active', MarketProduct::query()->where('market', 'us')->value('status'));
    }

    public function test_imports_bump_the_market_catalog_version(): void
    {
        $before = IkeaImporter::catalogVersion('us');

        app(IkeaImporter::class)->importCard($this->billyCard(), 'us', 'en');

        $this->assertGreaterThan($before, IkeaImporter::catalogVersion('us'));
    }
}
