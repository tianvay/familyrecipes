<?php

namespace App\Http\Controllers;

use App\Actions\ImportRecipeFromUrl;
use App\Models\Recipe;
use App\Support\RecipeShareCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Rezept per Link oder Rezept-Code importieren: Daten holen und das Rezeptformular damit vorausfüllen.
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

        $request->validate(
            ['url' => ['required', 'string', 'max:20000']],
            attributes: ['url' => 'Link oder Rezept-Code'],
        );
        $input = trim($request->string('url')->value());

        try {
            $prefill = RecipeShareCode::containsCode($input)
                ? RecipeShareCode::decode($input)
                : $import->handle($this->validatedUrl($input));
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['url' => $e->getMessage()]);
        }

        $status = 'Rezept übernommen – bitte kurz prüfen und speichern.';

        if (Recipe::where('title', $prefill['title'])->exists()) {
            $status .= " Hinweis: „{$prefill['title']}“ gibt es schon im Rezeptbuch.";
        }

        return to_route('recipes.create')
            ->withInput($prefill)
            ->with('status', $status);
    }

    private function validatedUrl(string $input): string
    {
        validator(['url' => $input], ['url' => ['url:http,https', 'max:2000']], [
            'url.url' => 'Bitte einen Link (https://…) oder einen Rezept-Code (RZB1:…) einfügen.',
        ])->validate();

        return $input;
    }
}
