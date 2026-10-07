<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
 * @property-read \App\Models\Zone|null $zone
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail forNotificationType($type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstitutionalEmail newQuery()
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
 *
 * @mixin \Eloquent
 */
class InstitutionalEmail extends Model
{

    /**
     * Available categories for institutional emails
     */
    public const CATEGORIES = [
        'federazione' => 'Federazione',
        'comitati' => 'Comitati',
        'zone' => 'Zone',
        'altro' => 'Altro',
    ];

    protected $fillable = [
        'name',
        'email',
        'description',
        'is_active',
        'zone_id',
        'category',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Relationship with Zone
     *
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Scope for active emails
     *
     * @param  Builder<InstitutionalEmail>  $query
     * @return Builder<InstitutionalEmail>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

}
