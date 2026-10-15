<?php

namespace Tests\Feature;

use App\Models\MealPlanEntry;
use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Models\User;
use App\Support\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanTest extends TestCase
{
    use RefreshDatabase;

    private const THIS_WEEK = '2026-W42'; // 12.–18.10.2026

    private const NEXT_WEEK = '2026-W43'; // 19.–25.10.2026

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-14 18:00'); // Mittwoch
    }

    // --- Ansehen -------------------------------------------------------------

    public function test_guests_cannot_see_the_meal_plan(): void
    {
        $this->get(route('meal-plan.show'))->assertRedirect(route('login'));
    }

    public function test_everyone_sees_the_current_week_with_today_highlighted(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Linsensuppe']);
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-14']);
        MealPlanEntry::factory()->create(['date' => '2026-10-16', 'title' => 'Pizza bestellen']);

        $this->actingAs(User::factory()->child()->create())
            ->get(route('meal-plan.show'))
            ->assertOk()
            ->assertSee('KW 42')
            ->assertSeeInOrder(['Mittwoch', 'Heute', 'Linsensuppe', 'Freitag', 'Pizza bestellen'])
            ->assertDontSee('Plan festlegen');
    }

    public function test_sunday_belongs_to_the_week(): void
    {
        MealPlanEntry::factory()->create(['date' => '2026-10-18', 'title' => 'Sonntagsbraten']);
        MealPlanEntry::factory()->create(['date' => '2026-10-19', 'title' => 'Montagssuppe']);

        $this->assertSame(['Sonntagsbraten'], MealPlanEntry::query()->forWeek(Week::fromIso(self::THIS_WEEK))->pluck('title')->all());

        $this->actingAs(User::factory()->create())
            ->get(route('meal-plan.show'))
            ->assertSeeInOrder(['Sonntag', 'Sonntagsbraten'])
            ->assertDontSee('Montagssuppe');
    }

    public function test_invalid_weeks_return_404(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/essensplan/2026-W99')->assertNotFound();
        $this->get('/essensplan/2025-W53')->assertNotFound(); // 2025 hat nur 52 Wochen
        $this->get('/essensplan/irgendwas')->assertNotFound();
    }

    public function test_past_weeks_stay_readable_but_cannot_be_changed(): void
    {
        MealPlanEntry::factory()->create(['date' => '2026-10-01', 'title' => 'Kartoffelsuppe']);
        $adult = User::factory()->create();

        $this->actingAs($adult)
            ->get('/essensplan/2026-W40')
            ->assertOk()
            ->assertSee('Kartoffelsuppe')
            ->assertSee('Diese Woche ist vorbei')
            ->assertDontSee('Plan festlegen')
            ->assertDontSee('Etwas wünschen');

        $this->get('/essensplan/2026-W40/planen')->assertForbidden();
        $this->put('/essensplan/2026-W40', ['days' => [1 => ['choice' => '']]])->assertForbidden();
    }

    // --- Plan festlegen ------------------------------------------------------

    public function test_children_cannot_set_the_plan(): void
    {
        $this->actingAs(User::factory()->child()->create());

        $this->get(route('meal-plan.edit', self::NEXT_WEEK))->assertForbidden();
        $this->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [1 => ['choice' => 't', 'title' => 'Eis']]])
            ->assertForbidden();

        $this->assertDatabaseCount('meal_plan_entries', 0);
    }

    public function test_planning_page_shows_all_wishes(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Lasagne']);
        MealSuggestion::factory()->forRecipe($recipe)->for(User::factory()->child()->create(['name' => 'Lena']))->create();
        MealSuggestion::factory()->forRecipe($recipe)->for(User::factory()->child()->create(['name' => 'Max']))->create(['preferred_day' => 6]);

        $this->actingAs(User::factory()->create())
            ->get(route('meal-plan.edit', self::NEXT_WEEK))
            ->assertOk()
            ->assertSee('Wünsche der Familie')
            ->assertSee('Lasagne (2× · Lena, Max)')
            ->assertSee('Wunsch für diesen Tag: Lasagne (Max)');
    }

    public function test_adults_set_the_plan_from_recipes_suggestions_and_text(): void
    {
        $adult = User::factory()->create();
        $recipe = Recipe::factory()->create(['title' => 'Lasagne']);
        $textSuggestion = MealSuggestion::factory()->create(['title' => 'Essen bei Oma']);

        $this->actingAs($adult)
            ->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [
                1 => ['choice' => "r{$recipe->id}"],
                2 => ['choice' => "s{$textSuggestion->id}"],
                3 => ['choice' => 't', 'title' => 'Reste'],
                4 => ['choice' => ''],
            ]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('meal-plan.show', self::NEXT_WEEK));

        $plan = MealPlanEntry::query()->orderBy('date')->get();
        $this->assertSame(['2026-10-19', '2026-10-20', '2026-10-21'], $plan->map->date->map->toDateString()->all());
        $this->assertSame(['Lasagne', 'Essen bei Oma', 'Reste'], $plan->pluck('title')->all());
        $this->assertSame([$recipe->id, null, null], $plan->pluck('recipe_id')->all());
        $this->assertTrue($plan->every(fn (MealPlanEntry $e) => $e->planned_by === $adult->id));
    }

    public function test_a_dish_may_appear_at_most_twice_per_week(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Lasagne']);

        $this->actingAs(User::factory()->create())
            ->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [
                1 => ['choice' => "r{$recipe->id}"],
                3 => ['choice' => "r{$recipe->id}"],
                5 => ['choice' => "r{$recipe->id}"],
            ]])
            ->assertSessionHasErrors(['days.5.choice' => '„Lasagne“ darf höchstens 2-mal pro Woche auf dem Plan stehen.']);

        $this->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [
            1 => ['choice' => 't', 'title' => 'Reste'],
            2 => ['choice' => 't', 'title' => 'reste'],
            3 => ['choice' => 't', 'title' => 'RESTE'],
        ]])->assertSessionHasErrors('days.3.choice');

        $this->assertDatabaseCount('meal_plan_entries', 0);
    }

    public function test_twice_per_week_is_allowed(): void
    {
        $recipe = Recipe::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [
                1 => ['choice' => "r{$recipe->id}"],
                4 => ['choice' => "r{$recipe->id}"],
            ]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('meal_plan_entries', 2);
    }

    public function test_invalid_choices_are_rejected(): void
    {
        // Ein Freitext-Wunsch erst ab übernächster Woche passt nicht in den Plan für nächste Woche
        $foreignWeekSuggestion = MealSuggestion::factory()->forWeek(Week::fromIso('2026-W44'))->create();

        $this->actingAs(User::factory()->create())
            ->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [
                1 => ['choice' => 'r999'],
                2 => ['choice' => "s{$foreignWeekSuggestion->id}"],
                3 => ['choice' => 't', 'title' => ''],
                8 => ['choice' => ''],
            ]])
            ->assertSessionHasErrors('days');

        $this->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [
            1 => ['choice' => 'r999'],
            2 => ['choice' => "s{$foreignWeekSuggestion->id}"],
            3 => ['choice' => 't', 'title' => ''],
        ]])->assertSessionHasErrors(['days.3.title']);

        $this->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [
            1 => ['choice' => 'r999'],
            2 => ['choice' => "s{$foreignWeekSuggestion->id}"],
        ]])->assertSessionHasErrors(['days.1.choice', 'days.2.choice']);

        $this->assertDatabaseCount('meal_plan_entries', 0);
    }

    public function test_unchanged_days_keep_who_planned_them_and_cleared_days_are_removed(): void
    {
        $papa = User::factory()->create();
        $mama = User::factory()->create();

        $this->actingAs($papa)->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [
            1 => ['choice' => 't', 'title' => 'Suppe'],
            2 => ['choice' => 't', 'title' => 'Salat'],
            3 => ['choice' => 't', 'title' => 'Fisch'],
        ]]);

        $this->actingAs($mama)->put(route('meal-plan.update', self::NEXT_WEEK), ['days' => [
            1 => ['choice' => 't', 'title' => 'Suppe'],
            2 => ['choice' => 't', 'title' => 'Nudeln'],
            3 => ['choice' => ''],
        ]]);

        $plan = MealPlanEntry::query()->orderBy('date')->get();
        $this->assertSame(['Suppe', 'Nudeln'], $plan->pluck('title')->all());
        $this->assertSame([$papa->id, $mama->id], $plan->pluck('planned_by')->all());
    }

    public function test_history_survives_deleting_a_recipe(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Gulasch']);
        MealSuggestion::factory()->forRecipe($recipe)->create();
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-20']);

        $recipe->delete();

        $this->assertDatabaseHas('meal_suggestions', ['title' => 'Gulasch', 'recipe_id' => null]);
        $this->assertDatabaseHas('meal_plan_entries', ['title' => 'Gulasch', 'recipe_id' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('meal-plan.show', self::NEXT_WEEK))
            ->assertSee('Gulasch');
    }
}
