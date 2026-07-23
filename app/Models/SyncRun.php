<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'market', 'language', 'status', 'stats', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
