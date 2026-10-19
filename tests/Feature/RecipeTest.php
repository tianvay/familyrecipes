<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_index_lists_recipes(): void
    {
        Recipe::factory()->create(['title' => 'Linsensuppe']);
        Recipe::factory()->create(['title' => 'Apfelkuchen']);

        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertSeeInOrder(['Apfelkuchen', 'Linsensuppe']);
    }

    public function test_index_can_be_searched_by_title_and_ingredient(): void
    {
        Recipe::factory()->create(['title' => 'Linsensuppe']);
        $cake = Recipe::factory()->create(['title' => 'Apfelkuchen']);
        $cake->ingredients()->create(['position' => 1, 'name' => 'Zimt']);

        $this->get(route('recipes.index', ['q' => 'linsen']))
            ->assertSee('Linsensuppe')
            ->assertDontSee('Apfelkuchen');

        $this->get(route('recipes.index', ['q' => 'Zimt']))
            ->assertSee('Apfelkuchen')
            ->assertDontSee('Linsensuppe');
    }

    public function test_index_can_be_filtered_by_tag_and_favorites(): void
    {
        $tag = Tag::factory()->create(['name' => 'Vegan']);
        Recipe::factory()->hasAttached($tag)->create(['title' => 'Tofu-Bowl']);
        $this->user->favoriteRecipes()->attach(Recipe::factory()->create(['title' => 'Gulasch']));

        $this->get(route('recipes.index', ['tag' => $tag->slug]))
            ->assertSee('Tofu-Bowl')
            ->assertDontSee('Gulasch');

        $this->get(route('recipes.index', ['favoriten' => 1]))
            ->assertSee('Gulasch')
            ->assertDontSee('Tofu-Bowl');
    }

    public function test_show_displays_recipe_details(): void
    {
        $recipe = Recipe::factory()->complete()->create();

        $this->get(route('recipes.show', $recipe))
            ->assertOk()
            ->assertSee($recipe->title)
            ->assertSee($recipe->ingredients->first()->name)
            ->assertSee($recipe->steps->first()->body);
    }

    public function test_create_and_edit_forms_render(): void
    {
        $recipe = Recipe::factory()->complete()->create();

        $this->get(route('recipes.create'))->assertOk()->assertSee('Neues Rezept');
        $this->get(route('recipes.edit', $recipe))->assertOk()->assertSee($recipe->title);
    }

    public function test_recipe_can_be_created(): void
    {
        Storage::fake('public');

        $response = $this->post(route('recipes.store'), [
            ...$this->validPayload(),
            // 1×1-PNG, damit der Test ohne GD-Extension läuft
            'image' => UploadedFile::fake()->createWithContent('suppe.png', base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
            )),
        ]);

        $recipe = Recipe::firstWhere('title', 'Kürbissuppe');

        $response->assertRedirect(route('recipes.show', $recipe));
        $this->assertSame('kuerbissuppe', $recipe->slug);
        $this->assertSame(['Kürbis', 'Brühe'], $recipe->ingredients->pluck('name')->all());
        $this->assertSame(['Kürbis würfeln.', 'Alles pürieren.'], $recipe->steps->pluck('body')->all());
        $this->assertEqualsCanonicalizing(['Herbst', 'Vegan'], $recipe->tags->pluck('name')->all());
        $this->assertSame(['Mit Kürbiskernöl servieren.'], $recipe->tips);
        // Das Foto liegt in der Datenbank, nicht als Datei
        $this->assertNull($recipe->image_path);
        $this->assertSame('image/png', $recipe->image()->sole()->mime_type);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_large_phone_photos_are_accepted_up_to_12_mb(): void
    {
        Storage::fake('public');

        $this->post(route('recipes.store'), [
            ...$this->validPayload(),
            'image' => UploadedFile::fake()->create('iphone.jpg', 8 * 1024, 'image/jpeg'),
        ])->assertSessionDoesntHaveErrors('image');

        $this->post(route('recipes.store'), [
            ...$this->validPayload(),
            'title' => 'Zu groß',
            'image' => UploadedFile::fake()->create('riesig.jpg', 13 * 1024, 'image/jpeg'),
        ])->assertSessionHasErrors('image');
    }

    public function test_empty_rows_are_ignored_and_required_fields_validated(): void
    {
        $this->post(route('recipes.store'), [
            'title' => '',
            'ingredients' => [['amount' => '', 'unit' => '', 'name' => '']],
            'steps' => ['', '   '],
        ])->assertSessionHasErrors(['title', 'ingredients', 'steps']);

        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_ingredient_needs_a_name(): void
    {
        $this->post(route('recipes.store'), [
            ...$this->validPayload(),
            'ingredients' => [['amount' => '200', 'unit' => 'g', 'name' => '']],
        ])->assertSessionHasErrors('ingredients.0.name');
    }

    public function test_recipe_can_be_updated(): void
    {
        $recipe = Recipe::factory()->complete()->create();

        $this->put(route('recipes.update', $recipe), [
            ...$this->validPayload(),
            'title' => 'Neuer Titel',
            'ingredients' => [['amount' => '1', 'unit' => 'Stück', 'name' => 'Zwiebel']],
        ])->assertRedirect(route('recipes.show', $recipe));

        $recipe->refresh();
        $this->assertSame('Neuer Titel', $recipe->title);
        $this->assertSame(['Zwiebel'], $recipe->ingredients->pluck('name')->all());
    }

    public function test_recipe_can_be_deleted(): void
    {
        $recipe = Recipe::factory()->complete()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('recipes.destroy', $recipe))
            ->assertRedirect(route('recipes.index'));

        $this->assertModelMissing($recipe);
        $this->assertDatabaseCount('ingredients', 0);
        $this->assertDatabaseCount('steps', 0);
    }

    public function test_favorite_can_be_toggled(): void
    {
        $recipe = Recipe::factory()->create();

        $this->patch(route('recipes.favorite', $recipe))->assertRedirect();
        $this->assertTrue($this->user->hasFavorited($recipe));

        $this->patch(route('recipes.favorite', $recipe));
        $this->assertFalse($this->user->hasFavorited($recipe));
    }

    public function test_favorites_are_separate_per_family_member(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Gulasch']);
        $sibling = User::factory()->create();
        $sibling->favoriteRecipes()->attach($recipe);

        $this->get(route('recipes.index', ['favoriten' => 1]))->assertDontSee('Gulasch');
        $this->get(route('recipes.show', $recipe))->assertSee('Merken');

        $this->actingAs($sibling)
            ->get(route('recipes.index', ['favoriten' => 1]))
            ->assertSee('Gulasch');
    }

    public function test_slugs_are_unique(): void
    {
        $first = Recipe::factory()->create(['title' => 'Pfannkuchen']);
        $second = Recipe::factory()->create(['title' => 'Pfannkuchen']);

        $this->assertSame('pfannkuchen', $first->slug);
        $this->assertSame('pfannkuchen-2', $second->slug);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'title' => 'Kürbissuppe',
            'summary' => 'Cremig und wärmend.',
            'servings' => 4,
            'prep_minutes' => 15,
            'cook_minutes' => 25,
            'difficulty' => 'easy',
            'color' => '#E8853D',
            'tags' => 'Herbst, Vegan, herbst',
            'tips' => "Mit Kürbiskernöl servieren.\n\n",
            'ingredients' => [
                ['amount' => '1', 'unit' => 'Stück', 'name' => 'Kürbis'],
                ['amount' => '', 'unit' => '', 'name' => ''],
                ['amount' => '500', 'unit' => 'ml', 'name' => 'Brühe'],
            ],
            'steps' => ['Kürbis würfeln.', '', 'Alles pürieren.'],
        ];
    }
}
