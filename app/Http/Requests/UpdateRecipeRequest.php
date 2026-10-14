<?php

namespace App\Http\Requests;

/**
 * Gleiche Regeln wie beim Anlegen – eigene Klasse, damit sich die
 * Autorisierung später (z. B. per Policy) getrennt anpassen lässt.
 */
class UpdateRecipeRequest extends StoreRecipeRequest
{
    //
}
