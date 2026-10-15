<?php

namespace App\Http\Controllers;

use App\Models\PantryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * „Haben wir immer da“ auf der Einkaufsliste an- und abwählen.
 */
class PantryItemController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('view-shopping-list');

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        // key wird im Model aus dem Namen berechnet, daher kein firstOrCreate(['key' => …])
        $item = PantryItem::query()->firstWhere('key', PantryItem::keyFor($validated['name']))
            ?? PantryItem::create(['name' => trim($validated['name']), 'added_by' => $request->user()->id]);

        return back()->with('status', "„{$item->name}“ steht ab jetzt nicht mehr auf der Einkaufsliste.");
    }

    public function destroy(PantryItem $pantryItem): RedirectResponse
    {
        Gate::authorize('view-shopping-list');

        $pantryItem->delete();

        return back()->with('status', "„{$pantryItem->name}“ wird wieder eingekauft.");
    }
}
