<?php

namespace App\Http\Controllers;

use App\Actions\SaveMealPlan;
use App\Http\Requests\UpdateMealPlanRequest;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Support\LastEaten;
use App\Support\Week;
use App\Support\WishList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Wochen-Essensplan: ansehen darf jeder, festlegen nur Erwachsene.
 */
class MealPlanController extends Controller
{
    public function show(?Week $week = null): View
    {
        $week ??= Week::current();

        return view('meal-plan.show', [
            'week' => $week,
            'entries' => $this->entriesByDay($week),
            'wishGroups' => $this->wishGroups($week),
        ]);
    }

    public function edit(Week $week): View
    {
        Gate::authorize('plan-meals', $week);

        $recipes = Recipe::query()->orderBy('title')->get(['id', 'title', 'emoji']);
        $lastEaten = LastEaten::dates();

        // Bis zu fünf Rezepte, die es seit mindestens drei Wochen (oder noch nie) gab – die ältesten zuerst
        $longAgo = $recipes
            ->filter(fn (Recipe $recipe) => ! $lastEaten->has($recipe->id)
                || $lastEaten[$recipe->id]->lessThanOrEqualTo(today()->subWeeks(3)))
            ->sortBy(fn (Recipe $recipe) => $lastEaten->get($recipe->id)?->timestamp ?? 0)
            ->take(5)
            ->values();

        return view('meal-plan.edit', [
            'week' => $week,
            'entries' => $this->entriesByDay($week),
            'wishGroups' => $this->wishGroups($week),
            'recipes' => $recipes,
            'lastEaten' => $lastEaten,
            'longAgo' => $longAgo,
        ]);
    }

    public function update(UpdateMealPlanRequest $request, Week $week, SaveMealPlan $saveMealPlan): RedirectResponse
    {
        $saveMealPlan->handle($week, $request->entries(), $request->user());

        return to_route('meal-plan.show', $week)->with('status', 'Essensplan wurde gespeichert.');
    }

    /**
     * @return Collection<int, MealPlanEntry> Schlüssel 1 (Montag) bis 7 (Sonntag)
     */
    private function entriesByDay(Week $week): Collection
    {
        return MealPlanEntry::query()
            ->forWeek($week)
            ->with(['recipe:id,title,slug,emoji,color', 'plannedBy:id,name'])
            ->get()
            ->keyBy(fn (MealPlanEntry $entry) => $entry->date->dayOfWeekIso);
    }

    /**
     * Wünsche, die für diese Woche gelten: noch offen oder in dieser Woche eingeplant.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function wishGroups(Week $week): Collection
    {
        $wishList = WishList::upTo($week);

        return $wishList->group($wishList->relevantFor($week));
    }
}
