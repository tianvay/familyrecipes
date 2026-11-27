<?php

namespace Tests\Feature;

use App\Enums\Difficulty;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use App\Support\RecipeShareText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RecipeShareTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function sampleRecipe(): Recipe
    {
        $recipe = Recipe::factory()->create([
            'title' => 'Omas Käsespätzle',
            'summary' => 'Mit Röstzwiebeln',
            'description' => "Mit Röstzwiebeln – wie früher.\nAm besten frisch.",
            'emoji' => '🧀',
            'servings' => 4,
            'prep_minutes' => 20,
            'cook_minutes' => 15,
            'difficulty' => Difficulty::Easy,
            'calories' => 650,
            'protein' => 28,
            'source' => 'Oma Hilde',
            'tips' => ['Mit Bergkäse wird es würziger'],
            'notes' => 'Nur für uns: Papa will doppelt Zwiebeln',
        ]);
        $recipe->ingredients()->createMany([
            ['position' => 1, 'amount' => '500', 'unit' => 'g', 'name' => 'Spätzle', 'is_optional' => false],
            ['position' => 2, 'amount' => '1,5', 'unit' => 'EL', 'name' => 'Butter', 'is_optional' => false],
            ['position' => 3, 'amount' => '2', 'unit' => null, 'name' => 'Zwiebeln', 'is_optional' => false],
            ['position' => 4, 'amount' => null, 'unit' => null, 'name' => 'Salz und Pfeffer', 'is_optional' => false],
            ['position' => 5, 'amount' => '1', 'unit' => 'Bund', 'name' => 'Schnittlauch', 'is_optional' => true],
        ]);
        $recipe->steps()->createMany([
            ['position' => 1, 'body' => 'Spätzle kochen.', 'parallel' => false],
            ['position' => 2, 'body' => 'Zwiebeln rösten.', 'parallel' => true],
            ['position' => 3, 'body' => 'Alles mit Käse schichten.', 'parallel' => false],
        ]);
        $recipe->tags()->sync(Tag::idsFromNames(['Vegetarisch', 'Klassiker']));

        return $recipe->fresh();
    }

    public function test_shared_text_has_the_uniform_format(): void
    {
        $this->assertSame(<<<'TEXT'
            🧀 Omas Käsespätzle
            Mit Röstzwiebeln – wie früher. Am besten frisch.
            4 Portionen · 20 Min. Vorbereitung · 15 Min. Kochen/Backen · Einfach · 650 kcal · 28 g Protein
            Tags: Klassiker, Vegetarisch
            Quelle: Oma Hilde

            Zutaten:
            • 500 g Spätzle
            • 1,5 EL Butter
            • 2 Zwiebeln
            • Salz und Pfeffer
            • 1 Bund Schnittlauch (optional)

            Zubereitung:
            1. Spätzle kochen.
            2. (gleichzeitig) Zwiebeln rösten.
            3. Alles mit Käse schichten.

            Tipps:
            • Mit Bergkäse wird es würziger
            TEXT, RecipeShareText::make($this->sampleRecipe()));
    }

    public function test_round_trip_keeps_the_recipe_but_not_notes(): void
    {
        $data = RecipeShareText::parse(RecipeShareText::make($this->sampleRecipe()));

        $this->assertSame('Omas Käsespätzle', $data['title']);
        $this->assertSame('🧀', $data['emoji']);
        $this->assertSame('Mit Röstzwiebeln – wie früher. Am besten frisch.', $data['description']);
        $this->assertSame(4, $data['servings']);
        $this->assertSame(20, $data['prep_minutes']);
        $this->assertSame(15, $data['cook_minutes']);
        $this->assertSame(Difficulty::Easy->value, $data['difficulty']);
        $this->assertSame(650, $data['calories']);
        $this->assertSame(28, $data['protein']);
        $this->assertSame('Oma Hilde', $data['source']);
        $this->assertSame(['Klassiker', 'Vegetarisch'], $data['tags']);
        $this->assertSame(['Mit Bergkäse wird es würziger'], $data['tips']);
        $this->assertSame([
            ['amount' => '500', 'unit' => 'g', 'name' => 'Spätzle', 'is_optional' => false],
            ['amount' => '1,5', 'unit' => 'EL', 'name' => 'Butter', 'is_optional' => false],
            ['amount' => '2', 'unit' => '', 'name' => 'Zwiebeln', 'is_optional' => false],
            ['amount' => '', 'unit' => '', 'name' => 'Salz und Pfeffer', 'is_optional' => false],
            ['amount' => '1', 'unit' => 'Bund', 'name' => 'Schnittlauch', 'is_optional' => true],
        ], $data['ingredients']);
        $this->assertSame([
            ['body' => 'Spätzle kochen.', 'parallel' => false],
            ['body' => 'Zwiebeln rösten.', 'parallel' => true],
            ['body' => 'Alles mit Käse schichten.', 'parallel' => false],
        ], $data['steps']);
        $this->assertArrayNotHasKey('notes', $data);
    }

    public function test_greetings_around_the_recipe_and_windows_line_breaks_are_ignored(): void
    {
        $message = "Hallo ihr!\nProbiert das mal:\n\n"
            .RecipeShareText::make($this->sampleRecipe())
            ."\n\nLiebe Grüße\nOma";
        $data = RecipeShareText::parse(str_replace("\n", "\r\n", $message));

        $this->assertSame('Omas Käsespätzle', $data['title']);
        $this->assertCount(5, $data['ingredients']);
        $this->assertCount(3, $data['steps']);
        $this->assertSame(['Mit Bergkäse wird es würziger'], $data['tips']);
    }

    public function test_minimal_hand_written_recipe_can_be_imported(): void
    {
        $data = RecipeShareText::parse("Pfannkuchen\n\nZutaten:\n- 250 g Mehl\n- 3 Eier\n\nZubereitung:\nAlles verrühren.\nAusbacken.");

        $this->assertSame('Pfannkuchen', $data['title']);
        $this->assertNull($data['emoji']);
        $this->assertNull($data['servings'] ?? null);
        $this->assertSame(['250', '3'], array_column($data['ingredients'], 'amount'));
        $this->assertSame(['Alles verrühren.', 'Ausbacken.'], array_column($data['steps'], 'body'));
    }

    public function test_incomplete_text_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecipeShareText::parse("Zutaten:\n• Mehl\n\nZubereitung:\n");
    }

    public function test_shared_text_imports_into_the_form_and_saves_a_copy(): void
    {
        $recipe = $this->sampleRecipe();

        $this->actingAs(User::factory()->create())
            ->post(route('recipes.import.store'), ['url' => RecipeShareText::make($recipe)])
            ->assertRedirect(route('recipes.create'))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'gibt es schon'));

        $old = session('_old_input');
        $this->get(route('recipes.create'))->assertOk()->assertSee('Schnittlauch');
        $this->post(route('recipes.store'), $old)->assertRedirect();

        $copy = Recipe::with(['ingredients', 'steps', 'tags'])->whereKeyNot($recipe->id)->sole();
        $this->assertSame($recipe->ingredients->map->label()->all(), $copy->ingredients->map->label()->all());
        $this->assertSame([false, true, false], $copy->steps->pluck('parallel')->all());
        $this->assertEqualsCanonicalizing(['Vegetarisch', 'Klassiker'], $copy->tags->pluck('name')->all());
        $this->assertSame(RecipeShareText::make($recipe), RecipeShareText::make($copy));
        $this->assertNull($copy->notes);
    }

    public function test_plain_text_that_is_no_link_gets_a_hint(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('recipes.import.store'), ['url' => 'Linsensuppe'])
            ->assertSessionHasErrors('url');

        $this->assertStringContainsString('Zutaten:', session('errors')->first('url'));
    }

    public function test_everyone_can_share_but_children_cannot_import(): void
    {
        $recipe = $this->sampleRecipe();

        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.show', $recipe))
            ->assertOk()
            ->assertViewHas('shareText', RecipeShareText::make($recipe));

        $this->post(route('recipes.import.store'), ['url' => RecipeShareText::make($recipe)])->assertForbidden();
    }
}
