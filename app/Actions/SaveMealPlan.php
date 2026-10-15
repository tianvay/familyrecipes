<?php

namespace App\Actions;

use App\Models\MealPlanEntry;
use App\Models\User;
use App\Support\Week;
use Illuminate\Support\Facades\DB;

/**
 * Speichert den Plan einer Woche. Unveränderte Tage bleiben unangetastet,
 * damit „geplant von“ und Zeitstempel nur bei echten Änderungen wechseln.
 */
class SaveMealPlan
{
    /**
     * @param  array<int, array{recipe_id: int|null, title: string}|null>  $entries  Tag (1–7) => Gericht oder null
     */
    public function handle(Week $week, array $entries, User $planner): void
    {
        DB::transaction(function () use ($week, $entries, $planner) {
            $existing = MealPlanEntry::query()->forWeek($week)->get()->keyBy(fn (MealPlanEntry $e) => $e->date->toDateString());

            foreach ($week->days() as $day => $date) {
                $entry = $existing->get($date->toDateString());
                $wanted = $entries[$day] ?? null;

                if ($wanted === null) {
                    $entry?->delete();

                    continue;
                }

                $entry ??= new MealPlanEntry(['date' => $date]);

                if ($entry->exists && $entry->recipe_id === $wanted['recipe_id'] && $entry->title === $wanted['title']) {
                    continue;
                }

                $entry->fill([...$wanted, 'planned_by' => $planner->id])->save();
            }
        });
    }
}
