<?php

namespace Tests\Feature;

use App\Models\Recipe;
use Database\Seeders\RecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_family_recipes_are_seeded_with_photos(): void
    {
        $data = json_decode(file_get_contents(database_path('seeders/data/recipes.json')), true);

        $this->seed(RecipeSeeder::class);

        $this->assertSame(count($data), Recipe::count());
        $this->assertSame(
            collect($data)->whereNotNull('image')->count(),
            Recipe::whereNotNull('image_hash')->count(),
        );

        $first = Recipe::with(['ingredients', 'steps', 'tags'])->where('title', $data[0]['title'])->firstOrFail();
        $this->assertCount(count($data[0]['ingredients']), $first->ingredients);
        $this->assertCount(count($data[0]['steps']), $first->steps);
        $this->assertCount(count($data[0]['tags']), $first->tags);
    }

    public function test_seeding_twice_does_not_duplicate_recipes(): void
    {
        $this->seed(RecipeSeeder::class);
        $count = Recipe::count();

        $this->seed(RecipeSeeder::class);

        $this->assertSame($count, Recipe::count());
    }
}
