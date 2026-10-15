<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWishRequest;
use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Support\Week;
use App\Support\WishList;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Die Wunschliste: Gerichte wünschen und Wünsche zurückziehen.
 * Wünsche bleiben offen, bis sie im Essensplan stehen.
 */
class WishController extends Controller
{
    public function index(Request $request): View
    {
        $wishList = WishList::upTo();
        $user = $request->user();
        $favoriteIds = $user->favoriteRecipes()->pluck('recipes.id');

        return view('wishes.index', [
            'wishList' => $wishList,
            'openGroups' => $wishList->group($wishList->open()),
            'plannedGroups' => $wishList->group($wishList->fulfilledSince(CarbonImmutable::today()->subDays(7))),
            'recipes' => Recipe::query()->orderBy('title')->get(['id', 'title', 'emoji'])
                ->map(fn (Recipe $recipe) => [
                    'id' => $recipe->id,
                    'title' => $recipe->title,
                    'emoji' => $recipe->emoji ?: '🍽️',
                    'favorite' => $favoriteIds->contains($recipe->id),
                ]),
            'weeks' => [Week::current(), Week::current()->next()],
            'selectedWeek' => $request->query('woche') === Week::current()->iso() ? Week::current() : Week::current()->next(),
        ]);
    }

    public function store(StoreWishRequest $request): RedirectResponse
    {
        $recipe = $request->filled('recipe_id') ? Recipe::findOrFail($request->validated('recipe_id')) : null;

        $request->user()->mealSuggestions()->create([
            'week_start' => $request->week()->start,
            'recipe_id' => $recipe?->id,
            'title' => $recipe?->title ?? $request->validated('title'),
            'preferred_day' => $request->validated('preferred_day'),
            'note' => $request->validated('note'),
        ]);

        return to_route('wishes.index')->with('status', 'Steht jetzt auf der Wunschliste!');
    }

    public function destroy(MealSuggestion $suggestion): RedirectResponse
    {
        Gate::authorize('delete', $suggestion);

        $suggestion->delete();

        return back()->with('status', 'Wunsch wurde zurückgezogen.');
    }
}
