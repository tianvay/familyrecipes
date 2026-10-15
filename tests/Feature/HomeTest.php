<?php

namespace Tests\Feature;

use App\Models\MealPlanEntry;
use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-15 18:30'); // Donnerstag, KW 42
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_shows_today_tomorrow_and_the_coming_days(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Linsensuppe', 'prep_minutes' => 10, 'cook_minutes' => 20]);
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-15']);
        MealPlanEntry::factory()->create(['date' => '2026-10-16', 'title' => 'Pizza bestellen']);
        MealPlanEntry::factory()->create(['date' => '2026-10-20', 'title' => 'Fischstäbchen']);
        MealPlanEntry::factory()->create(['date' => '2026-10-14', 'title' => 'Gestern-Essen']);

        $this->actingAs(User::factory()->child()->create(['name' => 'Lena']))
            ->get('/')
            ->assertOk()
            ->assertSee('Guten Abend, Lena!')
            ->assertSeeInOrder(['Heute gibt\'s', 'Linsensuppe', 'ca. 30 Min.', 'Zum Rezept'])
            ->assertSee(route('recipes.show', $recipe))
            ->assertSeeInOrder(['Morgen · Freitag', 'Pizza bestellen'])
            ->assertSeeInOrder(['Demnächst', 'Dienstag', 'Fischstäbchen'])
            ->assertDontSee('Gestern-Essen');
    }

    public function test_empty_day_offers_planning_to_adults_and_suggesting_to_children(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('Noch nichts geplant')
            ->assertSee(route('meal-plan.edit', '2026-W42'));

        $this->actingAs(User::factory()->child()->create())
            ->get('/')
            ->assertSee('Etwas vorschlagen')
            ->assertDontSee(route('meal-plan.edit', '2026-W42'));
    }

    public function test_next_week_status_from_thursday_on(): void
    {
        $child = User::factory()->child()->create();
        MealPlanEntry::factory()->create(['date' => '2026-10-19', 'title' => 'Suppe']);
        MealSuggestion::factory()->for($child)->create();
        MealSuggestion::factory()->create();

        $this->actingAs($child)
            ->get('/')
            ->assertSee('Nächste Woche')
            ->assertSee('1 von 7 Tagen geplant · 2 Vorschläge')
            ->assertSee('(davon 1 von dir)');
    }

    public function test_shopping_list_tile_only_for_adults(): void
    {
        $this->actingAs(User::factory()->child()->create())
            ->get('/')
            ->assertDontSee(route('shopping-list.show'));

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee(route('shopping-list.show'));
    }

    public function test_pages_are_ready_for_the_home_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('<link rel="manifest"', false)
            ->assertSee('apple-touch-icon.png')
            ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false);

        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('Rezeptbuch', $manifest['short_name']);

        foreach (['apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'favicon.ico'] as $icon) {
            $this->assertFileExists(public_path($icon));
        }
    }

    public function test_login_page_is_ready_for_the_home_screen_too(): void
    {
        $this->get(route('login'))->assertSee('apple-touch-icon.png');
    }
}
