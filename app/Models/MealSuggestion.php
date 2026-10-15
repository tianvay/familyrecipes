<?php

namespace App\Models;

use App\Support\Week;
use Database\Factories\MealSuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vorschlag eines Familienmitglieds für die Woche: ein Rezept oder ein Freitext.
 */
class MealSuggestion extends Model
{
    /** @use HasFactory<MealSuggestionFactory> */
    use HasFactory;

    protected $fillable = ['week_start', 'recipe_id', 'title', 'preferred_day', 'note'];

    protected function casts(): array
    {
        return [
            'week_start' => 'immutable_date',
            'preferred_day' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    #[Scope]
    protected function forWeek(Builder $query, Week $week): void
    {
        $query->whereDate('week_start', $week->start);
    }

    public function week(): Week
    {
        return Week::containing($this->week_start);
    }

    /**
     * Gleiche Gerichte erkennen: gleiches Rezept oder gleicher Freitext (ohne Groß-/Kleinschreibung).
     */
    public function dishKey(): string
    {
        return $this->recipe_id ? "r{$this->recipe_id}" : 't:'.mb_strtolower(trim($this->title));
    }
}
