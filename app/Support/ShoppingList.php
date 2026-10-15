<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Einkaufsliste aus dem festgelegten Essensplan: alle Zutaten der Gerichte
 * von heute bis Sonntag. Vergangene Tage der Woche zählen nicht mit.
 */
final class ShoppingList
{
    /**
     * @param  Collection<int, MealPlanEntry>  $meals  Berücksichtigte Tage, nach Datum sortiert
     * @param  Collection<int, ShoppingListItem>  $items  Zutaten, alphabetisch
     */
    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly Collection $meals,
        public readonly Collection $items,
    ) {}

    public static function forRestOfWeek(?CarbonImmutable $today = null): self
    {
        $from = ($today ?? CarbonImmutable::today())->startOfDay();
        $to = Week::containing($from)->end()->startOfDay();

        $meals = MealPlanEntry::query()
            ->betweenDates($from, $to)
            ->with('recipe.ingredients')
            ->orderBy('date')
            ->get();

        return new self($from, $to, $meals, self::collectItems($meals));
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Freitext-Gerichte wie „Pizza bestellen“ – dafür gibt es keine Zutaten.
     *
     * @return Collection<int, MealPlanEntry>
     */
    public function mealsWithoutRecipe(): Collection
    {
        return $this->meals->whereNull('recipe_id')->values();
    }

    /**
     * Gleiche Zutaten (Name + Einheit, ohne Groß-/Kleinschreibung) werden zusammengezählt.
     * Mengen, die keine Zahl sind („eine Prise“), bleiben eigene Zeilen.
     *
     * @param  Collection<int, MealPlanEntry>  $meals
     * @return Collection<int, ShoppingListItem>
     */
    private static function collectItems(Collection $meals): Collection
    {
        $groups = [];

        foreach ($meals as $meal) {
            foreach ($meal->recipe?->ingredients ?? [] as $ingredient) {
                /** @var Ingredient $ingredient */
                $quantity = Quantity::parse($ingredient->amount);
                $rawAmount = $quantity === null && filled($ingredient->amount) ? trim($ingredient->amount) : null;

                $key = implode('|', [
                    mb_strtolower(trim($ingredient->name)),
                    mb_strtolower(trim((string) $ingredient->unit)),
                    mb_strtolower((string) $rawAmount),
                ]);

                $group = $groups[$key] ?? [
                    'name' => trim($ingredient->name),
                    'unit' => filled($ingredient->unit) ? trim($ingredient->unit) : null,
                    'quantity' => null,
                    'rawAmount' => $rawAmount,
                    'optional' => true,
                    'sources' => [],
                ];

                if ($quantity) {
                    $group['quantity'] = $group['quantity']?->add($quantity) ?? $quantity;
                }

                // Optional nur, wenn die Zutat in keinem Gericht Pflicht ist
                $group['optional'] = $group['optional'] && $ingredient->is_optional;
                $group['sources'][] = ['title' => $meal->displayTitle(), 'date' => $meal->date];

                $groups[$key] = $group;
            }
        }

        return collect($groups)
            ->map(fn (array $group) => new ShoppingListItem(
                name: $group['name'],
                unit: $group['unit'],
                quantity: $group['quantity'],
                rawAmount: $group['rawAmount'],
                optional: $group['optional'],
                sources: collect($group['sources']),
            ))
            ->sortBy(fn (ShoppingListItem $item) => Str::ascii(mb_strtolower($item->name)))
            ->values();
    }
}
