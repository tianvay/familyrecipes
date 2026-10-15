<?php

namespace App\Policies;

use App\Models\MealSuggestion;
use App\Models\User;
use App\Support\Week;

/**
 * Vorschlagen darf jedes Familienmitglied – aber nur für offene Wochen
 * (diese und nächste). Vergangene Wochen bleiben als Historie unverändert.
 */
class MealSuggestionPolicy
{
    public function create(User $user, Week $week): bool
    {
        return $week->isOpen();
    }

    /**
     * Zurückziehen darf, wer vorgeschlagen hat – oder wer den Plan festlegt.
     */
    public function delete(User $user, MealSuggestion $suggestion): bool
    {
        return $suggestion->week()->isOpen()
            && ($suggestion->user_id === $user->id || $user->role->canPlanMeals());
    }
}
