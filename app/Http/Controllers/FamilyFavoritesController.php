<?php

namespace App\Http\Controllers;

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

        $members = User::query()
            ->with(['favoriteRecipes' => fn ($query) => $query->orderBy('title')])
            ->orderBy('name')
            ->get();

        $popular = Recipe::query()
            ->with(['favoritedBy' => fn ($query) => $query->orderBy('name')])
            ->withCount('favoritedBy')
            ->has('favoritedBy')
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
