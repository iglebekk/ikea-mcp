<?php

namespace Database\Factories;

use App\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRun>
 */
class SyncRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'category',
            'market' => 'us',
            'language' => 'en',
            'status' => 'completed',
            'stats' => ['new' => 0, 'changed' => 0, 'discontinued' => 0],
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }
}
