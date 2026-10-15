<?php

namespace App\Http\Controllers;

use App\Actions\ImportRecipeFromUrl;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Rezept per Link importieren: Daten holen und das Rezeptformular damit vorausfüllen.
 */
class RecipeImportController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', Recipe::class);

        return view('recipes.import');
    }

    public function store(Request $request, ImportRecipeFromUrl $import): RedirectResponse
    {
        Gate::authorize('create', Recipe::class);

        $validated = $request->validate(
            ['url' => ['required', 'url:http,https', 'max:2000']],
            attributes: ['url' => 'Link'],
        );

        try {
            $prefill = $import->handle(trim($validated['url']));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['url' => $e->getMessage()]);
        }

        return to_route('recipes.create')
            ->withInput($prefill)
            ->with('status', 'Rezept übernommen – bitte kurz prüfen und speichern.');
    }
}
