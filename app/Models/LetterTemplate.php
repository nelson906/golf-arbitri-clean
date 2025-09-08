<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $subject
 * @property string|null $description
 * @property string $body
 * @property int|null $zone_id
 * @property int|null $tournament_type_id
 * @property bool $is_active
 * @property bool $is_default
 * @property array<array-key, mixed>|null $variables
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $scope_label
 * @property-read mixed $status_label
 * @property-read mixed $type_badge_color
 * @property-read mixed $type_display
 * @property-read mixed $type_label
 * @property-read mixed $used_variables
 * @property-read \App\Models\TournamentType|null $tournamentType
 * @property-read Zone|null $zone
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate default()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate forTournamentType($tournamentTypeId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate forZone($zoneId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate ofType($type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereTournamentTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereVariables($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LetterTemplate whereZoneId($value)
 * @mixin \Eloquent
 */
class LetterTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'subject',
        'body',
        'zone_id',
        'tournament_type_id',
        'is_active',
        'is_default',
        'variables',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'variables' => 'array',
    ];

    /**
     * Relationship with Zone
     */
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Relationship with TournamentType
     */
    public function tournamentType()
    {
        return $this->belongsTo(TournamentType::class);
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default templates
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for specific zone
     */
    public function scopeForZone($query, $zoneId = null)
    {
        if ($zoneId) {
            return $query->where(function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId)
                    ->orWhereNull('zone_id');
            });
        }

        return $query->whereNull('zone_id');
    }

    /**
     * Scope for specific tournament type
     */
    public function scopeForTournamentType($query, $tournamentTypeId = null)
    {
        if ($tournamentTypeId) {
            return $query->where(function ($q) use ($tournamentTypeId) {
                $q->where('tournament_type_id', $tournamentTypeId)
                    ->orWhereNull('tournament_type_id');
            });
        }

        return $query->whereNull('tournament_type_id');
    }

    /**
     * Get template type badge color
     */
    public function getTypeBadgeColorAttribute()
    {
        return match ($this->type) {
            'assignment' => 'bg-blue-100 text-blue-800',
            'convocation' => 'bg-green-100 text-green-800',
            'club' => 'bg-yellow-100 text-yellow-800',
            'institutional' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get template type display name
     */
    public function getTypeDisplayAttribute()
    {
        return match ($this->type) {
            'assignment' => 'Assegnazione',
            'convocation' => 'Convocazione',
            'club' => 'Circolo',
            'institutional' => 'Istituzionale',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get template type label (alias for compatibility)
     */
    public function getTypeLabelAttribute()
    {
        return $this->type_display;
    }

    /**
     * Get template status label
     */
    public function getStatusLabelAttribute()
    {
        return $this->is_active ? 'Attivo' : 'Inattivo';
    }

    /**
     * Get template scope label
     */
    public function getScopeLabelAttribute()
    {
        if ($this->zone_id && $this->tournament_type_id) {
            return 'Specifico';
        } elseif ($this->zone_id) {
            return 'Zonale';
        } elseif ($this->tournament_type_id) {
            return 'Per Tipo Torneo';
        } else {
            return 'Globale';
        }
    }

    /**
     * Extract variables from template content
     */
    public function getUsedVariablesAttribute()
    {
        $text = $this->subject . ' ' . $this->body;
        preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches);

        $variables = [];
        foreach (array_unique($matches[1]) as $variable) {
            $variables[trim($variable)] = true;
        }

        return $variables;
    }

    /**
     * Replace variables in text
     */
    public function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    /**
     * Get processed subject with variables
     */
    public function getProcessedSubject(array $variables = []): string
    {
        return $this->replaceVariables($this->subject, $variables);
    }

    /**
     * Get processed body with variables
     */
    public function getProcessedBody(array $variables = []): string
    {
        return $this->replaceVariables($this->body, $variables);
    }

    /**
     * Check if template can be used for given context
     */
    public function canBeUsedFor(?int $zoneId = null, ?int $tournamentTypeId = null): bool
    {
        // Check if template is active
        if (!$this->is_active) {
            return false;
        }

        // Check zone compatibility
        if ($this->zone_id && $this->zone_id !== $zoneId) {
            return false;
        }

        // Check tournament type compatibility
        if ($this->tournament_type_id && $this->tournament_type_id !== $tournamentTypeId) {
            return false;
        }

        return true;
    }

    /**
     * Get the best template for given criteria
     */
    public static function getBestTemplate(
        string $type,
        ?int $zoneId = null,
        ?int $tournamentTypeId = null
    ): ?self {
        return static::active()
            ->ofType($type)
            ->forZone($zoneId)
            ->forTournamentType($tournamentTypeId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

}
