<?php

namespace App\Http\Controllers;

use App\Actions\SaveRecipe;
use App\Enums\Difficulty;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function index(Request $request): View
    {
        $recipes = Recipe::query()
            ->with('tags')
            ->search($request->string('q')->trim()->value())
            ->withTag($request->string('tag')->value())
            ->when($request->boolean('favoriten'), fn ($query) => $query->favorites())
            ->orderByDesc('is_favorite')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $tags = Tag::query()
            ->whereHas('recipes')
            ->withCount('recipes')
            ->orderByDesc('recipes_count')
            ->orderBy('name')
            ->get();

        return view('recipes.index', [
            'recipes' => $recipes,
            'tags' => $tags,
        ]);
    }

    public function create(): View
    {
        return view('recipes.create', [
            'recipe' => new Recipe(['servings' => 2]),
            'difficulties' => Difficulty::cases(),
        ]);
    }

    public function store(StoreRecipeRequest $request, SaveRecipe $saveRecipe): RedirectResponse
    {
        $recipe = $saveRecipe->handle(new Recipe, $request->validated(), $request->file('image'));

        return to_route('recipes.show', $recipe)->with('status', 'Rezept wurde angelegt.');
    }

    public function show(Recipe $recipe): View
    {
        $recipe->load(['ingredients', 'steps', 'tags']);

        return view('recipes.show', ['recipe' => $recipe]);
    }

    public function edit(Recipe $recipe): View
    {
        $recipe->load(['ingredients', 'steps', 'tags']);

        return view('recipes.edit', [
            'recipe' => $recipe,
            'difficulties' => Difficulty::cases(),
        ]);
    }

    public function update(UpdateRecipeRequest $request, Recipe $recipe, SaveRecipe $saveRecipe): RedirectResponse
    {
        $saveRecipe->handle($recipe, $request->validated(), $request->file('image'));

        return to_route('recipes.show', $recipe)->with('status', 'Änderungen wurden gespeichert.');
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $recipe->delete();

        if ($recipe->image_path) {
            Storage::disk('public')->delete($recipe->image_path);
        }

        return to_route('recipes.index')->with('status', "„{$recipe->title}“ wurde gelöscht.");
    }
}
