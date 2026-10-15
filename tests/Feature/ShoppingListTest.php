<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\MealPlanEntry;
use App\Models\PantryItem;
use App\Models\Recipe;
use App\Models\User;
use App\Support\ShoppingList;
use App\Support\ShoppingListItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-14 18:00'); // Mittwoch, KW 42 (12.–18.10.)
    }

    public function test_only_adults_can_see_the_shopping_list(): void
    {
        $this->get(route('shopping-list.show'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->child()->create())
            ->get(route('shopping-list.show'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('shopping-list.show'))
            ->assertOk();
    }

    public function test_navigation_link_is_only_shown_to_adults(): void
    {
        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.index'))
            ->assertDontSee(route('shopping-list.show'));

        $this->actingAs(User::factory()->create())
            ->get(route('recipes.index'))
            ->assertSee(route('shopping-list.show'));
    }

    public function test_only_today_until_sunday_is_included(): void
    {
        $this->plan('2026-10-13', $this->recipe('Gestern-Suppe', [['1', 'Stück', 'Lauch']]));   // Dienstag: vorbei
        $this->plan('2026-10-14', $this->recipe('Heute-Curry', [['1', 'Dose', 'Kokosmilch']]));   // Mittwoch: heute
        $this->plan('2026-10-18', $this->recipe('Sonntagsbraten', [['1', 'kg', 'Rinderbraten']])); // Sonntag
        $this->plan('2026-10-19', $this->recipe('Nächste-Woche', [['1', 'Stück', 'Kürbis']]));    // Montag danach

        $list = ShoppingList::forRestOfWeek();

        $this->assertSame(['Kokosmilch', 'Rinderbraten'], $list->items->pluck('name')->all());
        $this->assertSame(['Heute-Curry', 'Sonntagsbraten'], $list->meals->map->displayTitle()->all());
        $this->assertSame('2026-10-14', $list->from->toDateString());
        $this->assertSame('2026-10-18', $list->to->toDateString());
    }

    public function test_same_ingredients_are_added_up(): void
    {
        $this->plan('2026-10-14', $this->recipe('Lasagne', [
            ['200', 'g', 'Feta'],
            ['500–600', 'g', 'Tomaten'],
            ['0,5', 'TL', 'Salz'],
            ['', '', 'Pfeffer'],
        ]));
        $this->plan('2026-10-16', $this->recipe('Salat', [
            ['300', 'g', 'feta'],          // gleiche Zutat, andere Schreibweise
            ['200', 'g', 'Tomaten'],
            ['1', 'TL', 'Salz'],
            ['1', 'EL', 'Salz'],           // andere Einheit → eigene Zeile
            ['', '', 'Pfeffer'],
            ['eine Prise', '', 'Zucker'],  // keine Zahl → bleibt Text
        ]));

        $items = ShoppingList::forRestOfWeek()->items->keyBy(fn (ShoppingListItem $i) => $i->name.'|'.$i->unit);

        $this->assertSame('500 g', $items['Feta|g']->amountLabel());
        $this->assertSame('700–800 g', $items['Tomaten|g']->amountLabel());
        $this->assertSame('1,5 TL', $items['Salz|TL']->amountLabel());
        $this->assertSame('1 EL', $items['Salz|EL']->amountLabel());
        $this->assertSame('', $items['Pfeffer|']->amountLabel());
        $this->assertSame('eine Prise', $items['Zucker|']->amountLabel());
        $this->assertSame('Lasagne (Mi), Salat (Fr)', $items['Feta|g']->sourceLabel());
        $this->assertCount(6, $items); // Feta, Tomaten, Salz (TL), Salz (EL), Pfeffer, Zucker
    }

    public function test_a_recipe_planned_twice_counts_twice(): void
    {
        $recipe = $this->recipe('Pfannkuchen', [['250', 'ml', 'Milch']]);
        $this->plan('2026-10-15', $recipe);
        $this->plan('2026-10-17', $recipe);

        $item = ShoppingList::forRestOfWeek()->items->sole();

        $this->assertSame('500 ml', $item->amountLabel());
        $this->assertSame('Pfannkuchen (Do, Sa)', $item->sourceLabel());
    }

    public function test_optional_only_if_optional_everywhere(): void
    {
        $this->plan('2026-10-14', $this->recipe('A', [['1', 'Stück', 'Zitrone', true], ['50', 'g', 'Parmesan', true]]));
        $this->plan('2026-10-15', $this->recipe('B', [['1', 'Stück', 'Zitrone', false]]));

        $items = ShoppingList::forRestOfWeek()->items->keyBy('name');

        $this->assertFalse($items['Zitrone']->optional);
        $this->assertSame('2 Stück', $items['Zitrone']->amountLabel());
        $this->assertTrue($items['Parmesan']->optional);
    }

    public function test_page_lists_ingredients_meals_and_free_text_dishes(): void
    {
        $this->plan('2026-10-14', $this->recipe('Linsensuppe', [['200', 'g', 'Rote Linsen'], ['1', 'Stück', 'Petersilie', true]]));
        MealPlanEntry::factory()->create(['date' => '2026-10-16', 'title' => 'Pizza bestellen']);

        $this->actingAs(User::factory()->create())
            ->get(route('shopping-list.show'))
            ->assertOk()
            ->assertSee('Für Mittwoch, 14.10. bis Sonntag, 18.10.')
            ->assertSeeInOrder(['200 g', 'Rote Linsen', 'für Linsensuppe (Mi)', 'Optional', 'Petersilie'])
            ->assertSeeInOrder(['Berücksichtigte Gerichte', 'Linsensuppe', 'Pizza bestellen', 'kein Rezept – keine Zutaten']);
    }

    public function test_export_contains_compact_lines_sections_and_meals(): void
    {
        $this->plan('2026-10-14', $this->recipe('Linsensuppe', [['200', 'g', 'Rote Linsen'], ['', '', 'Salz'], ['1', 'Stück', 'Zitrone', true]]));
        MealPlanEntry::factory()->create(['date' => '2026-10-16', 'title' => 'Pizza bestellen']);

        $export = ShoppingList::forRestOfWeek()->export();

        $this->assertSame('Einkaufsliste KW 42', $export['title']);
        $this->assertSame('Mi 14.10. – So 18.10.', $export['period']);
        $this->assertSame('einkaufsliste-kw42.txt', $export['filename']);
        $this->assertSame('Gerichte: Mi Linsensuppe, Fr Pizza bestellen', $export['meals']);

        $this->assertSame([
            ['heading' => null, 'items' => [
                ['id' => 'i0', 'line' => '200 g Rote Linsen'],
                ['id' => 'i1', 'line' => 'Salz'],
            ]],
            ['heading' => 'Optional', 'items' => [
                ['id' => 'i2', 'line' => '1 Stück Zitrone'],
            ]],
        ], $export['sections']);
    }

    public function test_checkbox_ids_match_the_export(): void
    {
        $this->plan('2026-10-14', $this->recipe('Linsensuppe', [['200', 'g', 'Rote Linsen'], ['1', 'Stück', 'Zitrone', true]]));

        $this->actingAs(User::factory()->create())
            ->get(route('shopping-list.show'))
            ->assertSee('x-data="shoppingList(', false)
            ->assertSee('value="i0" x-model="done"', false)
            ->assertSee('value="i1" x-model="done"', false)
            ->assertSee('Teilen');
    }

    public function test_share_button_is_hidden_when_there_is_nothing_to_buy(): void
    {
        MealPlanEntry::factory()->create(['date' => '2026-10-16', 'title' => 'Pizza bestellen']);

        $this->actingAs(User::factory()->create())
            ->get(route('shopping-list.show'))
            ->assertSee('keine Zutaten einzukaufen')
            ->assertDontSee('Per WhatsApp senden');
    }

    public function test_pantry_items_are_listed_separately_and_not_shared(): void
    {
        $this->plan('2026-10-14', $this->recipe('Linsensuppe', [['200', 'g', 'Rote Linsen'], ['1', 'TL', 'Salz'], ['', '', 'Pfeffer']]));
        PantryItem::create(['name' => 'salz']); // Groß-/Kleinschreibung egal

        $list = ShoppingList::forRestOfWeek();

        $this->assertSame(['Pfeffer', 'Rote Linsen'], $list->itemsToBuy()->pluck('name')->values()->all());
        $this->assertSame(['Salz'], $list->pantryItems()->pluck('name')->values()->all());
        $this->assertStringNotContainsString('Salz', json_encode($list->export()));

        $this->actingAs(User::factory()->create())
            ->get(route('shopping-list.show'))
            ->assertSee('0</span> von 2 abgehakt', false)
            ->assertSeeInOrder(['Haben wir immer da (1)', 'Salz', 'doch einkaufen']);
    }

    public function test_adults_can_mark_and_unmark_pantry_items(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('pantry-items.store'), ['name' => ' Olivenöl '])->assertRedirect();
        $this->post(route('pantry-items.store'), ['name' => 'OLIVENÖL'])->assertRedirect(); // kein Duplikat

        $item = PantryItem::sole();
        $this->assertSame('Olivenöl', $item->name);
        $this->assertSame('olivenöl', $item->key);

        $this->delete(route('pantry-items.destroy', $item))->assertRedirect();
        $this->assertModelMissing($item);
    }

    public function test_children_cannot_change_pantry_items(): void
    {
        $item = PantryItem::create(['name' => 'Salz']);

        $this->actingAs(User::factory()->child()->create());
        $this->post(route('pantry-items.store'), ['name' => 'Zucker'])->assertForbidden();
        $this->delete(route('pantry-items.destroy', $item))->assertForbidden();

        $this->assertDatabaseCount('pantry_items', 1);
    }

    public function test_empty_state_when_nothing_is_planned(): void
    {
        MealPlanEntry::factory()->create(['date' => '2026-10-12', 'title' => 'Schon gegessen']);

        $this->actingAs(User::factory()->create())
            ->get(route('shopping-list.show'))
            ->assertSee('Nichts einzukaufen')
            ->assertSee(route('meal-plan.edit', '2026-W42'));
    }

    public function test_on_sunday_only_sunday_counts(): void
    {
        $this->travelTo('2026-10-18 09:00');
        $this->plan('2026-10-17', $this->recipe('Samstag', [['1', 'Stück', 'Brot']]));
        $this->plan('2026-10-18', $this->recipe('Sonntag', [['1', 'Stück', 'Kuchen']]));

        $list = ShoppingList::forRestOfWeek();

        $this->assertSame(['Kuchen'], $list->items->pluck('name')->all());
        $this->assertTrue($list->from->isSameDay($list->to));
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3?: bool}>  $ingredients  [Menge, Einheit, Name, optional]
     */
    private function recipe(string $title, array $ingredients): Recipe
    {
        $recipe = Recipe::factory()->create(['title' => $title]);

        foreach ($ingredients as $i => [$amount, $unit, $name]) {
            Ingredient::factory()->for($recipe)->create([
                'position' => $i + 1,
                'amount' => $amount ?: null,
                'unit' => $unit ?: null,
                'name' => $name,
                'is_optional' => $ingredients[$i][3] ?? false,
            ]);
        }

        return $recipe;
    }

    private function plan(string $date, Recipe $recipe): void
    {
        MealPlanEntry::factory()->forRecipe($recipe)->create(['date' => $date]);
    }
}
