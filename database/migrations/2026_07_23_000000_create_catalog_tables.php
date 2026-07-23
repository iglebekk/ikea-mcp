<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('country', 2)->unique();
            $table->string('name');
            $table->json('languages');
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('item_no', 8)->unique();
            $table->string('product_type')->nullable();
            $table->string('series')->nullable();
            $table->timestamp('first_observed_at')->nullable();
            $table->timestamp('last_observed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('language', 5);
            $table->string('name');
            $table->string('type_name')->nullable();
            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->json('materials')->nullable();
            $table->json('care_instructions')->nullable();
            $table->json('safety_information')->nullable();
            $table->json('technical_details')->nullable();
            $table->json('measurements')->nullable();
            $table->json('packages')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'language']);
        });

        Schema::create('market_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('market', 2);
            $table->string('currency', 3)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('regular_price', 10, 2)->nullable();
            $table->decimal('campaign_price', 10, 2)->nullable();
            $table->timestamp('campaign_starts_at')->nullable();
            $table->timestamp('campaign_ends_at')->nullable();
            $table->string('url')->nullable();
            $table->string('status')->default('active');
            $table->boolean('online_sellable')->nullable();
            $table->decimal('rating_value', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'market']);
            $table->index(['market', 'status']);
        });

        Schema::create('product_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('market', 2)->nullable();
            $table->string('language', 5)->nullable();
            $table->string('type');
            $table->string('url', 2048);
            $table->string('title')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'type']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('market', 2);
            $table->string('language', 5);
            $table->string('ikea_id');
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['market', 'language', 'ikea_id']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->unique(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('related_item_no', 8);
            $table->string('variant_group')->nullable();
            $table->json('variant_attributes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'related_item_no']);
        });

        Schema::create('stock_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('market', 2);
            $table->string('store_id')->nullable();
            $table->string('store_name')->nullable();
            $table->string('postal_code')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('probability')->nullable();
            $table->date('restock_expected_at')->nullable();
            $table->timestamp('checked_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'market', 'store_id']);
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('market', 2)->nullable();
            $table->string('language', 5)->nullable();
            $table->string('status')->default('running');
            $table->json('stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('stock_statuses');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('product_assets');
        Schema::dropIfExists('market_products');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('markets');
    }
};
