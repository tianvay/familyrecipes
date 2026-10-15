<?php

namespace App\Http\Controllers;

use App\Support\ShoppingList;
use App\Support\Week;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Einkaufsliste für den Rest der aktuellen Woche, erzeugt aus dem Essensplan.
 */
class ShoppingListController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('view-shopping-list');

        return view('shopping-list.show', [
            'list' => ShoppingList::forRestOfWeek(),
            'week' => Week::current(),
        ]);
    }
}
