<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert das Rezeptfoto aus der Datenbank aus.
 * Die URL enthält ?v=<Hash>, daher darf der Browser das Foto dauerhaft zwischenspeichern.
 */
class RecipeImageController extends Controller
{
    public function __invoke(Request $request, Recipe $recipe): Response
    {
        abort_if($recipe->image_hash === null, 404);

        $etag = '"'.$recipe->image_hash.'"';
        $headers = [
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'ETag' => $etag,
        ];

        // Hat der Browser genau dieses Foto schon, die Binärdaten gar nicht erst laden.
        if (in_array($etag, $request->getETags(), true)) {
            return response()->noContent(304, $headers);
        }

        $image = $recipe->image()->firstOrFail();

        return response($image->data, 200, $headers + [
            'Content-Type' => $image->mime_type,
            'Content-Length' => $image->size,
        ]);
    }
}
