<?php

namespace App\Providers;

use App\Models\User;
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
    }
}
