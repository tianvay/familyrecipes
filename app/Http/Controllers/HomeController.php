<?php

namespace App\Http\Controllers;

use App\Models\MealPlanEntry;
use App\Models\MealSuggestion;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Startseite: Was gibt es heute und morgen? Gedacht vor allem fürs Handy.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = CarbonImmutable::today();
        $nextWeek = Week::current()->next();

        $upcoming = MealPlanEntry::query()
            ->betweenDates($today, $today->addDays(6))
            ->with('recipe:id,title,slug,summary,emoji,color,image_path,prep_minutes,cook_minutes')
            ->get()
            ->keyBy(fn (MealPlanEntry $entry) => $entry->date->toDateString());

        return view('home', [
            'greeting' => $this->greeting(),
            'today' => $today,
            'upcoming' => $upcoming,
            'nextWeek' => $nextWeek,
            'nextWeekPlanned' => MealPlanEntry::query()->forWeek($nextWeek)->count(),
            'nextWeekSuggestions' => MealSuggestion::query()->forWeek($nextWeek)->count(),
            'ownSuggestionsNextWeek' => $request->user()->mealSuggestions()->forWeek($nextWeek)->count(),
        ]);
    }

    private function greeting(): string
    {
        return match (true) {
            now()->hour < 11 => 'Guten Morgen',
            now()->hour < 17 => 'Hallo',
            default => 'Guten Abend',
        };
    }
}
