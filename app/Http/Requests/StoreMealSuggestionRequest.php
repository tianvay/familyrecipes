<?php

namespace App\Http\Requests;

use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Support\Week;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ein Gericht für die Woche vorschlagen: ein Rezept aus der Sammlung oder ein Freitext.
 */
class StoreMealSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [MealSuggestion::class, $this->week()]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')) ?: null,
            'recipe_id' => $this->input('recipe_id') ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipe_id' => ['nullable', 'required_without:title', 'prohibits:title', 'integer', Rule::exists(Recipe::class, 'id')],
            'title' => ['nullable', 'required_without:recipe_id', 'string', 'max:100'],
            'preferred_day' => ['nullable', 'integer', 'between:1,7'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipe_id.required_without' => 'Wähle ein Rezept oder gib ein Gericht als Text ein.',
            'title.required_without' => 'Wähle ein Rezept oder gib ein Gericht als Text ein.',
            'recipe_id.prohibits' => 'Bitte entweder ein Rezept wählen oder einen Text eingeben, nicht beides.',
        ];
    }

    public function attributes(): array
    {
        return [
            'recipe_id' => 'Rezept',
            'title' => 'Gericht',
            'preferred_day' => 'Wunschtag',
            'note' => 'Notiz',
        ];
    }

    /**
     * Dasselbe Gericht kann jede Person pro Woche nur einmal vorschlagen.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $candidate = new MealSuggestion($this->only('recipe_id', 'title'));
                $alreadySuggested = $this->user()->mealSuggestions()
                    ->forWeek($this->week())
                    ->get()
                    ->contains(fn (MealSuggestion $s) => $s->dishKey() === $candidate->dishKey());

                if ($alreadySuggested) {
                    $validator->errors()->add($this->filled('recipe_id') ? 'recipe_id' : 'title', 'Das hast du für diese Woche schon vorgeschlagen.');
                }
            },
        ];
    }

    public function week(): Week
    {
        return $this->route('week');
    }
}
