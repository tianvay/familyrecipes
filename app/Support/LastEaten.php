<?php

namespace App\Support;

use App\Models\MealPlanEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Wann gab es ein Rezept zuletzt? Grundlage ist der Essensplan bis einschließlich heute.
 */
final class LastEaten
{
    /**
     * @return Collection<int, CarbonImmutable> recipe_id => letztes Datum
     */
    public static function dates(): Collection
    {
        return MealPlanEntry::query()
            ->whereNotNull('recipe_id')
            ->whereDate('date', '<=', CarbonImmutable::today()->toDateString())
            ->selectRaw('recipe_id, max(date) as last_date')
            ->groupBy('recipe_id')
            ->pluck('last_date', 'recipe_id')
            ->map(fn (string $date) => CarbonImmutable::parse($date)->startOfDay());
    }

    public static function forRecipe(int $recipeId): ?CarbonImmutable
    {
        return self::dates()->get($recipeId);
    }

    /**
     * z. B. „heute“, „gestern“, „vor 3 Tagen“, „vor 2 Wochen“, „vor 4 Monaten“, „noch nie“.
     */
    public static function label(?CarbonImmutable $date): string
    {
        if ($date === null) {
            return 'noch nie';
        }

        $days = (int) $date->diffInDays(CarbonImmutable::today());

        return match (true) {
            $days <= 0 => 'heute',
            $days === 1 => 'gestern',
            $days < 14 => "vor {$days} Tagen",
            $days < 60 => 'vor '.intdiv($days, 7).' Wochen',
            $days < 365 => 'vor '.intdiv($days, 30).' Monaten',
            default => 'vor über einem Jahr',
        };
    }
}
