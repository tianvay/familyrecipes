<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_children_can_view_and_favorite_recipes(): void
    {
        $child = User::factory()->child()->create();
        $recipe = Recipe::factory()->complete()->create();

        $this->actingAs($child);
        $this->get(route('recipes.index'))->assertOk();
        $this->get(route('recipes.show', $recipe))->assertOk();
        $this->get(route('recipes.history', $recipe))->assertOk();

        $this->patch(route('recipes.favorite', $recipe))->assertRedirect();
        $this->assertTrue($child->hasFavorited($recipe));
    }

    public function test_children_cannot_change_recipes(): void
    {
        $child = User::factory()->child()->create();
        $recipe = Recipe::factory()->complete()->create(['title' => 'Original']);

        $this->actingAs($child);
        $this->get(route('recipes.create'))->assertForbidden();
        $this->post(route('recipes.store'), $this->payload())->assertForbidden();
        $this->get(route('recipes.edit', $recipe))->assertForbidden();
        $this->put(route('recipes.update', $recipe), $this->payload())->assertForbidden();
        $this->delete(route('recipes.destroy', $recipe))->assertForbidden();

        $this->assertSame('Original', $recipe->fresh()->title);
        $this->assertDatabaseCount('recipes', 1);
        $this->assertDatabaseCount('recipe_revisions', 0);
    }

    public function test_children_do_not_see_edit_controls(): void
    {
        $recipe = Recipe::factory()->complete()->create();

        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.show', $recipe))
            ->assertDontSee(route('recipes.edit', $recipe))
            ->assertDontSee('Löschen')
            ->assertDontSee(route('recipes.create'));
    }

    public function test_adults_can_change_but_not_delete_recipes(): void
    {
        $recipe = Recipe::factory()->complete()->create();

        $this->actingAs(User::factory()->create());
        $this->get(route('recipes.show', $recipe))
            ->assertSee(route('recipes.edit', $recipe))
            ->assertDontSee('Löschen');
        $this->get(route('recipes.edit', $recipe))->assertOk();
        $this->put(route('recipes.update', $recipe), $this->payload())->assertRedirect(route('recipes.show', $recipe));
        $this->delete(route('recipes.destroy', $recipe))->assertForbidden();

        $this->assertModelExists($recipe);
    }

    public function test_only_the_admin_can_delete_recipes(): void
    {
        $recipe = Recipe::factory()->complete()->create();

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('recipes.show', $recipe))->assertSee('Löschen');
        $this->delete(route('recipes.destroy', $recipe))->assertRedirect(route('recipes.index'));

        $this->assertModelMissing($recipe);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => 'Geändert',
            'ingredients' => [['amount' => '1', 'unit' => 'Stück', 'name' => 'Zwiebel']],
            'steps' => ['Schneiden.'],
        ];
    }
}
