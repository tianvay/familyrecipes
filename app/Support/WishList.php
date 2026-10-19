<?php

namespace App\Support;

use App\Models\MealPlanEntry;
use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Die Wunschliste der Familie.
 *
 * Ein Wunsch (MealSuggestion) gilt „ab“ seiner Woche (week_start) und bleibt offen, bis dasselbe
 * Gericht an einem Tag ab dieser Woche – und nicht vor dem Tag des Wunsches – im Essensplan steht. „Erfüllt“ wird nicht gespeichert,
 * sondern aus dem Plan berechnet – wird ein Gericht wieder ausgeplant, ist der Wunsch wieder offen.
 */
final class WishList
{
    /**
     * @param  Collection<int, MealSuggestion>  $wishes
     * @param  array<int, CarbonImmutable|null>  $fulfilledOn  Wunsch-ID => Datum im Plan
     */
    private function __construct(
        private readonly Collection $wishes,
        private readonly array $fulfilledOn,
    ) {}

    /**
     * Alle Wünsche, die bis zur angegebenen Woche gestellt wurden (Standard: nächste Woche).
     */
    public static function upTo(?Week $week = null): self
    {
        $week ??= Week::current()->next();

        $wishes = MealSuggestion::query()
            ->whereDate('week_start', '<=', $week->start->toDateString())
            ->with(['recipe:id,title,slug,emoji,color', 'user:id,name'])
            ->oldest('id')
            ->get();

        return new self($wishes, self::fulfillments($wishes));
    }

    /**
     * Datum, an dem das gewünschte Gericht (ab der Wunsch-Woche) im Plan steht, sonst null.
     */
    public function fulfilledOn(MealSuggestion $wish): ?CarbonImmutable
    {
        return $this->fulfilledOn[$wish->id] ?? null;
    }

    /**
     * Noch nicht eingeplante Wünsche.
     *
     * @return Collection<int, MealSuggestion>
     */
    public function open(): Collection
    {
        return $this->wishes->filter(fn (MealSuggestion $wish) => $this->fulfilledOn($wish) === null)->values();
    }

    /**
     * Wünsche, die ab dem angegebenen Tag eingeplant sind (z. B. „Eingeplant 🎉“ ab heute).
     *
     * @return Collection<int, MealSuggestion>
     */
    public function fulfilledSince(CarbonImmutable $since): Collection
    {
        return $this->wishes
            ->filter(fn (MealSuggestion $wish) => $this->fulfilledOn($wish)?->greaterThanOrEqualTo($since->startOfDay()))
            ->values();
    }

    /**
     * Wünsche, die für eine Woche relevant sind: ab dieser Woche (oder früher) gewünscht und
     * entweder noch offen oder genau in dieser Woche eingeplant.
     *
     * @return Collection<int, MealSuggestion>
     */
    public function relevantFor(Week $week): Collection
    {
        return $this->wishes
            ->filter(fn (MealSuggestion $wish) => $wish->week_start->lessThanOrEqualTo($week->start))
            ->filter(function (MealSuggestion $wish) use ($week) {
                $date = $this->fulfilledOn($wish);

                return $date === null || $date->betweenIncluded($week->start, $week->end());
            })
            ->values();
    }

    /**
     * Wünsche nach Gericht zusammengefasst, beliebteste zuerst.
     *
     * @param  Collection<int, MealSuggestion>  $wishes
     * @return Collection<int, array{key: string, title: string, recipe: Recipe|null, choice: string, suggestions: Collection<int, MealSuggestion>, plannedOn: CarbonImmutable|null}>
     */
    public function group(Collection $wishes): Collection
    {
        return $wishes
            ->groupBy(fn (MealSuggestion $wish) => $wish->dishKey())
            ->map(function (Collection $group, string $key) {
                $first = $group->first();

                return [
                    'key' => $key,
                    'title' => $first->recipe?->title ?? $first->title,
                    'recipe' => $first->recipe,
                    // Wert für die Auswahl im Planungsformular
                    'choice' => $first->recipe_id ? "r{$first->recipe_id}" : "s{$first->id}",
                    'suggestions' => $group->values(),
                    'plannedOn' => $group->map(fn (MealSuggestion $wish) => $this->fulfilledOn($wish))->filter()->min(),
                ];
            })
            ->sortByDesc(fn (array $group) => $group['suggestions']->count())
            ->values();
    }

    /**
     * Hat die Person dieses Gericht schon offen auf der Wunschliste?
     */
    public static function hasOpenWish(User $user, MealSuggestion $candidate): bool
    {
        $own = $user->mealSuggestions()->get()
            ->filter(fn (MealSuggestion $wish) => $wish->dishKey() === $candidate->dishKey());

        if ($own->isEmpty()) {
            return false;
        }

        $fulfillments = self::fulfillments($own);

        return $own->contains(fn (MealSuggestion $wish) => ($fulfillments[$wish->id] ?? null) === null);
    }

    public static function isFulfilled(MealSuggestion $wish): bool
    {
        return (self::fulfillments(collect([$wish]))[$wish->id] ?? null) !== null;
    }

    /**
     * Erstes passendes Plan-Datum für jeden Wunsch: ab der Wunsch-Woche, aber frühestens am Tag,
     * an dem gewünscht wurde. Wer sich am Sonntag ein Gericht vom Freitag wünscht, will es noch einmal.
     *
     * @param  Collection<int, MealSuggestion>  $wishes
     * @return array<int, CarbonImmutable|null>
     */
    private static function fulfillments(Collection $wishes): array
    {
        if ($wishes->isEmpty()) {
            return [];
        }

        $earliest = $wishes->min(fn (MealSuggestion $wish) => $wish->week_start->toDateString());

        // Plan-Daten je Gericht, aufsteigend sortiert
        $datesByDish = MealPlanEntry::query()
            ->whereDate('date', '>=', $earliest)
            ->orderBy('date')
            ->get(['date', 'recipe_id', 'title'])
            ->groupBy(fn (MealPlanEntry $entry) => $entry->dishKey())
            ->map(fn (Collection $entries) => $entries->pluck('date'));

        $result = [];

        foreach ($wishes as $wish) {
            $from = $wish->week_start->max($wish->created_at->toImmutable()->startOfDay());

            $result[$wish->id] = $datesByDish->get($wish->dishKey())
                ?->first(fn (CarbonImmutable $date) => $date->greaterThanOrEqualTo($from));
        }

        return $result;
    }
}
