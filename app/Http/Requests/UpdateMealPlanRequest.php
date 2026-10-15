<?php

namespace App\Http\Requests;

use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Support\Week;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * Den Wochenplan festlegen. Pro Tag (1 = Montag … 7 = Sonntag) eine Auswahl:
 *  - ""      kein Gericht
 *  - "r{id}" Rezept aus der Sammlung
 *  - "s{id}" Freitext-Wunsch übernehmen (ab dieser Woche oder früher gewünscht)
 *  - "t"     eigener Freitext aus days[n][title]
 */
class UpdateMealPlanRequest extends FormRequest
{
    public const MAX_PER_DISH = 2;

    /** @var array<int, array{recipe_id: int|null, title: string}|null>|null */
    private ?array $entries = null;

    public function authorize(): bool
    {
        return $this->user()->can('plan-meals', $this->week());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array:1,2,3,4,5,6,7'],
            'days.*.choice' => ['nullable', 'string', 'regex:/^(r\d+|s\d+|t)$/'],
            'days.*.title' => ['nullable', 'required_if:days.*.choice,t', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.*.title.required_if' => 'Bitte gib das Gericht als Text ein.',
            'days.*.choice.regex' => 'Ungültige Auswahl.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->entries = $this->resolveEntries($validator);

                $this->checkMaxPerDish($validator, $this->entries);
            },
        ];
    }

    /**
     * Aufgelöste Einträge pro Tag; null bedeutet „kein Gericht“.
     *
     * @return array<int, array{recipe_id: int|null, title: string}|null>
     */
    public function entries(): array
    {
        return $this->entries ?? [];
    }

    public function week(): Week
    {
        return $this->route('week');
    }

    /**
     * @return array<int, array{recipe_id: int|null, title: string}|null>
     */
    private function resolveEntries(Validator $validator): array
    {
        $days = collect($this->input('days', []));
        $choices = $days->pluck('choice')->filter();

        $recipes = Recipe::query()
            ->whereKey($this->idsWithPrefix($choices, 'r'))
            ->pluck('title', 'id');

        // Freitext-Wünsche bleiben über Wochen offen – alle bis zu dieser Woche zählen
        $suggestions = MealSuggestion::query()
            ->whereDate('week_start', '<=', $this->week()->start->toDateString())
            ->whereNull('recipe_id')
            ->whereKey($this->idsWithPrefix($choices, 's'))
            ->pluck('title', 'id');

        $entries = [];

        foreach (range(1, 7) as $day) {
            $choice = $days[$day]['choice'] ?? null;
            $id = (int) substr((string) $choice, 1);

            $entries[$day] = match (true) {
                blank($choice) => null,
                $choice === 't' => ['recipe_id' => null, 'title' => trim($days[$day]['title'])],
                $choice[0] === 'r' && $recipes->has($id) => ['recipe_id' => $id, 'title' => $recipes[$id]],
                $choice[0] === 's' && $suggestions->has($id) => ['recipe_id' => null, 'title' => $suggestions[$id]],
                default => false,
            };

            if ($entries[$day] === false) {
                $validator->errors()->add("days.{$day}.choice", 'Dieses Gericht gibt es nicht (mehr).');
                $entries[$day] = null;
            }
        }

        return $entries;
    }

    /**
     * Ein Gericht darf höchstens zweimal pro Woche auf dem Plan stehen.
     *
     * @param  array<int, array{recipe_id: int|null, title: string}|null>  $entries
     */
    private function checkMaxPerDish(Validator $validator, array $entries): void
    {
        collect($entries)
            ->filter()
            ->groupBy(
                fn (array $entry) => $entry['recipe_id'] ? "r{$entry['recipe_id']}" : 't:'.mb_strtolower($entry['title']),
                preserveKeys: true,
            )
            ->filter(fn (Collection $days) => $days->count() > self::MAX_PER_DISH)
            ->each(function (Collection $days) use ($validator) {
                $title = $days->first()['title'];

                foreach ($days->keys()->slice(self::MAX_PER_DISH) as $day) {
                    $validator->errors()->add(
                        "days.{$day}.choice",
                        "„{$title}“ darf höchstens ".self::MAX_PER_DISH.'-mal pro Woche auf dem Plan stehen.'
                    );
                }
            });
    }

    /**
     * @param  Collection<int, string>  $choices
     * @return list<int>
     */
    private function idsWithPrefix(Collection $choices, string $prefix): array
    {
        return $choices
            ->filter(fn (string $choice) => str_starts_with($choice, $prefix))
            ->map(fn (string $choice) => (int) substr($choice, 1))
            ->values()
            ->all();
    }
}
