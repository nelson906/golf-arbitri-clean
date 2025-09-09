<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\RefereeCareerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumController extends Controller
{
    protected RefereeCareerService $careerService;

    public function __construct(RefereeCareerService $careerService)
    {
        $this->careerService = $careerService;
    }

    /**
     * Display the current user's career details
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $careerData = $this->careerService->getCareerData($user);
        
        return view('user.curriculum.index', [
            'careerData' => $careerData,
        ]);
    }
}
