<?php

namespace App\Http\Requests;

/**
 * Gleiche Regeln wie beim Anlegen, aber eigene Autorisierung.
 */
class UpdateRecipeRequest extends StoreRecipeRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('recipe'));
    }
}
