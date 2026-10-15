<?php

namespace Database\Factories;

use App\Models\MealPlanEntry;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlanEntry>
 */
class MealPlanEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'recipe_id' => null,
            'title' => fake()->words(2, true),
            'planned_by' => null,
        ];
    }

    public function forRecipe(Recipe $recipe): static
    {
        return $this->state(['recipe_id' => $recipe->id, 'title' => $recipe->title]);
    }
}
