<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Week;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Deutsche URLs: /rezepte/neu und /rezepte/{slug}/bearbeiten
        Route::resourceVerbs([
            'create' => 'neu',
            'edit' => 'bearbeiten',
        ]);

        Model::shouldBeStrict(! $this->app->isProduction());

        // Bewusst ohne Mindestlänge oder Sonderzeichen: kleiner Familienkreis im lokalen Netz.
        Password::defaults(fn () => Password::min(1));

        Gate::define('view-family-favorites', fn (User $user) => $user->isAdmin());
        Gate::define('view-revision-log', fn (User $user) => $user->isAdmin());

        // Essensplan festlegen: nur Erwachsene, nur für diese und nächste Woche.
        Gate::define('plan-meals', fn (User $user, Week $week) => $user->role->canPlanMeals() && $week->isOpen());

        // Einkaufsliste: für alle, die den Plan festlegen (Erwachsene).
        Gate::define('view-shopping-list', fn (User $user) => $user->role->canPlanMeals());

        Route::bind('week', fn (string $value) => Week::fromRouteKey($value));
    }
}
