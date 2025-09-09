<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefereeCareerHistory extends Model
{
    use HasFactory;

    protected $table = 'referee_career_history';

    protected $fillable = [
        'user_id', 'tournaments_by_year', 'assignments_by_year',
        'availabilities_by_year', 'level_changes_by_year',
        'career_stats', 'last_updated_year', 'data_completeness_score'
    ];

    protected $casts = [
        'tournaments_by_year' => 'array',
        'assignments_by_year' => 'array',
        'availabilities_by_year' => 'array',
        'level_changes_by_year' => 'array',
        'career_stats' => 'array',
        'data_completeness_score' => 'decimal:2'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessors per anni specifici
    public function getTournamentsForYear(int $year): array
    {
        return $this->tournaments_by_year[$year] ?? [];
    }

    public function getAssignmentsForYear(int $year): array
    {
        return $this->assignments_by_year[$year] ?? [];
    }

    public function getAvailabilitiesForYear(int $year): array
    {
        return $this->availabilities_by_year[$year] ?? [];
    }

    // Helper methods
    public function generateStatsSummary(): array
    {
        $tournaments = $this->tournaments_by_year ?? [];
        $assignments = $this->assignments_by_year ?? [];

        $totalTournaments = collect($tournaments)->flatten(1)->count();
        $totalYears = count($tournaments);

        $rolesSummary = collect($assignments)
            ->flatten(1)
            ->groupBy('role')
            ->map->count()
            ->toArray();

        return [
            'total_years' => $totalYears,
            'total_tournaments' => $totalTournaments,
            'avg_tournaments_per_year' => $totalYears > 0 ? round($totalTournaments / $totalYears, 1) : 0,
            'roles_summary' => $rolesSummary,
            'most_active_year' => collect($tournaments)->map->count()->flip()->max() ?? null
        ];
    }
}
