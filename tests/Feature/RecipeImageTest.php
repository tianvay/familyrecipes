<?php

namespace Tests\Feature;

use App\Actions\DeleteRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecipeImageTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_photo_is_served_from_the_database_and_cached(): void
    {
        $recipe = Recipe::factory()->create();
        $recipe->storeImage(base64_decode(self::PNG), 'image/png');

        $this->assertStringContainsString('/rezepte/'.$recipe->slug.'/bild?v=', $recipe->image_url);

        $this->actingAs(User::factory()->child()->create());

        $response = $this->get($recipe->image_url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('ETag', '"'.$recipe->image_hash.'"');
        $this->assertSame(base64_decode(self::PNG), $response->getContent());
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));

        $this->get($recipe->image_url, ['If-None-Match' => '"'.$recipe->image_hash.'"'])->assertStatus(304);
    }

    public function test_photo_needs_login_and_an_existing_photo(): void
    {
        $recipe = Recipe::factory()->create();

        $this->get(route('recipes.image', $recipe))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('recipes.image', $recipe))->assertNotFound();
    }

    public function test_photo_can_be_replaced_and_removed_with_revision_log(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->complete()->create();
        $recipe->storeImage('alt', 'image/jpeg');
        $payload = [
            'title' => $recipe->title,
            'ingredients' => [['name' => 'Salz']],
            'steps' => ['Kochen.'],
        ];

        $this->actingAs($user)->put(route('recipes.update', $recipe), [
            ...$payload,
            'image' => UploadedFile::fake()->createWithContent('neu.png', base64_decode(self::PNG)),
        ])->assertSessionHasNoErrors();

        $recipe->refresh();
        $this->assertSame(sha1(base64_decode(self::PNG)), $recipe->image_hash);
        $this->assertDatabaseCount('recipe_images', 1);

        $this->put(route('recipes.update', $recipe), [...$payload, 'remove_image' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($recipe->refresh()->image_hash);
        $this->assertDatabaseCount('recipe_images', 0);
        $this->assertNull($recipe->image_url);
        // „Foto ersetzt“ und „Foto entfernt“ im Änderungsprotokoll
        $this->assertCount(2, $recipe->revisions()->get()->flatMap(fn ($revision) => collect($revision->changes)->where('field', 'image')));
    }

    public function test_deleting_a_recipe_deletes_its_photo(): void
    {
        $recipe = Recipe::factory()->create();
        $recipe->storeImage('foto', 'image/jpeg');

        app(DeleteRecipe::class)->handle($recipe);

        $this->assertDatabaseCount('recipe_images', 0);
    }

    public function test_old_photo_files_still_show_until_moved(): void
    {
        $recipe = Recipe::factory()->create(['image_path' => 'recipes/alt.jpg']);

        $this->assertSame(asset('storage/recipes/alt.jpg'), $recipe->image_url);
    }

    public function test_command_moves_local_photo_files_into_the_database(): void
    {
        Storage::disk('public')->put('recipes/hier.png', base64_decode(self::PNG));
        $here = Recipe::factory()->create(['title' => 'Hier', 'image_path' => 'recipes/hier.png']);
        $elsewhere = Recipe::factory()->create(['title' => 'Woanders', 'image_path' => 'recipes/woanders.jpg']);

        $this->artisan('recipes:move-images')
            ->expectsOutputToContain('1 übernommen, 1 nicht auf dieser Installation')
            ->assertSuccessful();

        $here->refresh();
        $this->assertNull($here->image_path);
        $this->assertSame(base64_decode(self::PNG), $here->image()->sole()->data);
        Storage::disk('public')->assertExists('recipes/hier.png'); // bleibt als Sicherung

        $this->assertSame('recipes/woanders.jpg', $elsewhere->refresh()->image_path);
        $this->assertNull($elsewhere->image_hash);

        // Zweiter Lauf: nichts mehr zu tun hier
        $this->artisan('recipes:move-images')->expectsOutputToContain('0 übernommen, 1 nicht')->assertSuccessful();
    }
}
