<?php

namespace App\Providers;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Observers\AssignmentObserver;
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

        // Registra l'Observer per sincronizzare TournamentNotification.referee_list
        // ad ogni modifica delle assegnazioni (evita N+1 UPDATE in NotificationController::index)
        Assignment::observe(AssignmentObserver::class);
    }
}
