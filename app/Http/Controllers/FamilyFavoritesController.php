<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Recipe;
use App\Models\User;
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
            ->limit(10)
            ->get();

        return view('family.favorites', [
            'members' => $members,
            'popular' => $popular,
        ]);
    }
}
