<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\View\View;

/**
 * Verlauf eines einzelnen Rezepts – für alle Familienmitglieder sichtbar.
 */
class RecipeHistoryController extends Controller
{
    public function __invoke(Recipe $recipe): View
    {
        return view('recipes.history', [
            'recipe' => $recipe,
            'revisions' => $recipe->revisions()->paginate(20),
        ]);
    }
}
