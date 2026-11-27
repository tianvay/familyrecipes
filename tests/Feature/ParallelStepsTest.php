<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\Step;
use App\Models\User;
use App\Support\CookingSteps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParallelStepsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_form_saves_parallel_steps(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('recipes.store'), [
                'title' => 'Nudeln mit Soße',
                'ingredients' => [['amount' => '500', 'unit' => 'g', 'name' => 'Nudeln']],
                'steps' => [
                    // Der erste Schritt kann nicht gleichzeitig sein – Häkchen wird ignoriert
                    ['body' => 'Wasser aufsetzen.', 'parallel' => '1'],
                    ['body' => '', 'parallel' => '1'],
                    ['body' => 'Nudeln 10 Minuten kochen.'],
                    ['body' => 'Soße anbraten.', 'parallel' => '1'],
                    ['body' => 'Alles mischen.'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $steps = Recipe::sole()->steps()->get();

        $this->assertSame(['Wasser aufsetzen.', 'Nudeln 10 Minuten kochen.', 'Soße anbraten.', 'Alles mischen.'], $steps->pluck('body')->all());
        $this->assertSame([false, false, true, false], $steps->pluck('parallel')->all());
    }

    public function test_step_text_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('recipes.store'), [
                'title' => 'Ohne Text',
                'ingredients' => [['name' => 'Nudeln']],
                'steps' => [['body' => str_repeat('x', 2001)]],
            ])
            ->assertSessionHasErrors('steps.0.body');
    }

    public function test_steps_are_grouped(): void
    {
        $groups = CookingSteps::groups([
            ['parallel' => true], // erster Schritt: immer eigene Gruppe
            ['parallel' => false],
            ['parallel' => true],
            ['parallel' => true],
            ['parallel' => false],
        ]);

        $this->assertSame([[0], [1, 2, 3], [4]], array_map(array_keys(...), $groups));
    }

    public function test_cooking_mode_shows_parallel_steps_on_one_page(): void
    {
        $recipe = $this->recipe();

        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.cook', $recipe))
            ->assertOk()
            ->assertViewHas('pages', fn (array $pages) => array_map(fn (array $page) => array_column($page, 'number'), $pages) === [[1], [2, 3], [4]])
            ->assertSee('von 4');
    }

    public function test_recipe_page_marks_parallel_steps(): void
    {
        $recipe = $this->recipe();

        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.show', $recipe))
            ->assertSeeInOrder(['Wasser aufsetzen.', 'Gleichzeitig', 'Nudeln 10 Minuten kochen.', 'Soße anbraten.', 'Alles mischen.']);
    }

    public function test_edit_form_keeps_parallel_flag(): void
    {
        $recipe = $this->recipe();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('recipes.edit', $recipe))
            ->assertOk()
            ->assertSee('gleichzeitig mit Schritt');

        // @js kodiert Anführungszeichen als Unicode-Escape – nur der dritte Schritt ist gleichzeitig
        $this->assertSame(1, substr_count($response->getContent(), 'parallel'.chr(92).'u0022:true'));
    }

    public function test_revision_log_records_parallel_change(): void
    {
        $recipe = $this->recipe();

        $this->assertContains('(gleichzeitig) Soße anbraten.', $recipe->auditSnapshot()['steps']);
    }

    private function recipe(): Recipe
    {
        $recipe = Recipe::factory()->create(['title' => 'Nudeln mit Soße']);

        foreach (['Wasser aufsetzen.', 'Nudeln 10 Minuten kochen.', 'Soße anbraten.', 'Alles mischen.'] as $i => $body) {
            Step::factory()->for($recipe)->create(['position' => $i + 1, 'body' => $body, 'parallel' => $i === 2]);
        }

        return $recipe;
    }
}
