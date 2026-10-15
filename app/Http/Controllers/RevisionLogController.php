<?php

namespace App\Http\Controllers;

use App\Models\RecipeRevision;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Gesamtes Änderungsprotokoll aller Rezepte (inkl. gelöschter) für das Familienoberhaupt.
 */
class RevisionLogController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('view-revision-log');

        return view('revisions.index', [
            'revisions' => RecipeRevision::query()->with('recipe')->latest('id')->paginate(30),
        ]);
    }
}
