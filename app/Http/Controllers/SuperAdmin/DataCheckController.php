<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\DataConsistencyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Sistema -> Controllo dati (2026-10-09): anomalie nei dati del database in
 * uso, anche su Aruba dove artisan non c'e'. Solo lettura.
 *
 * La pagina si apre subito con la rotella; i risultati (qualche secondo:
 * il confronto dei conteggi ripercorre il curriculum di ogni arbitro)
 * arrivano dalla stessa rotta con ?parziale=1.
 */
class DataCheckController extends Controller
{
    public function index(Request $request, DataConsistencyService $service): View
    {
        if ($request->boolean('parziale')) {
            return view('super-admin.data-check._risultati', ['checks' => $service->run()]);
        }

        return view('super-admin.data-check.index');
    }
}
