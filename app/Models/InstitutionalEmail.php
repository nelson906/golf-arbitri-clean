<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $zone_id
 * @property string $category
 * @property bool $receive_all_notifications
 * @property array<array-key, mixed>|null $notification_types
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $category_badge_color
 * @property-read mixed $category_display
 * @property-read \App\Models\Zone|null $zone
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail forNotificationType($type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail forZone($zoneId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail ofCategory($category)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereNotificationTypes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereReceiveAllNotifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail whereZoneId($value)
 * @mixin \Eloquent
 */
class InstitutionalEmail extends Model
{
    use HasFactory;

    /**
     * Available categories for institutional emails
     */
    public const CATEGORIES = [
        'federazione' => 'Federazione',
        'comitati' => 'Comitati',
        'zone' => 'Zone',
        'altro' => 'Altro',
    ];

    /**
     * Available notification types
     */
    public const NOTIFICATION_TYPES = [
        'assignment' => 'Assegnazioni',
        'convocation' => 'Convocazioni',
        'club' => 'Comunicazioni Circoli',
        'institutional' => 'Comunicazioni Istituzionali',
        'tournament_updates' => 'Aggiornamenti Tornei',
        'system' => 'Notifiche Sistema',
    ];

    protected $fillable = [
        'name',
        'email',
        'description',
        'is_active',
        'zone_id',
        'category',
        'receive_all_notifications',
        'notification_types',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'receive_all_notifications' => 'boolean',
        'notification_types' => 'array',
    ];

    /**
     * Relationship with Zone
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Scope for active emails
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
     * Scope for specific category
     */
    public function scopeOfCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for notification type
     */
    public function scopeForNotificationType($query, $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->where('receive_all_notifications', true)
              ->orWhereJsonContains('notification_types', $type);
        });
    }

    /**
     * Get category badge color
     */
    public function getCategoryBadgeColorAttribute()
    {
        return match ($this->category) {
            'federazione' => 'bg-red-100 text-red-800',
            'comitati' => 'bg-blue-100 text-blue-800',
            'zone' => 'bg-green-100 text-green-800',
            'altro' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get category display name
     */
    public function getCategoryDisplayAttribute()
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Get all available categories
     */
    public static function getCategories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Get all available notification types
     */
    public static function getNotificationTypes(): array
    {
        return self::NOTIFICATION_TYPES;
    }

    /**
     * Check if should receive notification type
     */
    public function shouldReceiveNotificationType(string $type): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->receive_all_notifications) {
            return true;
        }

        $types = $this->notification_types ?? [];
        return in_array($type, $types);
    }

    /**
     * Add notification type
     */
    public function addNotificationType(string $type): void
    {
        $types = $this->notification_types ?? [];
        if (!in_array($type, $types)) {
            $types[] = $type;
            $this->update(['notification_types' => $types]);
        }
    }

    /**
     * Remove notification type
     */
    public function removeNotificationType(string $type): void
    {
        $types = $this->notification_types ?? [];
        $types = array_filter($types, fn($t) => $t !== $type);
        $this->update(['notification_types' => array_values($types)]);
    }
}
