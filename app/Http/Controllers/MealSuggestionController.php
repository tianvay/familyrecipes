<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMealSuggestionRequest;
use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Support\Week;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Gerichte für eine Woche vorschlagen und Vorschläge zurückziehen.
 */
class MealSuggestionController extends Controller
{
    public function store(StoreMealSuggestionRequest $request, Week $week): RedirectResponse
    {
        $recipe = $request->filled('recipe_id') ? Recipe::findOrFail($request->validated('recipe_id')) : null;

        $request->user()->mealSuggestions()->create([
            'week_start' => $week->start,
            'recipe_id' => $recipe?->id,
            'title' => $recipe?->title ?? $request->validated('title'),
            'preferred_day' => $request->validated('preferred_day'),
            'note' => $request->validated('note'),
        ]);

        return redirect()
            ->to(route('meal-plan.show', $week).'#vorschlaege')
            ->with('status', 'Danke für deinen Vorschlag!');
    }

    public function destroy(MealSuggestion $suggestion): RedirectResponse
    {
        Gate::authorize('delete', $suggestion);

        $suggestion->delete();

        return back()->with('status', 'Vorschlag wurde zurückgezogen.');
    }
}
