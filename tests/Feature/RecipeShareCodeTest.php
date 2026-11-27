<?php

namespace Tests\Feature;

use App\Enums\Difficulty;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use App\Support\RecipeShareCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RecipeShareCodeTest extends TestCase
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
            'summary' => 'Mit Röstzwiebeln – wie früher',
            'emoji' => '🧀',
            'color' => '#d4a017',
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
            ['position' => 2, 'amount' => null, 'unit' => null, 'name' => 'Salz', 'is_optional' => false],
            ['position' => 3, 'amount' => '1', 'unit' => 'Bund', 'name' => 'Schnittlauch', 'is_optional' => true],
        ]);
        $recipe->steps()->createMany([
            ['position' => 1, 'body' => 'Spätzle kochen.', 'parallel' => false],
            ['position' => 2, 'body' => 'Zwiebeln rösten.', 'parallel' => true],
            ['position' => 3, 'body' => 'Alles mit Käse schichten.', 'parallel' => false],
        ]);
        $recipe->tags()->sync(Tag::idsFromNames(['Vegetarisch', 'Klassiker']));

        return $recipe->fresh();
    }

    public function test_code_round_trip_keeps_the_recipe_but_not_notes(): void
    {
        $data = RecipeShareCode::decode(RecipeShareCode::encode($this->sampleRecipe()));

        $this->assertSame('Omas Käsespätzle', $data['title']);
        $this->assertSame('Mit Röstzwiebeln – wie früher', $data['summary']);
        $this->assertSame('🧀', $data['emoji']);
        $this->assertSame('#d4a017', $data['color']);
        $this->assertSame(4, $data['servings']);
        $this->assertSame(20, $data['prep_minutes']);
        $this->assertSame(15, $data['cook_minutes']);
        $this->assertSame(Difficulty::Easy->value, $data['difficulty']);
        $this->assertSame(650, $data['calories']);
        $this->assertSame(28, $data['protein']);
        $this->assertSame('Oma Hilde', $data['source']);
        $this->assertEqualsCanonicalizing(['Vegetarisch', 'Klassiker'], $data['tags']);
        $this->assertSame(['Mit Bergkäse wird es würziger'], $data['tips']);
        $this->assertSame([
            ['amount' => '500', 'unit' => 'g', 'name' => 'Spätzle', 'is_optional' => false],
            ['amount' => '', 'unit' => '', 'name' => 'Salz', 'is_optional' => false],
            ['amount' => '1', 'unit' => 'Bund', 'name' => 'Schnittlauch', 'is_optional' => true],
        ], $data['ingredients']);
        $this->assertSame([
            ['body' => 'Spätzle kochen.', 'parallel' => false],
            ['body' => 'Zwiebeln rösten.', 'parallel' => true],
            ['body' => 'Alles mit Käse schichten.', 'parallel' => false],
        ], $data['steps']);
        $this->assertArrayNotHasKey('notes', $data);
    }

    public function test_code_is_compact_and_survives_messenger_line_breaks(): void
    {
        $code = RecipeShareCode::encode($this->sampleRecipe());

        $this->assertStringStartsWith('RZB1:', $code);
        $this->assertMatchesRegularExpression('/^RZB1:[A-Za-z0-9_-]+\.$/', $code);
        $this->assertLessThan(600, strlen($code));

        $wrapped = "Schau mal:\n".chunk_split($code, 40, "\n ").'Liebe Grüße';
        $this->assertSame('Omas Käsespätzle', RecipeShareCode::decode($wrapped)['title']);
    }

    public function test_broken_codes_are_rejected_with_a_message(): void
    {
        $code = RecipeShareCode::encode($this->sampleRecipe());

        foreach (['Hallo', 'RZB1:', 'RZB1:.', 'RZB1:!!!.', substr($code, 0, (int) (strlen($code) / 2)), substr($code, 0, (int) (strlen($code) / 2)).'.'] as $input) {
            try {
                RecipeShareCode::decode($input);
                $this->fail("Code wurde akzeptiert: {$input}");
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_shared_message_is_readable_and_imports_into_the_form(): void
    {
        $recipe = $this->sampleRecipe();
        $message = RecipeShareCode::message($recipe);

        $this->assertStringContainsString('🧀 Omas Käsespätzle', $message);
        $this->assertStringContainsString('• 1 Bund Schnittlauch (optional)', $message);
        $this->assertStringContainsString('2. (gleichzeitig) Zwiebeln rösten.', $message);
        $this->assertStringNotContainsString('Papa will doppelt', $message);

        $this->actingAs(User::factory()->create())
            ->post(route('recipes.import.store'), ['url' => $message])
            ->assertRedirect(route('recipes.create'))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'gibt es schon'));

        $old = session('_old_input');
        $this->assertSame('Omas Käsespätzle', $old['title']);
        $this->assertCount(3, $old['ingredients']);

        // Das vorausgefüllte Formular abschicken legt eine Kopie an.
        $this->get(route('recipes.create'))->assertOk()->assertSee('Schnittlauch');
        $this->post(route('recipes.store'), $old)->assertRedirect();

        $copy = Recipe::with(['ingredients', 'steps', 'tags'])->whereKeyNot($recipe->id)->sole();
        $this->assertSame(['Spätzle', 'Salz', 'Schnittlauch'], $copy->ingredients->pluck('name')->all());
        $this->assertSame([false, true, false], $copy->steps->pluck('parallel')->all());
        $this->assertEqualsCanonicalizing(['Vegetarisch', 'Klassiker'], $copy->tags->pluck('name')->all());
        $this->assertNull($copy->notes);
        $this->assertNull($copy->image_hash);
    }

    public function test_invalid_code_shows_an_error_on_the_import_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('recipes.import'))
            ->post(route('recipes.import.store'), ['url' => 'RZB1:kaputt'])
            ->assertRedirect(route('recipes.import'))
            ->assertSessionHasErrors('url');
    }

    public function test_plain_text_that_is_no_link_gets_a_hint(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('recipes.import.store'), ['url' => 'Linsensuppe'])
            ->assertSessionHasErrors(['url' => 'Bitte einen Link (https://…) oder einen Rezept-Code (RZB1:…) einfügen.']);
    }

    public function test_everyone_can_share_but_children_cannot_import(): void
    {
        $recipe = $this->sampleRecipe();
        $child = User::factory()->child()->create();

        $this->actingAs($child)
            ->get(route('recipes.show', $recipe))
            ->assertOk()
            ->assertViewHas('shareText', fn (string $text) => str_contains($text, RecipeShareCode::encode($recipe)));

        $this->post(route('recipes.import.store'), ['url' => RecipeShareCode::encode($recipe)])->assertForbidden();
    }
}
