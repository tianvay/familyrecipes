<?php

namespace Tests\Feature;

use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\User;
use App\Support\LastEaten;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastEatenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-14 18:00'); // Mittwoch, KW 42
    }

    public function test_only_past_and_todays_plan_counts(): void
    {
        $recipe = Recipe::factory()->create();
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-09-30']);
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-10']);
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-20']); // Zukunft zählt nicht

        $this->assertSame('2026-10-10', LastEaten::forRecipe($recipe->id)->toDateString());
        $this->assertNull(LastEaten::forRecipe(Recipe::factory()->create()->id));
    }

    public function test_labels(): void
    {
        $today = CarbonImmutable::today();

        $this->assertSame('noch nie', LastEaten::label(null));
        $this->assertSame('heute', LastEaten::label($today));
        $this->assertSame('gestern', LastEaten::label($today->subDay()));
        $this->assertSame('vor 5 Tagen', LastEaten::label($today->subDays(5)));
        $this->assertSame('vor 3 Wochen', LastEaten::label($today->subDays(21)));
        $this->assertSame('vor 4 Monaten', LastEaten::label($today->subDays(125)));
        $this->assertSame('vor über einem Jahr', LastEaten::label($today->subYears(2)));
    }

    public function test_planning_shows_last_eaten_and_long_ago_group(): void
    {
        $recent = Recipe::factory()->create(['title' => 'Gestern-Suppe']);
        $old = Recipe::factory()->create(['title' => 'Uralt-Auflauf']);
        $never = Recipe::factory()->create(['title' => 'Nie-Gericht']);
        MealPlanEntry::factory()->forRecipe($recent)->create(['date' => '2026-10-13']);
        MealPlanEntry::factory()->forRecipe($old)->create(['date' => '2026-08-01']);

        $this->actingAs(User::factory()->create())
            ->get(route('meal-plan.edit', '2026-W43'))
            ->assertOk()
            ->assertSee('Gestern-Suppe · zuletzt gestern')
            ->assertSee('Uralt-Auflauf · zuletzt vor 2 Monaten')
            ->assertSee('Nie-Gericht · zuletzt noch nie')
            // „Lange nicht gegessen“: nie zuerst, dann das älteste; Gestern-Suppe nicht
            ->assertSeeInOrder(['Lange nicht gegessen', 'Nie-Gericht', 'Uralt-Auflauf', 'Alle Rezepte']);

        $html = $this->get(route('meal-plan.edit', '2026-W43'))->getContent();
        $group = str($html)->after('label="Lange nicht gegessen"')->before('</optgroup>');
        $this->assertStringNotContainsString('Gestern-Suppe', $group);
    }

    public function test_recipe_page_shows_when_it_was_last_eaten(): void
    {
        $recipe = Recipe::factory()->complete()->create();
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-09']);

        $this->actingAs(User::factory()->create())
            ->get(route('recipes.show', $recipe))
            ->assertSee('Zuletzt gegessen:')
            ->assertSee('Freitag, 09.10.2026')
            ->assertSee('(vor 5 Tagen)');
    }
}
