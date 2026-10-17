<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Das Foto eines Rezepts, als Binärdaten in der Datenbank – damit alle
 * Installationen im Heimnetz dieselben Fotos sehen.
 */
class RecipeImage extends Model
{
    protected $fillable = ['mime_type', 'size', 'data'];

    /** Die Binärdaten nie versehentlich serialisieren (JSON, Logs). */
    protected $hidden = ['data'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
