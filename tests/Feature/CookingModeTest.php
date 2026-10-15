<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Step;
use App\Models\User;
use App\Support\CookingSteps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookingModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_everyone_including_children_can_cook(): void
    {
        $recipe = $this->recipe();

        $this->get(route('recipes.cook', $recipe))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.cook', $recipe))
            ->assertOk()
            ->assertSee('x-data="cookingMode(', false)
            ->assertViewHas('steps', fn (array $steps) => $steps[0]['text'] === 'Zwiebel würfeln.' && count($steps) === 2)
            ->assertSee('von 2')
            ->assertSee('Guten Appetit!');
    }

    public function test_recipe_page_links_to_cooking_mode(): void
    {
        $recipe = $this->recipe();

        $this->actingAs(User::factory()->child()->create())
            ->get(route('recipes.show', $recipe))
            ->assertSee(route('recipes.cook', $recipe));
    }

    public function test_steps_know_their_ingredients_and_timers(): void
    {
        $steps = CookingSteps::for($this->recipe());

        $this->assertSame(['1 Stück Rote Zwiebel'], $steps[0]['ingredients']);
        $this->assertNull($steps[0]['timer']);

        $this->assertSame(['1 Stück Rote Zwiebel', '200 g Hähnchenbrustfilet', 'Salz'], $steps[1]['ingredients']);
        $this->assertSame(['seconds' => 900, 'label' => '15 Min.'], $steps[1]['timer']);
    }

    public function test_ingredient_matching(): void
    {
        $ingredients = collect([
            new Ingredient(['name' => 'Rote Zwiebel', 'amount' => '1', 'unit' => 'Stück']),
            new Ingredient(['name' => 'Knoblauchpulver']),
            new Ingredient(['name' => 'Vegane Sahne', 'amount' => '200', 'unit' => 'ml']),
            new Ingredient(['name' => 'Veganer Reibekäse']),
            new Ingredient(['name' => 'Ei', 'amount' => '1']),
        ]);

        // Plural im Text, Wortanfang des Namens im Text („Knoblauch“ → „Knoblauchpulver“)
        $this->assertSame(['1 Stück Rote Zwiebel', 'Knoblauchpulver'], CookingSteps::ingredientsIn('Zwiebeln und Knoblauch anbraten.', $ingredients));

        // Eigenschaftswörter wie „vegan“ verbinden keine Zutaten
        $this->assertSame(['Veganer Reibekäse'], CookingSteps::ingredientsIn('Veganen Reibekäse darüber streuen.', $ingredients));

        // „Sahnemischung“ gehört zur Sahne
        $this->assertSame(['200 ml Vegane Sahne'], CookingSteps::ingredientsIn('Mit der Sahnemischung übergießen.', $ingredients));

        // Sehr kurze Namen werden nicht geraten
        $this->assertSame([], CookingSteps::ingredientsIn('Mit verquirltem Ei bestreichen.', $ingredients));
    }

    public function test_timer_detection(): void
    {
        $this->assertSame(['seconds' => 900, 'label' => '15 Min.'], CookingSteps::timerIn('Bei 200 °C für 12–15 Minuten garen.'));
        $this->assertSame(['seconds' => 480, 'label' => '8 Min.'], CookingSteps::timerIn('Nach etwa 8 Minuten wenden.'));
        $this->assertSame(['seconds' => 3600, 'label' => '1 Std.'], CookingSteps::timerIn('40 bis 60 Minuten backen.'));
        $this->assertSame(['seconds' => 5400, 'label' => '90 Min.'], CookingSteps::timerIn('1,5 Stunden schmoren.'));
        $this->assertSame(['seconds' => 300, 'label' => '5 Min.'], CookingSteps::timerIn('5 Min. ruhen lassen.'));
        $this->assertNull(CookingSteps::timerIn('Bei 200 °C backen.'));
        $this->assertNull(CookingSteps::timerIn('Einige Stunden kalt stellen.'));
    }

    private function recipe(): Recipe
    {
        $recipe = Recipe::factory()->create(['title' => 'Hähnchenpfanne']);

        foreach ([['1', 'Stück', 'Rote Zwiebel'], ['200', 'g', 'Hähnchenbrustfilet'], [null, null, 'Salz']] as $i => [$amount, $unit, $name]) {
            Ingredient::factory()->for($recipe)->create(['position' => $i + 1, 'amount' => $amount, 'unit' => $unit, 'name' => $name]);
        }

        Step::factory()->for($recipe)->create(['position' => 1, 'body' => 'Zwiebel würfeln.']);
        Step::factory()->for($recipe)->create(['position' => 2, 'body' => 'Hähnchen mit der Zwiebel 15 Minuten braten, salzen.']);

        return $recipe;
    }
}
