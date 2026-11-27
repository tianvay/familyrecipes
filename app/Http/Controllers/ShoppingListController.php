<?php

namespace App\Http\Controllers;

use App\Models\MealPlanEntry;
use App\Support\ShoppingList;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Einkaufsliste aus dem Essensplan für frei wählbare Tage (?von=2026-10-14&bis=2026-10-16).
 * Ohne Auswahl: Rest der aktuellen Woche. Wählbar sind heute bis Sonntag der nächsten Woche.
 */
class ShoppingListController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('view-shopping-list');

        $days = ShoppingList::selectableDays();
        $today = $days->first();
        $restOfWeek = [$today, Week::containing($today)->end()->startOfDay()];
        $nextWeek = [Week::containing($today)->next()->start, $days->last()];

        $from = $this->pickDay($request->query('von'), $days) ?? $restOfWeek[0];
        $to = $this->pickDay($request->query('bis'), $days) ?? Week::containing($from)->end()->startOfDay();

        $list = ShoppingList::forDays($from, $to);

        return view('shopping-list.show', [
            'list' => $list,
            'week' => Week::containing($list->from),
            'days' => $days,
            'mealsByDay' => $this->mealsByDay($days),
            'presets' => [
                ['label' => 'Rest dieser Woche', 'range' => $restOfWeek],
                ['label' => 'Nächste Woche', 'range' => $nextWeek],
            ],
        ]);
    }

    /**
     * Nur Tage aus der Auswahlliste; alles andere (Tippfehler, alte Links) wird ignoriert.
     *
     * @param  Collection<int, CarbonImmutable>  $days
     */
    private function pickDay(mixed $value, Collection $days): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        return $days->first(fn (CarbonImmutable $day) => $day->toDateString() === $value);
    }

    /**
     * Gerichte je Tag für die Auswahl („Do 15.10. · Lasagne“).
     *
     * @param  Collection<int, CarbonImmutable>  $days
     * @return Collection<string, string>
     */
    private function mealsByDay(Collection $days): Collection
    {
        return MealPlanEntry::query()
            ->betweenDates($days->first(), $days->last())
            ->with('recipe')
            ->get()
            ->mapWithKeys(fn (MealPlanEntry $meal) => [$meal->date->toDateString() => $meal->displayTitle()]);
    }
}
