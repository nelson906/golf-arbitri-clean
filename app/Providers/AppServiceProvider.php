<?php

namespace App\Providers;

use App\Models\Letterhead;
use App\Policies\LetterheadPolicy;
use Illuminate\Support\Facades\Gate;
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
        // Register policies
        Gate::policy(Letterhead::class, LetterheadPolicy::class);
    }
}
