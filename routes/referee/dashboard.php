<?php

use App\Http\Controllers\User\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legacy Referee Routes (Compatibility Layer)
|--------------------------------------------------------------------------
| Queste route mantengono la compatibilità con i vecchi URL /referee/*.
| Devono essere protette dal middleware auth + referee_or_admin esattamente
| come le route /user/* equivalenti.
|
| FIX: aggiunto middleware auth — in precedenza mancante (rischio accesso
| non autenticato). Vedere test: RefereeDashboardAuthTest.
|
| NOTA (audit 2026-07): rimosso il blocco legacy /referee/quadranti/* —
| mai referenziato da views/JS; la versione attiva è user.quadranti.*
| (routes/user/quadranti.php). referee.dashboard resta: è il target del
| redirect di DashboardController per gli arbitri.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'referee_or_admin'])->group(function () {
    Route::get('/referee/dashboard', [DashboardController::class, 'index'])->name('referee.dashboard');
});
