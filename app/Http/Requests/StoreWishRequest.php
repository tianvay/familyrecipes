<?php

namespace App\Http\Requests;

use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Support\Week;
use App\Support\WishList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Sich ein Gericht wünschen: ein Rezept aus der Sammlung oder eine eigene Idee,
 * gültig ab dieser oder ab nächster Woche – und offen, bis es im Essensplan steht.
 */
class StoreWishRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Wünschen darf jedes Familienmitglied, auch Kinder – nur der Admin-Account nicht.
        return $this->user()->can('make-wishes');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')) ?: null,
            'recipe_id' => $this->input('recipe_id') ?: null,
            'week' => $this->input('week') ?: Week::current()->next()->iso(),
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
            'week' => ['required', Rule::in([Week::current()->iso(), Week::current()->next()->iso()])],
            'preferred_day' => ['nullable', 'integer', 'between:1,7'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipe_id.required_without' => 'Wähle ein Rezept oder gib eine eigene Idee ein.',
            'title.required_without' => 'Wähle ein Rezept oder gib eine eigene Idee ein.',
            'recipe_id.prohibits' => 'Bitte entweder ein Rezept wählen oder eine eigene Idee eingeben, nicht beides.',
            'week.in' => 'Wünsche sind nur ab dieser oder ab nächster Woche möglich.',
        ];
    }

    public function attributes(): array
    {
        return [
            'recipe_id' => 'Rezept',
            'title' => 'Gericht',
            'week' => 'Woche',
            'preferred_day' => 'Wunschtag',
            'note' => 'Notiz',
        ];
    }

    /**
     * Ein Gericht kann jede Person nur einmal offen auf der Wunschliste haben.
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

                if (WishList::hasOpenWish($this->user(), new MealSuggestion($this->only('recipe_id', 'title')))) {
                    $validator->errors()->add(
                        $this->filled('recipe_id') ? 'recipe_id' : 'title',
                        'Das steht schon auf deiner Wunschliste.',
                    );
                }
            },
        ];
    }

    public function week(): Week
    {
        return Week::fromIso($this->validated('week'));
    }
}
