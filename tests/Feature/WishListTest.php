<?php

namespace Tests\Feature;

use App\Models\MealPlanEntry;
use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Models\User;
use App\Support\Week;
use App\Support\WishList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishListTest extends TestCase
{
    use RefreshDatabase;

    private const THIS_WEEK = '2026-W42'; // 12.–18.10.2026

    private const NEXT_WEEK = '2026-W43'; // 19.–25.10.2026

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-14 18:00'); // Mittwoch, KW 42
    }

    // --- Wünschen ------------------------------------------------------------

    public function test_children_can_wish_a_recipe_from_next_week_on(): void
    {
        $child = User::factory()->child()->create(['name' => 'Lena']);
        $recipe = Recipe::factory()->create(['title' => 'Pfannkuchen']);

        $this->actingAs($child)
            ->post(route('wishes.store'), [
                'recipe_id' => $recipe->id,
                'week' => self::NEXT_WEEK,
                'preferred_day' => 5,
                'note' => 'Bitte mit Apfelmus',
            ])
            ->assertRedirect(route('wishes.index'));

        $wish = MealSuggestion::sole();
        $this->assertTrue($wish->user->is($child));
        $this->assertSame('Pfannkuchen', $wish->title);
        $this->assertSame('2026-10-19', $wish->week_start->toDateString());

        $this->get(route('wishes.index'))
            ->assertSeeInOrder(['Offene Wünsche', 'Pfannkuchen', '1×', 'Lena', 'ab KW 43', 'am liebsten Freitag', 'Bitte mit Apfelmus']);
    }

    public function test_next_week_is_the_default(): void
    {
        $this->actingAs(User::factory()->child()->create())
            ->post(route('wishes.store'), ['title' => 'Pizza bestellen'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-10-19', MealSuggestion::sole()->week_start->toDateString());
    }

    public function test_recipe_or_text_is_required_and_only_this_or_next_week(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('wishes.store'), ['preferred_day' => 2])
            ->assertSessionHasErrors(['recipe_id', 'title']);

        $this->post(route('wishes.store'), ['title' => 'Suppe', 'week' => '2026-W41'])->assertSessionHasErrors('week');
        $this->post(route('wishes.store'), ['title' => 'Suppe', 'week' => '2026-W44'])->assertSessionHasErrors('week');

        $this->assertDatabaseCount('meal_suggestions', 0);
    }

    public function test_a_dish_can_only_be_on_your_list_once_while_open(): void
    {
        $lena = User::factory()->child()->create();
        $recipe = Recipe::factory()->create();

        $this->actingAs($lena)->post(route('wishes.store'), ['recipe_id' => $recipe->id, 'week' => self::THIS_WEEK]);
        $this->post(route('wishes.store'), ['recipe_id' => $recipe->id, 'week' => self::NEXT_WEEK])
            ->assertSessionHasErrors(['recipe_id' => 'Das steht schon auf deiner Wunschliste.']);

        $this->post(route('wishes.store'), ['title' => 'Pizza bestellen']);
        $this->post(route('wishes.store'), ['title' => 'PIZZA BESTELLEN'])->assertSessionHasErrors('title');

        // Andere dürfen dasselbe wünschen
        $this->actingAs(User::factory()->child()->create())
            ->post(route('wishes.store'), ['recipe_id' => $recipe->id])
            ->assertSessionHasNoErrors();

        // Ist der Wunsch erfüllt, kann man es sich erneut wünschen
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-15']);
        $this->actingAs($lena)
            ->post(route('wishes.store'), ['recipe_id' => $recipe->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('meal_suggestions', 4);
    }

    // --- Offen, bis eingeplant ----------------------------------------------

    public function test_wishes_stay_open_across_weeks_until_planned(): void
    {
        $wish = MealSuggestion::factory()->forWeek(Week::fromIso('2026-W40'))->create(['title' => 'Spinat-Lasagne']);

        // Zwei Wochen später immer noch offen
        $this->assertTrue(WishList::upTo()->open()->contains($wish));

        $this->actingAs(User::factory()->create())
            ->get(route('meal-plan.edit', self::NEXT_WEEK))
            ->assertSee('Spinat-Lasagne')
            ->assertSee('seit KW 40');

        // Eingeplant → nicht mehr offen
        MealPlanEntry::factory()->create(['date' => '2026-10-22', 'title' => 'spinat-lasagne']);
        $list = WishList::upTo();
        $this->assertFalse($list->open()->contains($wish));
        $this->assertSame('2026-10-22', $list->fulfilledOn($wish)->toDateString());

        // Wieder ausgeplant → wieder offen
        MealPlanEntry::query()->delete();
        $this->assertTrue(WishList::upTo()->open()->contains($wish));
    }

    public function test_a_wish_from_next_week_is_not_fulfilled_by_this_week(): void
    {
        $recipe = Recipe::factory()->create();
        $wish = MealSuggestion::factory()->forRecipe($recipe)->forWeek(Week::fromIso(self::NEXT_WEEK))->create();
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-15']); // diese Woche

        $this->assertTrue(WishList::upTo()->open()->contains($wish));

        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-21']); // nächste Woche
        $this->assertFalse(WishList::upTo()->open()->contains($wish));
    }

    public function test_a_wish_made_after_the_dish_was_eaten_stays_open(): void
    {
        $recipe = Recipe::factory()->create();
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-12']); // Montag, schon gegessen

        // Mittwoch: „ab dieser Woche“ gewünscht – das Essen vom Montag zählt nicht
        $wish = MealSuggestion::factory()->forRecipe($recipe)->forWeek(Week::fromIso(self::THIS_WEEK))->create();
        $this->assertTrue(WishList::upTo()->open()->contains($wish));

        // Wird es heute oder später eingeplant, ist der Wunsch erfüllt
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-14']);
        $this->assertSame('2026-10-14', WishList::upTo()->fulfilledOn($wish)->toDateString());
    }

    public function test_planned_section_only_shows_today_and_later(): void
    {
        $past = Recipe::factory()->create(['title' => 'Gestern-Suppe']);
        $soon = Recipe::factory()->create(['title' => 'Morgen-Pizza']);
        MealSuggestion::factory()->forRecipe($past)->forWeek(Week::fromIso(self::THIS_WEEK))->create(['created_at' => '2026-10-12 08:00']);
        MealSuggestion::factory()->forRecipe($soon)->forWeek(Week::fromIso(self::THIS_WEEK))->create();
        MealPlanEntry::factory()->forRecipe($past)->create(['date' => '2026-10-13']);
        MealPlanEntry::factory()->forRecipe($soon)->create(['date' => '2026-10-15']);

        $this->actingAs(User::factory()->child()->create())
            ->get(route('wishes.index'))
            ->assertViewHas('plannedGroups', fn ($groups) => $groups->pluck('title')->all() === ['Morgen-Pizza'])
            ->assertViewHas('openGroups', fn ($groups) => $groups->isEmpty());
    }

    public function test_planned_wishes_show_up_as_planned(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Lasagne']);
        MealSuggestion::factory()->forRecipe($recipe)->for(User::factory()->child()->create(['name' => 'Lena']))->create();
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-23']);

        $this->actingAs(User::factory()->child()->create())
            ->get(route('wishes.index'))
            ->assertSeeInOrder(['Eingeplant 🎉', 'Lasagne', '✓ eingeplant: Fr 23.10.', 'Lena']);

        // In der Wochenansicht und beim Planen mit Häkchen
        $this->actingAs(User::factory()->create())
            ->get(route('meal-plan.edit', self::NEXT_WEEK))
            ->assertSee('✓ '.$recipe->emoji.' Lasagne', false);
    }

    public function test_planning_week_view_shows_open_and_this_weeks_wishes_only(): void
    {
        MealSuggestion::factory()->forWeek(Week::fromIso(self::THIS_WEEK))->create(['title' => 'Offen']);
        MealSuggestion::factory()->forWeek(Week::fromIso('2026-W44'))->create(['title' => 'Erst später']);
        $planned = MealSuggestion::factory()->forWeek(Week::fromIso(self::THIS_WEEK))->create(['title' => 'Schon dran', 'created_at' => '2026-10-12 08:00']);
        MealPlanEntry::factory()->create(['date' => '2026-10-13', 'title' => 'Schon dran']);

        $this->actingAs(User::factory()->create())
            ->get(route('meal-plan.show', self::NEXT_WEEK))
            ->assertSee('Offen')
            ->assertDontSee('Erst später')
            ->assertDontSee('Schon dran');

        $this->get(route('meal-plan.show', self::THIS_WEEK))
            ->assertSee('Schon dran')
            ->assertSee('✓ eingeplant: Di 13.10.');
    }

    // --- Zurückziehen ------------------------------------------------------

    public function test_own_open_wishes_can_be_withdrawn(): void
    {
        $child = User::factory()->child()->create();
        $own = MealSuggestion::factory()->for($child)->forWeek(Week::fromIso('2026-W40'))->create();
        $foreign = MealSuggestion::factory()->create();

        $this->actingAs($child);
        $this->delete(route('wishes.destroy', $foreign))->assertForbidden();
        $this->delete(route('wishes.destroy', $own))->assertRedirect(); // auch alte, noch offene Wünsche

        $this->assertModelMissing($own);
        $this->assertModelExists($foreign);
    }

    public function test_adults_can_remove_open_wishes_but_planned_ones_stay(): void
    {
        $recipe = Recipe::factory()->create();
        $open = MealSuggestion::factory()->create();
        $planned = MealSuggestion::factory()->forRecipe($recipe)->create();
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => '2026-10-20']);

        $this->actingAs(User::factory()->create());
        $this->delete(route('wishes.destroy', $open))->assertRedirect();
        $this->delete(route('wishes.destroy', $planned))->assertForbidden();

        $this->assertModelMissing($open);
        $this->assertModelExists($planned);
    }

    // --- Rezeptauswahl und Einstiege -----------------------------------------

    public function test_picker_knows_the_users_favorites(): void
    {
        $user = User::factory()->child()->create();
        $favorite = Recipe::factory()->create(['title' => 'Lieblingssuppe']);
        Recipe::factory()->create(['title' => 'Andere Suppe']);
        $user->favoriteRecipes()->attach($favorite);

        $this->actingAs($user)
            ->get(route('wishes.index'))
            ->assertOk()
            ->assertSee('x-data="recipePicker(', false)
            ->assertSee('Nur meine Favoriten')
            ->assertViewHas('recipes', fn ($recipes) => $recipes->firstWhere('title', 'Lieblingssuppe')['favorite'] === true
                && $recipes->firstWhere('title', 'Andere Suppe')['favorite'] === false);
    }

    public function test_recipe_page_offers_quick_wish(): void
    {
        $recipe = Recipe::factory()->complete()->create();

        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.show', $recipe))
            ->assertSee('⭐ Wünschen')
            ->assertSee('name="week" value="'.self::THIS_WEEK.'"', false)
            ->assertSee('name="week" value="'.self::NEXT_WEEK.'"', false);
    }

    public function test_admin_account_cannot_wish_but_sees_the_list(): void
    {
        $admin = User::factory()->admin()->create();
        $recipe = Recipe::factory()->complete()->create();
        MealSuggestion::factory()->create(['title' => 'Lasagne']);

        $this->actingAs($admin)
            ->post(route('wishes.store'), ['recipe_id' => $recipe->id])
            ->assertForbidden();
        $this->assertDatabaseMissing('meal_suggestions', ['user_id' => $admin->id]);

        $this->get(route('wishes.index'))
            ->assertOk()
            ->assertSee('Lasagne')
            ->assertDontSee('Etwas wünschen');
        $this->get(route('recipes.show', $recipe))->assertDontSee('⭐ Wünschen');
        $this->get(route('home'))->assertDontSee('Etwas wünschen');
        $this->get(route('meal-plan.show', Week::current()->next()))->assertDontSee('Etwas wünschen');
    }

    public function test_adults_can_still_wish(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('wishes.store'), ['title' => 'Lasagne'])
            ->assertRedirect(route('wishes.index'));

        $this->assertDatabaseHas('meal_suggestions', ['title' => 'Lasagne']);
    }

    public function test_wishes_are_in_the_navigation(): void
    {
        $this->actingAs(User::factory()->child()->create())
            ->get(route('home'))
            ->assertSee(route('wishes.index'))
            ->assertSee('Etwas wünschen');
    }
}
