<?php

namespace App\Actions;

use App\Enums\RevisionAction;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Löscht ein Rezept samt Foto und hält das im Änderungsprotokoll fest.
 */
class DeleteRecipe
{
    public function handle(Recipe $recipe, ?User $actor = null): void
    {
        DB::transaction(function () use ($recipe, $actor) {
            // Der Eintrag bleibt erhalten; recipe_id wird beim Löschen auf null gesetzt.
            $recipe->recordRevision(RevisionAction::Deleted, $actor);
            $recipe->delete();
        });

        if ($recipe->image_path) {
            Storage::disk('public')->delete($recipe->image_path);
        }
    }
}
