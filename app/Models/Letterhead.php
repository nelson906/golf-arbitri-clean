<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int|null $zone_id
 * @property string|null $logo_path
 * @property string|null $header_text
 * @property string|null $header_content
 * @property string|null $footer_text
 * @property string|null $footer_content
 * @property array<array-key, mixed>|null $contact_info
 * @property array<array-key, mixed>|null $settings
 * @property bool $is_active
 * @property bool $is_default
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read array $font_settings
 * @property-read string $formatted_contact_info
 * @property-read string|null $logo_url
 * @property-read array $margin_settings
 * @property-read string $scope_display
 * @property-read \App\Models\User|null $updatedBy
 * @property-read \App\Models\Zone|null $zone
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead default()
 * @method static \Database\Factories\LetterheadFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead forZone($zoneId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereContactInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereFooterContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereFooterText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereHeaderContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereHeaderText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Letterhead whereZoneId($value)
 * @mixin \Eloquent
 */
class Letterhead extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'zone_id',
        'logo_path',
        'header_text',
        'footer_text',
        'contact_info',
        'settings',
        'is_active',
        'is_default',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'contact_info' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the zone that owns the letterhead.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the user who last updated this letterhead.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include active letterheads.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include default letterheads.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to filter by zone.
     */
    public function scopeForZone($query, $zoneId = null)
    {
        if ($zoneId === null) {
            return $query->whereNull('zone_id');
        }

        return $query->where(function ($q) use ($zoneId) {
            $q->where('zone_id', $zoneId)->orWhereNull('zone_id');
        });
    }

    /**
     * Get the logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return Storage::url($this->logo_path);
    }

    /**
     * Get formatted contact information.
     */
    public function getFormattedContactInfoAttribute(): string
    {
        if (!$this->contact_info) {
            return '';
        }

        $info = $this->contact_info;
        $parts = [];

        if (!empty($info['address'])) {
            $parts[] = $info['address'];
        }

        if (!empty($info['phone'])) {
            $parts[] = "Tel: " . $info['phone'];
        }

        if (!empty($info['email'])) {
            $parts[] = "Email: " . $info['email'];
        }

        if (!empty($info['website'])) {
            $parts[] = "Web: " . $info['website'];
        }

        return implode(' | ', $parts);
    }

    /**
     * Get scope display name.
     */
    public function getScopeDisplayAttribute(): string
    {
        return $this->zone ? $this->zone->name : 'Globale';
    }

    /**
     * Set this letterhead as default for its zone.
     */
    public function setAsDefault(): void
    {
        // Remove default from other letterheads in same zone
        self::where('zone_id', $this->zone_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Set this as default and ensure it's active
        $this->update([
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Get margin settings with defaults.
     */
    public function getMarginSettingsAttribute(): array
    {
        return array_merge([
            'top' => 20,
            'bottom' => 20,
            'left' => 25,
            'right' => 25,
        ], $this->settings['margins'] ?? []);
    }

    /**
     * Get font settings with defaults.
     */
    public function getFontSettingsAttribute(): array
    {
        return array_merge([
            'family' => 'Arial',
            'size' => 11,
            'color' => '#000000',
        ], $this->settings['font'] ?? []);
    }

    /**
     * Clone this letterhead.
     */
    public function cloneLetterhead(array $overrides = []): self
    {
        $clone = $this->replicate();
        $clone->title = $this->title . ' (Copia)';
        $clone->is_default = false;
        $clone->is_active = false;

        foreach ($overrides as $key => $value) {
            $clone->$key = $value;
        }

        // Copy logo if exists
        if ($this->logo_path && Storage::exists($this->logo_path)) {
            $extension = pathinfo($this->logo_path, PATHINFO_EXTENSION);
            $newPath = 'letterheads/logos/logo_' . uniqid() . '.' . $extension;
            Storage::copy($this->logo_path, $newPath);
            $clone->logo_path = $newPath;
        }

        $clone->save();

        return $clone;
    }

    /**
     * Generate letterhead HTML for preview or PDF generation.
     */
    public function generateHtml(array $variables = []): string
    {
        $variables = array_merge([
            'date' => now()->format('d/m/Y'),
            'zone_name' => $this->zone?->name ?? 'Golf Referee Management',
        ], $variables);

        $html = '<!DOCTYPE html>';
        $html .= '<html lang="it">';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<title>' . e($this->title) . '</title>';
        $html .= $this->generateCss();
        $html .= '</head>';
        $html .= '<body>';

        // Header
        $html .= '<div class="letterhead-header">';
        if ($this->logo_path) {
            $html .= '<div class="logo">';
            $html .= '<img src="' . Storage::url($this->logo_path) . '" alt="Logo">';
            $html .= '</div>';
        }
        if ($this->header_text) {
            $html .= '<div class="header-text">';
            $html .= nl2br(e($this->replaceVariables($this->header_text, $variables)));
            $html .= '</div>';
        }
        $html .= '</div>';

        // Contact info
        if ($this->contact_info) {
            $html .= '<div class="contact-info">';
            $html .= e($this->formatted_contact_info);
            $html .= '</div>';
        }

        // Content area (placeholder)
        $html .= '<div class="content">';
        $html .= '<p>Questo è un esempio di contenuto che verrà sostituito dal testo della lettera.</p>';
        $html .= '<p>Le variabili come {{referee_name}} e {{tournament_name}} verranno sostituite automaticamente.</p>';
        $html .= '</div>';

        // Footer
        if ($this->footer_text) {
            $html .= '<div class="letterhead-footer">';
            $html .= nl2br(e($this->replaceVariables($this->footer_text, $variables)));
            $html .= '</div>';
        }

        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }

    /**
     * Generate CSS for letterhead.
     */
    private function generateCss(): string
    {
        $margins = $this->margin_settings;
        $font = $this->font_settings;

        return '<style>
            body {
                margin: 0;
                padding: ' . $margins['top'] . 'mm ' . $margins['right'] . 'mm ' . $margins['bottom'] . 'mm ' . $margins['left'] . 'mm;
                font-family: "' . $font['family'] . '", Arial, sans-serif;
                font-size: ' . $font['size'] . 'pt;
                color: ' . $font['color'] . ';
                line-height: 1.4;
            }
            .letterhead-header {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #ddd;
            }
            .logo {
                margin-right: 20px;
            }
            .logo img {
                max-height: 60px;
                max-width: 120px;
                object-fit: contain;
            }
            .header-text {
                flex: 1;
                font-weight: bold;
            }
            .contact-info {
                text-align: center;
                font-size: ' . ($font['size'] - 1) . 'pt;
                margin-bottom: 20px;
                color: #666;
            }
            .content {
                min-height: 300px;
                margin-bottom: 30px;
            }
            .letterhead-footer {
                margin-top: 30px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
                font-size: ' . ($font['size'] - 1) . 'pt;
                text-align: center;
                color: #666;
            }
        </style>';
    }

    /**
     * Replace variables in text.
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        return $text;
    }

    /**
     * Get available variables for this letterhead.
     */
    public function getAvailableVariables(): array
    {
        return [
            'referee_name' => 'Nome dell\'arbitro',
            'tournament_name' => 'Nome del torneo',
            'tournament_date' => 'Data del torneo',
            'tournament_dates' => 'Date del torneo (inizio-fine)',
            'club_name' => 'Nome del circolo',
            'club_address' => 'Indirizzo del circolo',
            'club_phone' => 'Telefono del circolo',
            'club_email' => 'Email del circolo',
            'zone_name' => 'Nome della zona',
            'assignment_role' => 'Ruolo dell\'assegnazione',
            'arrival_time' => 'Orario di arrivo',
            'dress_code' => 'Codice abbigliamento',
            'special_instructions' => 'Istruzioni speciali',
            'date' => 'Data odierna',
            'fee_amount' => 'Importo compenso',
            'contact_person' => 'Persona di contatto',
            'deadline_date' => 'Data scadenza',
            'deadline_time' => 'Ora scadenza',
        ];
    }

    /**
     * Boot method to set up model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Delete logo file when deleting letterhead
        static::deleting(function ($letterhead) {
            if ($letterhead->logo_path && Storage::exists($letterhead->logo_path)) {
                Storage::delete($letterhead->logo_path);
            }
        });

        // Ensure only one default per zone
        static::saving(function ($letterhead) {
            if ($letterhead->is_default && $letterhead->isDirty('is_default')) {
                self::where('zone_id', $letterhead->zone_id)
                    ->where('id', '!=', $letterhead->id)
                    ->update(['is_default' => false]);
            }
        });
    }


}
