<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CookingModeController;
use App\Http\Controllers\FamilyFavoritesController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\PantryItemController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeHistoryController;
use App\Http\Controllers\RecipeImageController;
use App\Http\Controllers\RecipeImportController;
use App\Http\Controllers\RevisionLogController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\ToggleFavoriteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WishController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

// auth.session beendet Sitzungen, sobald sich das Passwort des Accounts ändert
// (z. B. wenn das Familienoberhaupt ein Passwort zurücksetzt).
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Startseite: „Heute gibt's …“
    Route::get('/', HomeController::class)->name('home');

    // Import per Link – vor der Resource-Route, sonst würde „importieren“ als Rezept-Slug gelesen.
    Route::get('rezepte/importieren', [RecipeImportController::class, 'create'])->name('recipes.import');
    Route::post('rezepte/importieren', [RecipeImportController::class, 'store'])->name('recipes.import.store');

    // Schreibrechte für Rezepte regelt die RecipePolicy (Kinder dürfen nur lesen).
    Route::resource('rezepte', RecipeController::class)
        ->names('recipes')
        ->parameters(['rezepte' => 'recipe']);

    Route::patch('rezepte/{recipe}/favorit', ToggleFavoriteController::class)->name('recipes.favorite');
    Route::get('rezepte/{recipe}/verlauf', RecipeHistoryController::class)->name('recipes.history');
    Route::get('rezepte/{recipe}/kochen', CookingModeController::class)->name('recipes.cook');
    Route::get('rezepte/{recipe}/bild', RecipeImageController::class)->name('recipes.image');

    // Essensplan: ansehen und vorschlagen darf jeder, festlegen nur Erwachsene ({week} = „2026-W43“).
    Route::pattern('week', '\d{4}-W\d{2}');
    Route::get('essensplan/{week?}', [MealPlanController::class, 'show'])->name('meal-plan.show');
    Route::get('essensplan/{week}/planen', [MealPlanController::class, 'edit'])->name('meal-plan.edit');
    Route::put('essensplan/{week}', [MealPlanController::class, 'update'])->name('meal-plan.update');

    // Wunschliste: wünschen darf jeder; Wünsche bleiben offen, bis sie im Essensplan stehen.
    Route::get('wuensche', [WishController::class, 'index'])->name('wishes.index');
    Route::post('wuensche', [WishController::class, 'store'])->name('wishes.store');
    Route::delete('wuensche/{suggestion}', [WishController::class, 'destroy'])->name('wishes.destroy');

    // Einkaufsliste aus dem Essensplan (nur Erwachsene, Prüfung im Controller).
    Route::get('einkaufsliste', ShoppingListController::class)->name('shopping-list.show');
    Route::post('einkaufsliste/immer-da', [PantryItemController::class, 'store'])->name('pantry-items.store');
    Route::delete('einkaufsliste/immer-da/{pantryItem}', [PantryItemController::class, 'destroy'])->name('pantry-items.destroy');

    Route::get('konto', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('konto/passwort', [AccountController::class, 'updatePassword'])->name('account.password');

    // Nur für Admins – Berechtigung prüfen Policy bzw. Gate in den Controllern.
    Route::get('familie', FamilyFavoritesController::class)->name('family.favorites');
    Route::get('aenderungen', RevisionLogController::class)->name('revisions.index');

    Route::resource('benutzer', UserController::class)
        ->except('show')
        ->names('users')
        ->parameters(['benutzer' => 'user']);
});
