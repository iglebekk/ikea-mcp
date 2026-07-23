<?php

use Database\Seeders\MarketSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The list of supported IKEA markets is required reference data the app
     * cannot work without, so it is populated as part of `migrate` rather than
     * a separate, easy-to-forget seed step. MarketSeeder is idempotent
     * (updateOrCreate), so running it here and via db:seed is safe.
     */
    public function up(): void
    {
        (new MarketSeeder)->run();
    }

    /**
     * Reference data is left in place on rollback; other catalog rows depend
     * on it, and re-migrating simply upserts the same markets.
     */
    public function down(): void
    {
        //
    }
};
