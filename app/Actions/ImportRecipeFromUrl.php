<?php

namespace App\Actions;

use App\Support\RecipeImport\JsonLdRecipeParser;
use App\Support\RecipeImport\UrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Holt ein Rezept von einer Webseite und bereitet es als Eingabe für das
 * Rezeptformular auf. Gespeichert wird erst, wenn man das Formular abschickt.
 */
class ImportRecipeFromUrl
{
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private UrlGuard $guard) {}

    /**
     * @return array<string, mixed> Formulareingaben (wie old()) für recipes.create
     *
     * @throws RuntimeException mit einer Meldung für die Nutzerin/den Nutzer
     */
    public function handle(string $url): array
    {
        $html = $this->fetch($url, accept: 'text/html');
        $recipe = JsonLdRecipeParser::parse($html);

        if ($recipe === null) {
            throw new RuntimeException('Auf dieser Seite wurde kein Rezept gefunden. Nicht jede Seite liefert ihre Rezepte maschinenlesbar mit – dann bitte von Hand eintragen.');
        }

        if ($recipe['ingredients'] === [] || $recipe['steps'] === []) {
            throw new RuntimeException('Das Rezept auf dieser Seite ist unvollständig (Zutaten oder Zubereitung fehlen) und kann nicht übernommen werden.');
        }

        $this->deleteOldImports();

        return [
            'title' => $recipe['title'],
            'summary' => $recipe['description'] ? Str::limit($recipe['description'], 250) : null,
            'description' => $recipe['description'],
            'servings' => $recipe['servings'],
            'prep_minutes' => $recipe['prep_minutes'],
            'cook_minutes' => $recipe['cook_minutes'],
            'calories' => $recipe['calories'],
            'protein' => $recipe['protein'],
            'source' => Str::limit($url, 250, ''),
            'tags' => implode(', ', $recipe['tags']),
            'ingredients' => array_map(fn (array $ingredient) => [...$ingredient, 'is_optional' => false], $recipe['ingredients']),
            'steps' => $recipe['steps'],
            'imported_image' => $recipe['image_url'] ? $this->downloadImage($recipe['image_url']) : null,
        ];
    }

    private function fetch(string $url, string $accept): string
    {
        try {
            $this->guard->assertPublic($url);

            $response = $this->client()->accept($accept)->get($url);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Die Seite konnte nicht geladen werden. Stimmt die Adresse und hat der Rezeptbuch-Rechner Internet?', previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException("Die Seite hat mit einem Fehler geantwortet ({$response->status()}).");
        }

        return $response->body();
    }

    /**
     * Lädt das Rezeptfoto in storage/app/public/imports. Fehler sind nicht schlimm –
     * dann wird das Rezept eben ohne Foto übernommen.
     */
    private function downloadImage(string $url): ?string
    {
        try {
            $this->guard->assertPublic($url);
            $response = $this->client()->accept('image/*')->get($url);
        } catch (InvalidArgumentException|ConnectionException) {
            return null;
        }

        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extension = self::IMAGE_TYPES[$type] ?? null;

        if ($response->failed() || $extension === null || strlen($response->body()) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $path = 'imports/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    private function client(): PendingRequest
    {
        return Http::timeout(15)
            ->withUserAgent('Mozilla/5.0 (compatible; Familien-Rezeptbuch)')
            ->withOptions([
                // Auch Weiterleitungen dürfen nicht ins Heimnetz führen
                'allow_redirects' => [
                    'max' => 5,
                    'on_redirect' => fn ($request, $response, $uri) => $this->guard->assertPublic((string) $uri),
                ],
            ]);
    }

    /**
     * Importierte, aber nie gespeicherte Fotos nach einem Tag aufräumen.
     */
    private function deleteOldImports(): void
    {
        $disk = Storage::disk('public');

        foreach ($disk->files('imports') as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
