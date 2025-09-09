<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RefereeCareerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RefereeCareerController extends Controller
{
    protected RefereeCareerService $careerService;

    public function __construct(RefereeCareerService $careerService)
    {
        $this->careerService = $careerService;
    }

    /**
     * Display a list of all referees with their career stats
     */
    public function curricula(Request $request): View
    {
        $year = $request->get('year', now()->year);
        $stats = $this->careerService->getHistoricalStats($year);

        return view('admin.referees.curricula', [
            'stats' => $stats,
            'year' => $year,
            'years' => range(2015, now()->year),
        ]);
    }

    /**
     * Display career details for a specific referee
     */
    public function curriculum(User $referee): View
    {
        $careerData = $this->careerService->getCareerData($referee);
        
        return view('admin.referees.curriculum', [
            'referee' => $referee,
            'careerData' => $careerData,
            'isAdmin' => true,
        ]);
    }
}
