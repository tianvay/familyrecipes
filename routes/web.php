<?php

use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ToggleFavoriteController;
use Illuminate\Support\Facades\Route;

// to_route() statt Route::redirect(), damit ein Unterpfad (XAMPP: /rezeptbuch) erhalten bleibt
Route::get('/', fn () => to_route('recipes.index'));

Route::resource('rezepte', RecipeController::class)
    ->names('recipes')
    ->parameters(['rezepte' => 'recipe']);

Route::patch('rezepte/{recipe}/favorit', ToggleFavoriteController::class)->name('recipes.favorite');
