<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

/**
 * Ansehen und Favoriten markieren darf jedes Familienmitglied,
 * Ändern nur Erwachsene und das Familienoberhaupt.
 */
class RecipePolicy
{
    public function create(User $user): bool
    {
        return $user->role->canEditRecipes();
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $user->role->canEditRecipes();
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $user->role->canEditRecipes();
    }
}
