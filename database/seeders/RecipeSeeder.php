<?php

namespace Database\Seeders;

use App\Actions\SaveRecipe;
use App\Enums\Difficulty;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

/**
 * Die Familienrezepte (Stand 09.11.2026) für eine frische Installation.
 * Daten: database/seeders/data/recipes.json, Fotos in data/images/.
 */
class RecipeSeeder extends Seeder
{
    private const DATA_DIR = __DIR__.'/data';

    public function run(SaveRecipe $saveRecipe): void
    {
        $recipes = json_decode(file_get_contents(self::DATA_DIR.'/recipes.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($recipes as $data) {
            // Schon vorhandene Rezepte (gleicher Titel) nicht doppelt anlegen.
            if (Recipe::where('title', $data['title'])->exists()) {
                continue;
            }

            $image = $data['image'] ?? null;
            unset($data['image']);

            $data['difficulty'] = isset($data['difficulty']) ? Difficulty::from($data['difficulty']) : null;

            $recipe = $saveRecipe->handle(new Recipe, $data);

            if ($image && is_file($path = self::DATA_DIR.'/images/'.$image)) {
                $recipe->storeImage(file_get_contents($path), mime_content_type($path) ?: 'image/jpeg');
            }
        }
    }
}
