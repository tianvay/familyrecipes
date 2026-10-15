<?php

namespace Tests\Feature;

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

    public function test_members_cannot_see_family_overview(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('family.favorites'))
            ->assertForbidden()
            ->assertSee('Kein Zugriff');
    }

    public function test_admin_sees_favorites_of_every_member(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Papa']);
        $lena = User::factory()->create(['name' => 'Lena']);
        $max = User::factory()->create(['name' => 'Max']);

        $pizza = Recipe::factory()->create(['title' => 'Pizza']);
        $salad = Recipe::factory()->create(['title' => 'Salat']);
        Recipe::factory()->create(['title' => 'Rosenkohl']);

        $lena->favoriteRecipes()->attach([$pizza->id, $salad->id]);
        $max->favoriteRecipes()->attach($pizza);

        $this->actingAs($admin)
            ->get(route('family.favorites'))
            ->assertOk()
            ->assertSee(['Lena', 'Max', 'Papa', 'Pizza', 'Salat'])
            ->assertDontSee('Rosenkohl')
            ->assertSeeInOrder(['Am beliebtesten', 'Pizza', '♥ 2', 'Salat', '♥ 1'])
            ->assertSee('Noch keine Favoriten markiert.');
    }

    public function test_family_link_is_only_shown_to_admins(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('recipes.index'))
            ->assertDontSee(route('family.favorites'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('recipes.index'))
            ->assertSee(route('family.favorites'));
    }
}
