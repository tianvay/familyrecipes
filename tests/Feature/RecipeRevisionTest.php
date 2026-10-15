<?php

namespace Tests\Feature;

use App\Enums\RevisionAction;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $anna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->anna = User::factory()->create(['name' => 'Anna']);
        $this->actingAs($this->anna);
    }

    public function test_creating_a_recipe_is_recorded(): void
    {
        $this->post(route('recipes.store'), $this->payload());

        $revision = RecipeRevision::sole();
        $this->assertSame(RevisionAction::Created, $revision->action);
        $this->assertTrue($revision->user->is($this->anna));
        $this->assertSame('Anna', $revision->user_name);
        $this->assertSame('Linsensuppe', $revision->recipe_title);
        $this->assertNull($revision->changes);
    }

    public function test_updates_record_who_changed_what(): void
    {
        $this->post(route('recipes.store'), $this->payload());
        $recipe = Recipe::sole();

        $this->put(route('recipes.update', $recipe), $this->payload([
            'cook_minutes' => 30,
            'tags' => 'Suppe, Winter',
            'ingredients' => [
                ['amount' => '250', 'unit' => 'g', 'name' => 'Linsen'],
                ['amount' => '1', 'unit' => 'Stück', 'name' => 'Möhre', 'is_optional' => '1'],
            ],
        ]));

        $revision = $recipe->revisions()->first();
        $this->assertSame(RevisionAction::Updated, $revision->action);

        $changes = collect($revision->changes)->keyBy('field');
        $this->assertEqualsCanonicalizing(['cook_minutes', 'tags', 'ingredients'], $changes->keys()->all());
        $this->assertSame(['old' => '20 Min.', 'new' => '30 Min.'], collect($changes['cook_minutes'])->only('old', 'new')->all());
        $this->assertSame(['Winter'], $changes['tags']['added']);
        $this->assertSame(['250 g Linsen', '1 Stück Möhre (optional)'], $changes['ingredients']['added']);
        $this->assertSame(['200 g Linsen', '1 Stück Zwiebel'], $changes['ingredients']['removed']);
    }

    public function test_reordering_steps_is_recorded(): void
    {
        $this->post(route('recipes.store'), $this->payload());
        $recipe = Recipe::sole();

        $this->put(route('recipes.update', $recipe), $this->payload([
            'steps' => ['Alles kochen.', 'Zwiebel schneiden.'],
        ]));

        $change = $recipe->revisions()->first()->changes[0];
        $this->assertSame('steps', $change['field']);
        $this->assertTrue($change['reordered']);
    }

    public function test_saving_without_changes_is_not_recorded(): void
    {
        $this->post(route('recipes.store'), $this->payload());
        $recipe = Recipe::sole();

        $this->put(route('recipes.update', $recipe), $this->payload());

        $this->assertSame(1, $recipe->revisions()->count());
    }

    public function test_deleting_keeps_the_log_entry(): void
    {
        $this->post(route('recipes.store'), $this->payload());
        $recipe = Recipe::sole();

        $this->delete(route('recipes.destroy', $recipe));

        $this->assertModelMissing($recipe);
        $deleted = RecipeRevision::where('action', RevisionAction::Deleted)->sole();
        $this->assertNull($deleted->recipe_id);
        $this->assertSame('Linsensuppe', $deleted->recipe_title);
        $this->assertSame('Anna', $deleted->user_name);
    }

    public function test_log_entries_survive_deleting_the_author(): void
    {
        $this->post(route('recipes.store'), $this->payload());
        $this->anna->delete();

        $revision = RecipeRevision::sole();
        $this->assertNull($revision->user_id);
        $this->assertSame('Anna', $revision->authorName());
    }

    public function test_recipe_page_shows_last_change_and_history(): void
    {
        $this->post(route('recipes.store'), $this->payload());
        $recipe = Recipe::sole();
        $this->put(route('recipes.update', $recipe), $this->payload(['cook_minutes' => 30]));

        $this->get(route('recipes.show', $recipe))
            ->assertSee('Zuletzt bearbeitet von Anna')
            ->assertSee(route('recipes.history', $recipe));

        $this->get(route('recipes.history', $recipe))
            ->assertOk()
            ->assertSeeInOrder(['Anna', 'bearbeitet', 'Koch-/Backzeit', '20 Min.', '30 Min.', 'Anna', 'angelegt']);
    }

    public function test_revision_log_is_admin_only_and_lists_deleted_recipes(): void
    {
        $this->post(route('recipes.store'), $this->payload());
        $this->delete(route('recipes.destroy', Recipe::sole()));

        $this->get(route('revisions.index'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('revisions.index'))
            ->assertOk()
            ->assertSeeInOrder(['Anna', '„Linsensuppe“', 'gelöscht', 'Anna', '„Linsensuppe“', 'angelegt']);
    }

    public function test_seeded_recipes_are_attributed_to_system(): void
    {
        $this->seed();

        $revision = RecipeRevision::first();
        $this->assertNull($revision->user_name);
        $this->assertSame('System', $revision->authorName());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Linsensuppe',
            'servings' => 4,
            'prep_minutes' => 10,
            'cook_minutes' => 20,
            'tags' => 'Suppe',
            'ingredients' => [
                ['amount' => '200', 'unit' => 'g', 'name' => 'Linsen'],
                ['amount' => '1', 'unit' => 'Stück', 'name' => 'Zwiebel'],
            ],
            'steps' => ['Zwiebel schneiden.', 'Alles kochen.'],
            ...$overrides,
        ];
    }
}
