<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\User;
use App\Support\RecipeImport\JsonLdRecipeParser;
use App\Support\RecipeImport\UrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecipeImportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://www.chefkoch.de/rezepte/123/linsensuppe.html';

    private const IMAGE_URL = 'https://img.chefkoch-cdn.de/rezepte/123/bild.png';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');

        // Keine echten DNS-Abfragen in Tests: alle Hosts gelten als öffentlich …
        $this->app->instance(UrlGuard::class, new UrlGuard(fn (string $host) => match ($host) {
            'router.fritz.box' => ['192.168.178.1'], // … außer diesem
            default => ['93.184.216.34'],
        }));
    }

    public function test_children_cannot_import(): void
    {
        $this->actingAs(User::factory()->child()->create());

        $this->get(route('recipes.import'))->assertForbidden();
        $this->post(route('recipes.import.store'), ['url' => self::URL])->assertForbidden();
    }

    public function test_import_prefills_the_recipe_form(): void
    {
        $this->fakeChefkoch();

        $this->actingAs(User::factory()->create())
            ->post(route('recipes.import.store'), ['url' => self::URL])
            ->assertRedirect(route('recipes.create'))
            ->assertSessionHas('status');

        $old = session('_old_input');
        $this->assertSame('Rote Linsensuppe', $old['title']);
        $this->assertSame(4, $old['servings']);
        $this->assertSame(15, $old['prep_minutes']);
        $this->assertSame(30, $old['cook_minutes']);
        $this->assertSame(320, $old['calories']);
        $this->assertSame(self::URL, $old['source']);
        $this->assertSame('Suppe, Vegetarisch, Schnell', $old['tags']);
        $this->assertSame(
            ['amount' => '250', 'unit' => 'g', 'name' => 'Rote Linsen', 'is_optional' => false],
            $old['ingredients'][0],
        );
        $this->assertSame(['Zwiebel würfeln.', 'Linsen dazugeben und 20 Minuten kochen.', 'Pürieren.'], $old['steps']);
        $this->assertMatchesRegularExpression('#^imports/[a-f0-9\-]{36}\.png$#', $old['imported_image']);
        Storage::disk('public')->assertExists($old['imported_image']);

        $this->get(route('recipes.create'))
            ->assertOk()
            ->assertSee('value="Rote Linsensuppe"', false)
            ->assertSee('Foto aus dem Import wird übernommen.');
    }

    public function test_saving_takes_over_the_imported_photo(): void
    {
        Storage::disk('public')->put($import = 'imports/0b7c1a52-4b0e-4c55-9a6a-3f3a1c2d9e10.png', 'png');

        $this->actingAs(User::factory()->create())
            ->post(route('recipes.store'), [
                'title' => 'Rote Linsensuppe',
                'ingredients' => [['amount' => '250', 'unit' => 'g', 'name' => 'Rote Linsen']],
                'steps' => ['Kochen.'],
                'imported_image' => $import,
            ])
            ->assertSessionHasNoErrors();

        $recipe = Recipe::sole();
        $this->assertSame('recipes/0b7c1a52-4b0e-4c55-9a6a-3f3a1c2d9e10.png', $recipe->image_path);
        Storage::disk('public')->assertExists($recipe->image_path);
        Storage::disk('public')->assertMissing($import);
    }

    public function test_imported_photo_path_cannot_point_elsewhere(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('recipes.store'), [
                'title' => 'Hack',
                'ingredients' => [['name' => 'Salz']],
                'steps' => ['Kochen.'],
                'imported_image' => '../../.env',
            ])
            ->assertSessionHasErrors('imported_image');
    }

    public function test_addresses_in_the_home_network_are_refused(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());

        foreach (['http://192.168.178.38/', 'http://router.fritz.box/rezept', 'http://127.0.0.1/', 'http://[::1]/'] as $url) {
            $this->from(route('recipes.import'))
                ->post(route('recipes.import.store'), ['url' => $url])
                ->assertRedirect(route('recipes.import'))
                ->assertSessionHasErrors(['url' => 'Adressen im Heimnetz können nicht importiert werden.']);
        }

        Http::assertNothingSent();
    }

    public function test_pages_without_a_recipe_give_a_helpful_message(): void
    {
        Http::fake(['*' => Http::response('<html><body>Kein Rezept hier</body></html>')]);

        $this->actingAs(User::factory()->create())
            ->from(route('recipes.import'))
            ->post(route('recipes.import.store'), ['url' => 'https://example.com/blog'])
            ->assertRedirect(route('recipes.import'))
            ->assertSessionHasErrors('url');

        $this->assertStringContainsString('kein Rezept gefunden', session('errors')->first('url'));
    }

    public function test_parser_understands_graph_sections_and_image_objects(): void
    {
        $html = $this->page([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'WebPage', 'name' => 'Blog'],
                [
                    '@type' => ['Recipe', 'NewsArticle'],
                    'name' => 'Ofengemüse &amp; Feta',
                    'recipeYield' => ['4', '4 Portionen'],
                    'totalTime' => 'PT1H',
                    'prepTime' => 'PT20M',
                    'image' => [['@type' => 'ImageObject', 'url' => 'https://example.com/bild.jpg']],
                    'recipeIngredient' => ['1 kg Kartoffeln', '200 g Feta'],
                    'recipeInstructions' => [
                        ['@type' => 'HowToSection', 'name' => 'Gemüse', 'itemListElement' => [
                            ['@type' => 'HowToStep', 'text' => 'Kartoffeln schneiden.'],
                            ['@type' => 'HowToStep', 'text' => '<p>Backen.</p>'],
                        ]],
                        ['@type' => 'HowToStep', 'text' => 'Feta darüber.'],
                    ],
                    'keywords' => 'Ofen, ofen, Vegetarisch',
                ],
            ],
        ]);

        $recipe = JsonLdRecipeParser::parse($html);

        $this->assertSame('Ofengemüse & Feta', $recipe['title']);
        $this->assertSame(4, $recipe['servings']);
        $this->assertSame(20, $recipe['prep_minutes']);
        $this->assertSame(40, $recipe['cook_minutes']); // Gesamtzeit minus Vorbereitung
        $this->assertSame('https://example.com/bild.jpg', $recipe['image_url']);
        $this->assertSame(['Kartoffeln schneiden.', 'Backen.', 'Feta darüber.'], $recipe['steps']);
        $this->assertSame(['Ofen', 'Vegetarisch'], $recipe['tags']);
        $this->assertSame('1', $recipe['ingredients'][0]['amount']);
        $this->assertSame('kg', $recipe['ingredients'][0]['unit']);
    }

    public function test_parser_resolves_id_references_like_chefkoch(): void
    {
        $recipe = JsonLdRecipeParser::parse($this->page([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Recipe',
                    'name' => 'Klassischer Linseneintopf von Surina',
                    'author' => ['@id' => 'https://example.com/user/1#author'],
                    'image' => ['@id' => 'https://example.com/rezept#primaryimage'],
                    'recipeCategory' => 'Kochen',
                    'keywords' => 'Raffiniert oder preiswert, Hauptspeise',
                    'recipeIngredient' => ['250 g Tellerlinsen'],
                    'recipeInstructions' => [['@type' => 'HowToStep', 'text' => 'Kochen.']],
                ],
                ['@type' => 'ImageObject', '@id' => 'https://example.com/rezept#primaryimage', 'url' => 'https://img.example.com/linsen.jpg'],
                ['@type' => 'Person', '@id' => 'https://example.com/user/1#author', 'name' => 'Surina'],
            ],
        ]));

        $this->assertSame('Klassischer Linseneintopf', $recipe['title']);
        $this->assertSame('https://img.example.com/linsen.jpg', $recipe['image_url']);
        $this->assertSame(['Hauptspeise'], $recipe['tags']);
    }

    public function test_advertising_is_removed_from_the_description(): void
    {
        $recipe = JsonLdRecipeParser::parse($this->page([
            '@type' => 'Recipe',
            'name' => 'Feine Linsensuppe',
            'description' => 'Feine Linsensuppe - Eine würzige Linsensuppe mit Kartoffeln. Über 1037 Bewertungen und für sehr gut befunden. Mit ► Portionsrechner ► Kochbuch ► Video-Tipps!',
            'recipeIngredient' => ['200 g Linsen'],
            'recipeInstructions' => 'Kochen.',
        ]));

        $this->assertSame('Eine würzige Linsensuppe mit Kartoffeln.', $recipe['description']);
    }

    public function test_titles_ending_with_von_are_kept_without_matching_author(): void
    {
        $recipe = JsonLdRecipeParser::parse($this->page([
            '@type' => 'Recipe',
            'name' => 'Apfelkuchen von Oma',
            'author' => ['@type' => 'Person', 'name' => 'Anna'],
            'recipeIngredient' => ['3 Äpfel'],
            'recipeInstructions' => 'Backen.',
        ]));

        $this->assertSame('Apfelkuchen von Oma', $recipe['title']);
    }

    public function test_instructions_as_plain_text_are_split_into_steps(): void
    {
        $recipe = JsonLdRecipeParser::parse($this->page([
            '@type' => 'Recipe',
            'name' => 'Rührei',
            'recipeIngredient' => ['2 Eier'],
            'recipeInstructions' => "Eier verquirlen.\nIn der Pfanne stocken lassen.<br>Salzen.",
        ]));

        $this->assertSame(['Eier verquirlen.', 'In der Pfanne stocken lassen.', 'Salzen.'], $recipe['steps']);
    }

    public function test_import_links_are_shown_to_adults(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('recipes.index'))
            ->assertSee(route('recipes.import'));

        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.index'))
            ->assertDontSee(route('recipes.import'));
    }

    private function fakeChefkoch(): void
    {
        Http::fake([
            self::URL => Http::response($this->page([
                '@context' => 'https://schema.org',
                '@type' => 'Recipe',
                'name' => 'Rote Linsensuppe',
                'description' => 'Schnelle, cremige Suppe.',
                'image' => self::IMAGE_URL,
                'recipeYield' => '4 Portionen',
                'prepTime' => 'P0DT0H15M',
                'cookTime' => 'P0DT0H30M',
                'nutrition' => ['@type' => 'NutritionInformation', 'calories' => '320 kcal'],
                'recipeCategory' => ['Suppe'],
                'keywords' => 'Vegetarisch, Schnell',
                'recipeIngredient' => ['250 g Rote Linsen', '1 Zwiebel(n)', '1 Liter Gemüsebrühe'],
                'recipeInstructions' => [
                    ['@type' => 'HowToStep', 'text' => 'Zwiebel würfeln.'],
                    ['@type' => 'HowToStep', 'text' => 'Linsen dazugeben und 20 Minuten kochen.'],
                    ['@type' => 'HowToStep', 'text' => 'Pürieren.'],
                ],
            ]), 200, ['Content-Type' => 'text/html; charset=utf-8']),
            self::IMAGE_URL => Http::response('fake-png-bytes', 200, ['Content-Type' => 'image/png']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(array $data): string
    {
        return '<html><head><script type="application/ld+json">'
            .json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .'</script></head><body>Rezept</body></html>';
    }
}
