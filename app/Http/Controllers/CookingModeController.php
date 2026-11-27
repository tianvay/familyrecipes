<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Support\CookingSteps;
use Illuminate\View\View;

/**
 * Kochmodus: ein Schritt pro Bildschirm (gleichzeitige Schritte zusammen), gedacht fürs iPad in der Küche.
 */
class CookingModeController extends Controller
{
    public function __invoke(Recipe $recipe): View
    {
        $recipe->load(['ingredients', 'steps']);

        $steps = CookingSteps::for($recipe);

        return view('recipes.cook', [
            'recipe' => $recipe,
            'steps' => $steps,
            'pages' => array_map(array_values(...), CookingSteps::groups($steps)),
        ]);
    }
}
