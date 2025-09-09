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
        $search = $request->get('search');
        $zone = $request->get('zone');
        $level = $request->get('level');
        $sort = $request->get('sort', 'last_name');
        $direction = $request->get('direction', 'asc');

        $query = User::where('user_type', 'referee')->with('zone');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('referee_code', 'like', "%{$search}%");
            });
        }

        if ($zone) {
            $query->where('zone_id', $zone);
        }

        if ($level) {
            $query->where('level', $level);
        }

        $query->orderBy($sort, $direction);
        $referees = $query->get();

        $stats = $referees->map(function($referee) use ($year) {
            $data = $this->careerService->getCareerData($referee, $year);
            return [
                'referee' => $referee,
                'stats' => $data['career_summary'] ?? null,
                'year_data' => $year ? ($data['year_summary'] ?? null) : null,
            ];
        });

        return view('admin.referees.curricula', [
            'stats' => $stats,
            'year' => $year,
            'years' => range(2015, now()->year),
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
            'zone' => $zone,
            'level' => $level,
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
