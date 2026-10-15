<?php

namespace App\Policies;

use App\Models\MealSuggestion;
use App\Models\User;
use App\Support\WishList;

/**
 * Wünsche (MealSuggestion): wünschen darf jedes Familienmitglied (siehe StoreWishRequest).
 */
class MealSuggestionPolicy
{
    /**
     * Zurückziehen darf, wer sich etwas gewünscht hat – oder wer den Plan festlegt.
     * Eingeplante (erfüllte) Wünsche bleiben als Historie erhalten.
     */
    public function delete(User $user, MealSuggestion $suggestion): bool
    {
        return ($suggestion->user_id === $user->id || $user->role->canPlanMeals())
            && ! WishList::isFulfilled($suggestion);
    }
}
