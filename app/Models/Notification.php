<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Assignment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $assignment_id
 * @property int|null $tournament_id
 * @property string $recipient_type
 * @property string|null $recipient_email
 * @property string|null $recipient_name
 * @property string $subject
 * @property string|null $body
 * @property string|null $template_used
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string|null $scheduled_at
 * @property string|null $expires_at
 * @property string|null $error_message
 * @property int $retry_count
 * @property int $priority
 * @property array<array-key, mixed>|null $attachments
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $sender_id
 * @property-read Assignment|null $assignment
 * @property-read mixed $formatted_sent_date
 * @property-read mixed $priority_badge_color
 * @property-read mixed $priority_display
 * @property-read mixed $recipient_type_display
 * @property-read mixed $referee
 * @property-read mixed $status_badge_color
 * @property-read mixed $status_display
 * @property-read \App\Models\Tournament|null $tournament
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification failed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification ofType($type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification sent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereAssignmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereAttachments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereRecipientEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereRecipientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereRecipientType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereRetryCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereTemplateUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification withPriority($priority)
 * @mixin \Eloquent
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'recipient_type',
        'recipient_email',
        'subject',
        'body',
        'template_used',
        'status',
        'priority',
        'sent_at',
        'recipient_name', // ✅ ADDED: Missing field
        'tournament_id',
        'error_message',
        'attachments',
        'retry_count',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'attachments' => 'array',
        'metadata' => 'array',
        'retry_count' => 'integer',
    ];

    /**
     * Notification statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Notification priorities
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';

    /**
     * Recipient types
     */
    public const TYPE_REFEREE = 'referee';
    public const TYPE_CLUB = 'club';
    public const TYPE_INSTITUTIONAL = 'institutional';
    public const TYPE_CUSTOM = 'custom';

    /**
     * Relationship with Assignment
     */
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Scope for pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope for failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for specific recipient type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('recipient_type', $type);
    }

    /**
     * Scope for specific priority
     */
    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent()
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $errorMessage)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Reset notification for retry
     */
    public function resetForRetry()
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'error_message' => null,
        ]);
    }

    /**
     * Check if notification can be retried
     */
    public function canBeRetried(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->retry_count < 3;
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeColorAttribute()
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_SENT => 'bg-green-100 text-green-800',
            self::STATUS_FAILED => 'bg-red-100 text-red-800',
            self::STATUS_CANCELLED => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute()
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'In attesa',
            self::STATUS_SENT => 'Inviata',
            self::STATUS_FAILED => 'Fallita',
            self::STATUS_CANCELLED => 'Annullata',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get recipient type display name
     */
    public function getRecipientTypeDisplayAttribute()
    {
        return match ($this->recipient_type) {
            self::TYPE_REFEREE => 'Arbitro',
            self::TYPE_CLUB => 'Circolo',
            self::TYPE_INSTITUTIONAL => 'Istituzionale',
            self::TYPE_CUSTOM => 'Personalizzato',
            default => ucfirst($this->recipient_type),
        };
    }

    /**
     * Get priority badge color
     */
    public function getPriorityBadgeColorAttribute()
    {
        return match ($this->priority) {
            self::PRIORITY_HIGH => 'bg-red-100 text-red-800',
            self::PRIORITY_NORMAL => 'bg-blue-100 text-blue-800',
            self::PRIORITY_LOW => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get priority display name
     */
    public function getPriorityDisplayAttribute()
    {
        return match ($this->priority) {
            self::PRIORITY_HIGH => 'Alta',
            self::PRIORITY_NORMAL => 'Normale',
            self::PRIORITY_LOW => 'Bassa',
            default => ucfirst($this->priority),
        };
    }

    /**
     * Get formatted sent date
     */
    public function getFormattedSentDateAttribute()
    {
        return $this->sent_at ? $this->sent_at->format('d/m/Y H:i') : '-';
    }


    /**
     * Get tournament information through assignment
     */
    public function getTournamentAttribute()
    {
        return $this->assignment?->tournament;
    }

    /**
     * Get referee information through assignment
     */
    public function getRefereeAttribute()
    {
        return $this->assignment?->user;
    }

    /**
     * Check if notification has attachments
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Get attachment count
     */
    public function getAttachmentCount(): int
    {
        return count($this->attachments ?? []);
    }

    /**
     * Add metadata
     */
    public function addMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->update(['metadata' => $metadata]);
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get the tournament this notification belongs to
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }


}
