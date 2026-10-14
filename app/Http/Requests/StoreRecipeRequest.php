<?php

namespace App\Http\Requests;

use App\Enums\Difficulty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Noch keine Benutzerverwaltung – sobald es Auth gibt, hier eine Policy prüfen.
        return true;
    }

    /**
     * Leere Formularzeilen entfernen und Freitextfelder in Listen umwandeln,
     * bevor validiert wird.
     */
    protected function prepareForValidation(): void
    {
        $ingredients = collect($this->input('ingredients', []))
            ->filter(fn ($row) => is_array($row)
                && (filled($row['amount'] ?? null) || filled($row['unit'] ?? null) || filled($row['name'] ?? null)))
            ->map(fn (array $row) => [...$row, 'is_optional' => (bool) ($row['is_optional'] ?? false)])
            ->values()
            ->all();

        $steps = collect($this->input('steps', []))
            ->filter(fn ($step) => filled($step))
            ->values()
            ->all();

        $this->merge([
            'ingredients' => $ingredients,
            'steps' => $steps,
            'tags' => $this->splitList($this->input('tags'), ','),
            'tips' => $this->splitList($this->input('tips'), "\n"),
            'is_favorite' => $this->boolean('is_favorite'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'color' => ['nullable', 'hex_color'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['boolean'],
            'servings' => ['nullable', 'integer', 'between:1,100'],
            'prep_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'cook_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'calories' => ['nullable', 'integer', 'between:0,10000'],
            'protein' => ['nullable', 'integer', 'between:0,1000'],
            'source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_favorite' => ['boolean'],

            'tags' => ['array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'tips' => ['array', 'max:20'],
            'tips.*' => ['string', 'max:500'],

            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.amount' => ['nullable', 'string', 'max:32'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:32'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.is_optional' => ['boolean'],

            'steps' => ['required', 'array', 'min:1', 'max:100'],
            'steps.*' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Titel',
            'summary' => 'Kurzbeschreibung',
            'description' => 'Beschreibung',
            'color' => 'Farbe',
            'image' => 'Bild',
            'servings' => 'Portionen',
            'prep_minutes' => 'Vorbereitungszeit',
            'cook_minutes' => 'Koch-/Backzeit',
            'difficulty' => 'Schwierigkeit',
            'calories' => 'Kalorien',
            'protein' => 'Protein',
            'source' => 'Quelle',
            'notes' => 'Notizen',
            'tags.*' => 'Tag',
            'tips.*' => 'Tipp',
            'ingredients' => 'Zutaten',
            'ingredients.*.amount' => 'Menge',
            'ingredients.*.unit' => 'Einheit',
            'ingredients.*.name' => 'Zutat',
            'steps' => 'Zubereitungsschritte',
            'steps.*' => 'Zubereitungsschritt',
        ];
    }

    /**
     * @return list<string>
     */
    private function splitList(mixed $value, string $separator): array
    {
        $items = is_array($value) ? $value : explode($separator, (string) $value);

        return collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }
}
