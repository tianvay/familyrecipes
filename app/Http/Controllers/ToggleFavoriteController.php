<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;

class ToggleFavoriteController extends Controller
{
    public function __invoke(Recipe $recipe): RedirectResponse
    {
        $recipe->update(['is_favorite' => ! $recipe->is_favorite]);

        return back()->with('status', $recipe->is_favorite
            ? 'Zu den Favoriten hinzugefügt.'
            : 'Aus den Favoriten entfernt.');
    }
}
