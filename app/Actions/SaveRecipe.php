<?php

namespace App\Actions;

use App\Enums\RevisionAction;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Speichert ein Rezept inklusive Zutaten, Schritten, Tags und Bild
 * und hält die Änderung im Änderungsprotokoll fest.
 * Wird für Anlegen und Bearbeiten gleichermaßen verwendet.
 */
class SaveRecipe
{
    /**
     * @param  array<string, mixed>  $data  Validierte Daten aus StoreRecipeRequest
     * @param  User|null  $actor  Wer speichert; null bei Seeder und Import
     */
    public function handle(Recipe $recipe, array $data, ?UploadedFile $image = null, ?User $actor = null): Recipe
    {
        // Foto aus dem Import-Zwischenspeicher (liegt nur auf dieser Installation)
        $imported = $data['imported_image'] ?? null;
        $imported = $imported && Storage::disk('public')->exists($imported) ? $imported : null;

        DB::transaction(function () use ($recipe, $data, $image, $imported, $actor) {
            $isNew = ! $recipe->exists;
            $before = $isNew ? [] : $recipe->auditSnapshot();

            $recipe->fill(Arr::except($data, ['ingredients', 'steps', 'tags', 'image', 'remove_image', 'imported_image']))->save();

            // Fotos liegen in der Datenbank (recipe_images), damit jede Installation sie sieht.
            if ($image) {
                $recipe->storeImage($image->get(), $image->getMimeType());
            } elseif ($data['remove_image'] ?? false) {
                $recipe->removeImage();
            } elseif ($imported) {
                $recipe->storeImage(Storage::disk('public')->get($imported), Storage::disk('public')->mimeType($imported));
            }

            $recipe->ingredients()->delete();
            $recipe->ingredients()->createMany(
                collect($data['ingredients'])->values()->map(fn (array $row, int $i) => [
                    'position' => $i + 1,
                    'amount' => $row['amount'] ?? null,
                    'unit' => $row['unit'] ?? null,
                    'name' => $row['name'],
                    'is_optional' => $row['is_optional'] ?? false,
                ])->all()
            );

            $recipe->steps()->delete();
            $recipe->steps()->createMany(
                collect($data['steps'])->values()->map(fn (string $body, int $i) => [
                    'position' => $i + 1,
                    'body' => $body,
                ])->all()
            );

            $recipe->tags()->sync(Tag::idsFromNames($data['tags'] ?? []));

            if ($isNew) {
                $recipe->recordRevision(RevisionAction::Created, $actor);

                return;
            }

            // Speichern ohne echte Änderung erzeugt keinen Eintrag.
            $changes = RecipeRevision::diff($before, $recipe->auditSnapshot());

            if ($changes) {
                $recipe->recordRevision(RevisionAction::Updated, $actor, $changes);
            }
        });

        if ($imported) {
            Storage::disk('public')->delete($imported);
        }

        return $recipe;
    }
}
