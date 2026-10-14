<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Step;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'emoji' => fake()->randomElement(['🍝', '🥗', '🍲', '🥘', '🍰']),
            'color' => fake()->hexColor(),
            'servings' => fake()->numberBetween(1, 6),
            'prep_minutes' => fake()->numberBetween(5, 30),
            'cook_minutes' => fake()->numberBetween(0, 60),
            'difficulty' => fake()->randomElement(Difficulty::cases()),
            'calories' => fake()->numberBetween(150, 900),
            'protein' => fake()->numberBetween(5, 50),
            'tips' => [fake()->sentence()],
            'is_favorite' => false,
        ];
    }

    public function favorite(): static
    {
        return $this->state(['is_favorite' => true]);
    }

    /**
     * Rezept mit Zutaten und Zubereitungsschritten.
     */
    public function complete(int $ingredients = 4, int $steps = 3): static
    {
        return $this
            ->has(Ingredient::factory()->count($ingredients)->sequence(fn ($s) => ['position' => $s->index + 1]))
            ->has(Step::factory()->count($steps)->sequence(fn ($s) => ['position' => $s->index + 1]));
    }
}
