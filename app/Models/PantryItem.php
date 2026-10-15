<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Zutat, die die Familie „immer da hat“ (z. B. Salz, Pfeffer, Öl).
 * Abgleich über den ganzen Namen, ohne Groß-/Kleinschreibung.
 */
class PantryItem extends Model
{
    protected $fillable = ['name', 'added_by'];

    protected static function booted(): void
    {
        static::saving(fn (PantryItem $item) => $item->key = self::keyFor($item->name));
    }

    public static function keyFor(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
