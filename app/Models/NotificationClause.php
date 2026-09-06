<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationClause extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'category',
        'title',
        'content',
        'applies_to',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const CATEGORIES = [
        'spese' => 'Spese e Rimborsi',
        'logistica' => 'Logistica e Servizi',
        'responsabilita' => 'Responsabilità e Assicurazioni',
        'comunicazioni' => 'Comunicazioni e Report',
        'altro' => 'Altro',
    ];

    public const APPLIES_TO = [
        'club' => 'Circolo',
        'referee' => 'Arbitri',
        'institutional' => 'Istituzionali',
        'all' => 'Tutti',
    ];

    // Relazioni
    /**
     * @return HasMany<NotificationClauseSelection, $this>
     */
    public function selections(): HasMany
    {
        return $this->hasMany(NotificationClauseSelection::class, 'clause_id');
    }

    // Scopes
    /**
     * @param  Builder<NotificationClause>  $query
     * @return Builder<NotificationClause>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<NotificationClause>  $query
     * @return Builder<NotificationClause>
     */
    public function scopeForRecipientType(Builder $query, string $type): Builder
    {
        return $query->where(function ($q) use ($type) {
            $q->where('applies_to', $type)
                ->orWhere('applies_to', 'all');
        });
    }

    /**
     * @param  Builder<NotificationClause>  $query
     * @return Builder<NotificationClause>
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * @param  Builder<NotificationClause>  $query
     * @return Builder<NotificationClause>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    // Accessors
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getAppliesToLabelAttribute(): string
    {
        return self::APPLIES_TO[$this->applies_to] ?? $this->applies_to;
    }

    public function getFormattedContentAttribute(): string
    {
        return nl2br(e($this->content));
    }

    public function getUsageCountAttribute(): int
    {
        return $this->selections()->count();
    }
}
