<?php

namespace App\Models;

use Database\Factories\StepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Step extends Model
{
    /** @use HasFactory<StepFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['position', 'body', 'parallel'];

    protected $attributes = [
        'parallel' => false,
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'parallel' => 'boolean',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
