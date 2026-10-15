<?php

namespace Database\Factories;

use App\Models\MealSuggestion;
use App\Models\Recipe;
use App\Models\User;
use App\Support\Week;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealSuggestion>
 */
class MealSuggestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'week_start' => Week::current()->next()->start,
            'user_id' => User::factory(),
            'recipe_id' => null,
            'title' => fake()->words(2, true),
            'preferred_day' => null,
            'note' => null,
        ];
    }

    public function forWeek(Week $week): static
    {
        return $this->state(['week_start' => $week->start]);
    }

    public function forRecipe(Recipe $recipe): static
    {
        return $this->state(['recipe_id' => $recipe->id, 'title' => $recipe->title]);
    }
}
