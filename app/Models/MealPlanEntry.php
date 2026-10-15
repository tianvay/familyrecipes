<?php

namespace App\Models;

use App\Support\Week;
use Carbon\CarbonInterface;
use Database\Factories\MealPlanEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Das festgelegte Gericht eines Tages (genau eins pro Datum).
 */
class MealPlanEntry extends Model
{
    /** @use HasFactory<MealPlanEntryFactory> */
    use HasFactory;

    protected $fillable = ['date', 'recipe_id', 'title', 'planned_by'];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function plannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planned_by');
    }

    #[Scope]
    protected function forWeek(Builder $query, Week $week): void
    {
        $query->betweenDates($week->start, $week->end());
    }

    /**
     * whereDate statt whereBetween: vergleicht nur das Datum, auch wenn die Datenbank
     * (z. B. SQLite) den Wert als „Y-m-d H:i:s“ speichert.
     */
    #[Scope]
    protected function betweenDates(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString());
    }

    /**
     * Gleiche Gerichte erkennen – passend zu MealSuggestion::dishKey().
     */
    public function dishKey(): string
    {
        return $this->recipe_id ? "r{$this->recipe_id}" : 't:'.mb_strtolower(trim($this->title));
    }

    /**
     * Aktueller Rezepttitel, sonst die gespeicherte Kopie bzw. der Freitext.
     */
    public function displayTitle(): string
    {
        return $this->recipe?->title ?? $this->title;
    }
}
