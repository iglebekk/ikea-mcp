<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'language', 'name', 'type_name', 'description', 'benefits',
        'materials', 'care_instructions', 'safety_information', 'technical_details',
        'measurements', 'packages',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'materials' => 'array',
            'care_instructions' => 'array',
            'safety_information' => 'array',
            'technical_details' => 'array',
            'measurements' => 'array',
            'packages' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
