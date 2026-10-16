<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Übersicht für das Familienoberhaupt: Wer mag welche Rezepte?
 */
class FamilyFavoritesController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('view-family-favorites');

        // Der Admin-Account ist kein Familienmitglied und bleibt außen vor.
        $familyOnly = fn ($query) => $query->whereNot('role', Role::Admin);

        $members = User::query()
            ->where($familyOnly)
            ->with(['favoriteRecipes' => fn ($query) => $query->orderBy('title')])
            ->orderBy('name')
            ->get();

        $popular = Recipe::query()
            ->with(['favoritedBy' => fn ($query) => $familyOnly($query)->orderBy('name')])
            ->withCount(['favoritedBy' => $familyOnly])
            ->whereHas('favoritedBy', $familyOnly)
            ->orderByDesc('favorited_by_count')
            ->orderBy('title')
            ->limit(5)
            ->get();

        return view('family.favorites', [
            'members' => $members,
            'popular' => $popular,
            'mostWished' => $this->mostWished(),
        ]);
    }

    /**
     * Die fünf am häufigsten gewünschten Gerichte – über alle Wünsche, auch erfüllte.
     *
     * @return Collection<int, array{title: string, recipe: ?Recipe, count: int, wishers: Collection<int, User>}>
     */
    private function mostWished(): Collection
    {
        return MealSuggestion::query()
            ->whereHas('user', fn ($query) => $query->whereNot('role', Role::Admin))
            ->with(['recipe:id,title,slug,emoji,color', 'user:id,name'])
            ->get()
            ->groupBy(fn (MealSuggestion $wish) => $wish->dishKey())
            ->map(fn (Collection $group) => [
                'title' => $group->first()->recipe?->title ?? $group->first()->title,
                'recipe' => $group->first()->recipe,
                'count' => $group->count(),
                'wishers' => $group->pluck('user')->unique('id')->sortBy('name')->values(),
            ])
            ->sortBy([['count', 'desc'], ['title', 'asc']])
            ->take(5)
            ->values();
    }
}
