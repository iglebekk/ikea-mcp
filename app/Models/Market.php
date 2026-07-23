<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Market extends Model
{
    use HasFactory;

    protected $fillable = ['country', 'name', 'languages', 'currency', 'is_active'];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function supportsLanguage(string $language): bool
    {
        return in_array(strtolower($language), $this->languages, true);
    }
}
