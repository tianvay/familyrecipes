<?php

namespace App\Actions;

use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Speichert ein Rezept inklusive Zutaten, Schritten, Tags und Bild.
 * Wird für Anlegen und Bearbeiten gleichermaßen verwendet.
 */
class SaveRecipe
{
    /**
     * @param  array<string, mixed>  $data  Validierte Daten aus StoreRecipeRequest
     */
    public function handle(Recipe $recipe, array $data, ?UploadedFile $image = null): Recipe
    {
        $oldImage = $recipe->image_path;

        if ($image) {
            $data['image_path'] = $image->store('recipes', 'public');
        } elseif ($data['remove_image'] ?? false) {
            $data['image_path'] = null;
        }

        DB::transaction(function () use ($recipe, $data) {
            $recipe->fill(Arr::except($data, ['ingredients', 'steps', 'tags', 'image', 'remove_image']))->save();

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
        });

        if ($oldImage && $oldImage !== $recipe->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return $recipe;
    }
}
