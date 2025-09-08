<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * 📢 Communication Model - Gestione comunicazioni di sistema
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string $type Tipo di comunicazione
 * @property string $status Stato della comunicazione
 * @property string $priority Priorità della comunicazione
 * @property int|null $zone_id
 * @property int $author_id
 * @property \Illuminate\Support\Carbon|null $scheduled_at Quando pubblicare (null = subito)
 * @property \Illuminate\Support\Carbon|null $expires_at Quando far scadere (null = mai)
 * @property \Illuminate\Support\Carbon|null $published_at Quando è stata effettivamente pubblicata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $author
 * @property-read string $priority_badge
 * @property-read string $type_badge
 * @property-read \App\Models\Zone|null $zone
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Communication active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Communication published()
 */
class Communication extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'type',
        'status',
        'priority',
        'zone_id',
        'author_id',
        'scheduled_at',
        'expires_at',
        'published_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'expires_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    // Communication types
    const TYPE_ANNOUNCEMENT = 'announcement';
    const TYPE_ALERT = 'alert';
    const TYPE_MAINTENANCE = 'maintenance';
    const TYPE_INFO = 'info';

    const TYPES = [
        self::TYPE_ANNOUNCEMENT => 'Annuncio',
        self::TYPE_ALERT => 'Avviso Importante',
        self::TYPE_MAINTENANCE => 'Manutenzione',
        self::TYPE_INFO => 'Informazione',
    ];

    // Communication statuses
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_EXPIRED = 'expired';

    const STATUSES = [
        self::STATUS_DRAFT => 'Bozza',
        self::STATUS_PUBLISHED => 'Pubblicato',
        self::STATUS_EXPIRED => 'Scaduto',
    ];

    // Priority levels
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    const PRIORITIES = [
        self::PRIORITY_LOW => 'Bassa',
        self::PRIORITY_NORMAL => 'Normale',
        self::PRIORITY_HIGH => 'Alta',
        self::PRIORITY_URGENT => 'Urgente',
    ];

    /**
     * Get the author of the communication
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the zone for this communication
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Scope for published communications
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
                    ->where(function($q) {
                        $q->whereNull('scheduled_at')
                          ->orWhere('scheduled_at', '<=', now());
                    })
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope for active communications
     */
    public function scopeActive($query)
    {
        return $query->published();
    }

    /**
     * Check if communication is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_PUBLISHED &&
               ($this->scheduled_at === null || $this->scheduled_at <= now()) &&
               ($this->expires_at === null || $this->expires_at > now());
    }

    /**
     * Get priority badge class
     */
    public function getPriorityBadgeAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'bg-gray-100 text-gray-800',
            self::PRIORITY_NORMAL => 'bg-blue-100 text-blue-800',
            self::PRIORITY_HIGH => 'bg-yellow-100 text-yellow-800',
            self::PRIORITY_URGENT => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get type badge class
     */
    public function getTypeBadgeAttribute(): string
    {
        return match($this->type) {
            self::TYPE_ANNOUNCEMENT => 'bg-green-100 text-green-800',
            self::TYPE_ALERT => 'bg-red-100 text-red-800',
            self::TYPE_MAINTENANCE => 'bg-orange-100 text-orange-800',
            self::TYPE_INFO => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
