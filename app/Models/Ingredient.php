<?php

namespace App\Models;

use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['position', 'amount', 'unit', 'name', 'is_optional'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_optional' => 'boolean',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Einzeilige Darstellung, z. B. „200 g Feta (optional)“.
     */
    public function label(): string
    {
        $label = implode(' ', array_filter([$this->amount, $this->unit, $this->name], 'filled'));

        return $this->is_optional ? "{$label} (optional)" : $label;
    }
}
