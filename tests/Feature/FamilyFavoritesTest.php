<?php

namespace Tests\Feature;

use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyFavoritesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_children_cannot_see_family_overview(): void
    {
        $this->actingAs(User::factory()->child()->create())
            ->get(route('family.favorites'))
            ->assertForbidden()
            ->assertSee('Kein Zugriff');
    }

    public function test_adults_see_family_overview_but_not_user_management(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('family.favorites'))
            ->assertOk()
            ->assertSee('Wer mag was?');

        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_admin_sees_favorites_of_every_member(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Verwaltung']);
        $lena = User::factory()->create(['name' => 'Lena']);
        $max = User::factory()->create(['name' => 'Max']);
        User::factory()->create(['name' => 'Papa']);

        $pizza = Recipe::factory()->create(['title' => 'Pizza']);
        $salad = Recipe::factory()->create(['title' => 'Salat']);
        $sprouts = Recipe::factory()->create(['title' => 'Rosenkohl']);

        $lena->favoriteRecipes()->attach([$pizza->id, $salad->id]);
        $max->favoriteRecipes()->attach($pizza);
        // Favoriten des Admin-Accounts zählen nicht mit.
        $admin->favoriteRecipes()->attach([$pizza->id, $sprouts->id]);

        $this->actingAs($admin)
            ->get(route('family.favorites'))
            ->assertOk()
            ->assertSee(['Lena', 'Max', 'Papa', 'Pizza', 'Salat'])
            ->assertDontSee('Rosenkohl')
            ->assertViewHas('members', fn ($members) => $members->pluck('name')->all() === ['Lena', 'Max', 'Papa'])
            ->assertViewHas('popular', fn ($popular) => $popular->first()->favoritedBy->pluck('name')->all() === ['Lena', 'Max'])
            ->assertSeeInOrder(['Am beliebtesten', 'Pizza', '♥ 2', 'Salat', '♥ 1'])
            ->assertSee('Noch keine Favoriten markiert.');
    }

    public function test_popular_shows_the_top_five_favorites(): void
    {
        $members = User::factory()->count(3)->create();
        $recipes = Recipe::factory()->count(7)->sequence(fn ($s) => ['title' => 'Rezept '.($s->index + 1)])->create();

        // Rezept 7 von allen, Rezept 6 von zweien, der Rest von einer Person
        $recipes->each(fn (Recipe $recipe) => $members[0]->favoriteRecipes()->attach($recipe));
        $members[1]->favoriteRecipes()->attach([$recipes[5]->id, $recipes[6]->id]);
        $members[2]->favoriteRecipes()->attach($recipes[6]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('family.favorites'))
            ->assertViewHas('popular', fn ($popular) => $popular->pluck('title')->all()
                === ['Rezept 7', 'Rezept 6', 'Rezept 1', 'Rezept 2', 'Rezept 3']);
    }

    public function test_most_wished_counts_all_wishes_without_the_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $lena = User::factory()->child()->create(['name' => 'Lena']);
        $max = User::factory()->create(['name' => 'Max']);
        $pizza = Recipe::factory()->create(['title' => 'Pizza']);

        MealSuggestion::factory()->for($lena)->forRecipe($pizza)->create();
        MealSuggestion::factory()->for($lena)->forRecipe($pizza)->create(); // z. B. später erneut gewünscht
        MealSuggestion::factory()->for($max)->forRecipe($pizza)->create();
        MealSuggestion::factory()->for($max)->create(['recipe_id' => null, 'title' => 'Döner']);
        MealSuggestion::factory()->for($lena)->create(['recipe_id' => null, 'title' => 'döner ']);
        MealSuggestion::factory()->for($admin)->create(['recipe_id' => null, 'title' => 'Sushi']);

        $this->actingAs($admin)
            ->get(route('family.favorites'))
            ->assertOk()
            ->assertSeeInOrder(['Am häufigsten gewünscht', 'Pizza', '⭐ 3×', 'Döner', '⭐ 2×'])
            ->assertViewHas('mostWished', fn ($dishes) => $dishes->pluck('title')->all() === ['Pizza', 'Döner']
                && $dishes->first()['wishers']->pluck('name')->all() === ['Lena', 'Max']);
    }

    public function test_family_link_is_shown_to_adults_and_admins_only(): void
    {
        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.index'))
            ->assertDontSee(route('family.favorites'));

        $this->actingAs(User::factory()->create())
            ->get(route('recipes.index'))
            ->assertSee(route('family.favorites'))
            ->assertDontSee(route('users.index'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('recipes.index'))
            ->assertSee(route('family.favorites'))
            ->assertSee(route('users.index'));
    }
}
