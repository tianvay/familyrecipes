<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'position' => 1,
            'amount' => (string) fake()->numberBetween(1, 500),
            'unit' => fake()->randomElement(['g', 'ml', 'EL', 'TL', 'Stück']),
            'name' => fake()->word(),
            'is_optional' => false,
        ];
    }
}
