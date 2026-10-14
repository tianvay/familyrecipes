<?php

namespace App\Console\Commands;

use App\Actions\SaveRecipe;
use App\Enums\Difficulty;
use App\Http\Requests\StoreRecipeRequest;
use App\Models\Recipe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportRecipes extends Command
{
    protected $signature = 'recipes:import {path=database/data/recipes.json : Pfad zur JSON-Datei}';

    protected $description = 'Importiert Rezepte aus der alten recipes.json (vorhandene Titel werden übersprungen)';

    public function handle(SaveRecipe $saveRecipe): int
    {
        $path = $this->argument('path');
        $path = is_file($path) ? $path : base_path($path);

        if (! is_file($path)) {
            $this->components->error("Datei nicht gefunden: {$path}");

            return self::FAILURE;
        }

        $recipes = json_decode(file_get_contents($path), true)['recipes'] ?? null;

        if (! is_array($recipes)) {
            $this->components->error('Die Datei enthält keine gültige "recipes"-Liste.');

            return self::FAILURE;
        }

        $rules = (new StoreRecipeRequest)->rules();
        $imported = $skipped = $failed = 0;

        foreach ($recipes as $raw) {
            $title = trim($raw['title'] ?? '');

            if (Recipe::where('title', $title)->exists()) {
                $this->components->twoColumnDetail($title, '<fg=gray>übersprungen (existiert)</>');
                $skipped++;

                continue;
            }

            $validator = Validator::make($this->map($raw), $rules);

            if ($validator->fails()) {
                $this->components->twoColumnDetail($title ?: '(ohne Titel)', '<fg=red>Fehler</>');
                $this->components->bulletList($validator->errors()->all());
                $failed++;

                continue;
            }

            $saveRecipe->handle(new Recipe, $validator->validated());
            $this->components->twoColumnDetail($title, '<fg=green>importiert</>');
            $imported++;
        }

        $this->newLine();
        $this->components->info("{$imported} importiert, {$skipped} übersprungen, {$failed} fehlerhaft.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Übersetzt ein Rezept aus dem alten JSON-Format in das Format von SaveRecipe.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function map(array $raw): array
    {
        $nutrition = $raw['nutrition'] ?? [];
        $variants = $nutrition['variants'] ?? [];

        return [
            'title' => trim($raw['title'] ?? ''),
            'summary' => $this->blankToNull($raw['summary'] ?? null),
            'description' => $this->blankToNull($raw['description'] ?? null),
            'emoji' => $this->blankToNull($raw['emoji'] ?? null),
            'color' => $this->blankToNull($raw['color'] ?? null),
            'servings' => $this->toInt($raw['servings'] ?? null),
            'prep_minutes' => $this->toInt($raw['prepTime'] ?? null),
            'cook_minutes' => $this->toInt($raw['cookTime'] ?? null),
            'difficulty' => $this->difficulty($raw['difficulty'] ?? null)?->value,
            // Fehlen Gesamtwerte, gilt die erste Nährwert-Variante (z. B. „Vegetarisch“).
            'calories' => $this->toInt($nutrition['calories'] ?? null) ?? $this->toInt($variants[0]['calories'] ?? null),
            'protein' => $this->toInt($nutrition['protein'] ?? null) ?? $this->toInt($variants[0]['protein'] ?? null),
            'source' => $this->blankToNull($raw['source'] ?? null),
            'notes' => $this->blankToNull($raw['notes'] ?? null),
            'is_favorite' => (bool) ($raw['favorite'] ?? false),
            'tags' => array_values(array_filter($raw['tags'] ?? [], 'filled')),
            'ingredients' => array_map($this->ingredient(...), $raw['ingredients'] ?? []),
            'steps' => array_values(array_filter($raw['instructions'] ?? [], 'filled')),
            'tips' => array_values(array_filter([
                ...($raw['tips'] ?? []),
                ...($raw['variations'] ?? []),
                ...array_map($this->variantTip(...), $variants),
            ], 'filled')),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function ingredient(array $raw): array
    {
        $name = trim($raw['name'] ?? '');
        $optional = (bool) ($raw['optional'] ?? false);

        // Alte Einträge markieren Optionales nur im Namen: „Zitrone (optional)“
        if (preg_match('/\s*\(optional\)\s*$/i', $name)) {
            $name = preg_replace('/\s*\(optional\)\s*$/i', '', $name);
            $optional = true;
        }

        $amount = $this->formatAmount($raw['amount'] ?? null);
        $amountMax = $this->formatAmount($raw['amountMax'] ?? null);

        return [
            'amount' => $amount !== null && $amountMax !== null ? "{$amount}–{$amountMax}" : $amount,
            'unit' => $this->blankToNull($raw['unit'] ?? null),
            'name' => $name,
            'is_optional' => $optional,
        ];
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    private function variantTip(array $variant): ?string
    {
        $values = array_filter([
            $this->blankToNull($variant['calories'] ?? null),
            filled($variant['protein'] ?? null) ? "{$variant['protein']} Protein" : null,
        ]);

        return filled($variant['name'] ?? null) && $values
            ? "Nährwerte {$variant['name']}: ".implode(', ', $values)
            : null;
    }

    private function formatAmount(mixed $value): ?string
    {
        $value = $this->blankToNull(is_scalar($value) ? (string) $value : null);

        if ($value === null) {
            return null;
        }

        // Deutsche Schreibweise: „0,5“ und Bereiche mit Halbgeviertstrich „1–2“
        return preg_replace('/(?<=\d)\s*-\s*(?=\d)/', '–', str_replace('.', ',', $value));
    }

    /**
     * Liest die erste bzw. bei Bereichen („10-12 Minuten“) die größte Zahl.
     */
    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        preg_match_all('/\d+/', (string) $value, $matches);

        return $matches[0] ? max(array_map('intval', $matches[0])) : null;
    }

    private function difficulty(?string $label): ?Difficulty
    {
        return collect(Difficulty::cases())
            ->first(fn (Difficulty $case) => Str::lower($case->label()) === Str::lower(trim((string) $label)));
    }

    private function blankToNull(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }
}
