<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\DataConsistencyService;
use Illuminate\Contracts\View\View;

/**
 * Sistema -> Controllo dati (2026-10-09): anomalie nei dati del database in
 * uso, anche su Aruba dove artisan non c'e'. Solo lettura.
 */
class DataCheckController extends Controller
{
    public function index(DataConsistencyService $service): View
    {
        return view('super-admin.data-check.index', ['checks' => $service->run()]);
    }
}
