<?php

namespace App\Providers;

use App\Models\Tournament;
use App\Observers\TournamentObserver;
use Illuminate\Support\ServiceProvider;

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
        // Registra l'Observer per sincronizzare tournament.zone_id con club.zone_id
        Tournament::observe(TournamentObserver::class);
    }
}
