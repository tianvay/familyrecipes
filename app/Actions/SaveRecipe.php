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
        $oldImage = $recipe->image_path;

        $imported = $data['imported_image'] ?? null;

        if ($image) {
            $data['image_path'] = $image->store('recipes', 'public');
        } elseif ($data['remove_image'] ?? false) {
            $data['image_path'] = null;
        } elseif ($imported && Storage::disk('public')->exists($imported)) {
            // Foto aus dem Import-Zwischenspeicher übernehmen
            $data['image_path'] = 'recipes/'.basename($imported);
            Storage::disk('public')->move($imported, $data['image_path']);
        }

        DB::transaction(function () use ($recipe, $data, $actor) {
            $isNew = ! $recipe->exists;
            $before = $isNew ? [] : $recipe->auditSnapshot();

            $recipe->fill(Arr::except($data, ['ingredients', 'steps', 'tags', 'image', 'remove_image', 'imported_image']))->save();

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

        if ($oldImage && $oldImage !== $recipe->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return $recipe;
    }
}
