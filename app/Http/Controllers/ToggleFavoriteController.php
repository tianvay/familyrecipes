<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ToggleFavoriteController extends Controller
{
    public function __invoke(Request $request, Recipe $recipe): RedirectResponse
    {
        $changes = $request->user()->favoriteRecipes()->toggle($recipe);

        return back()->with('status', $changes['attached']
            ? 'Zu deinen Favoriten hinzugefügt.'
            : 'Aus deinen Favoriten entfernt.');
    }
}
