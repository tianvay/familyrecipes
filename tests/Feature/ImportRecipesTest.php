<?php

namespace Tests\Feature;

use App\Enums\Difficulty;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportRecipesTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipes_are_imported_from_legacy_json(): void
    {
        $path = $this->fixture([
            [
                'title' => 'Gefüllte Paprika',
                'summary' => '',
                'description' => 'Aus dem Airfryer.',
                'emoji' => '🫑',
                'color' => '#D94E41',
                'servings' => '2',
                'prepTime' => '10 Minuten',
                'cookTime' => '10-12 Minuten',
                'difficulty' => 'Sehr einfach',
                'tags' => ['Airfryer', 'Schnell'],
                'nutrition' => ['calories' => '', 'protein' => '', 'variants' => [
                    ['name' => 'Vegetarisch', 'calories' => '~240 kcal', 'protein' => '~18 g'],
                ]],
                'ingredients' => [
                    ['name' => 'Paprika', 'amount' => 2, 'amountMax' => 3, 'unit' => 'Stück', 'optional' => false],
                    ['name' => 'Butter', 'amount' => '0.5', 'unit' => 'EL'],
                    ['name' => 'Zitrone (optional)', 'amount' => '1-2', 'unit' => ''],
                ],
                'instructions' => ['Paprika füllen.', 'Garen.'],
                'tips' => ['Mit Feta probieren.'],
                'variations' => ['Mit Chili.'],
                'notes' => '',
                'favorite' => true,
            ],
        ]);

        $this->artisan('recipes:import', ['path' => $path])->assertSuccessful();

        $recipe = Recipe::with('ingredients', 'steps', 'tags')->sole();

        $this->assertSame('Gefüllte Paprika', $recipe->title);
        $this->assertNull($recipe->summary);
        $this->assertSame(2, $recipe->servings);
        $this->assertSame(10, $recipe->prep_minutes);
        $this->assertSame(12, $recipe->cook_minutes);
        $this->assertSame(Difficulty::VeryEasy, $recipe->difficulty);
        $this->assertSame(240, $recipe->calories);
        $this->assertSame(18, $recipe->protein);
        $this->assertSame(['Airfryer', 'Schnell'], $recipe->tags->pluck('name')->all());
        $this->assertSame(['Paprika füllen.', 'Garen.'], $recipe->steps->pluck('body')->all());
        $this->assertSame(
            ['Mit Feta probieren.', 'Mit Chili.', 'Nährwerte Vegetarisch: ~240 kcal, ~18 g Protein'],
            $recipe->tips,
        );

        $this->assertSame(
            [
                ['amount' => '2–3', 'unit' => 'Stück', 'name' => 'Paprika', 'is_optional' => false],
                ['amount' => '0,5', 'unit' => 'EL', 'name' => 'Butter', 'is_optional' => false],
                ['amount' => '1–2', 'unit' => null, 'name' => 'Zitrone', 'is_optional' => true],
            ],
            $recipe->ingredients->map->only('amount', 'unit', 'name', 'is_optional')->all(),
        );
    }

    public function test_existing_recipes_are_skipped_on_repeated_runs(): void
    {
        $path = $this->fixture([
            ['title' => 'Linsensuppe', 'ingredients' => [['name' => 'Linsen']], 'instructions' => ['Kochen.']],
        ]);

        $this->artisan('recipes:import', ['path' => $path])->assertSuccessful();
        $this->artisan('recipes:import', ['path' => $path])
            ->expectsOutputToContain('0 importiert, 1 übersprungen')
            ->assertSuccessful();

        $this->assertDatabaseCount('recipes', 1);
    }

    public function test_invalid_recipes_are_reported_and_not_saved(): void
    {
        $path = $this->fixture([
            ['title' => 'Ohne Schritte', 'ingredients' => [['name' => 'Wasser']], 'instructions' => []],
        ]);

        $this->artisan('recipes:import', ['path' => $path])->assertFailed();

        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_missing_file_fails(): void
    {
        $this->artisan('recipes:import', ['path' => 'gibt/es/nicht.json'])->assertFailed();
    }

    /**
     * @param  list<array<string, mixed>>  $recipes
     */
    private function fixture(array $recipes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'recipes').'.json';
        file_put_contents($path, json_encode(['recipes' => $recipes]));
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
