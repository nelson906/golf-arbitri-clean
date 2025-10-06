<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationClause extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'category',
        'title',
        'content',
        'applies_to',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    const CATEGORIES = [
        'spese' => 'Spese e Rimborsi',
        'logistica' => 'Logistica e Servizi',
        'responsabilita' => 'Responsabilità e Assicurazioni',
        'comunicazioni' => 'Comunicazioni e Report',
        'altro' => 'Altro'
    ];

    const APPLIES_TO = [
        'club' => 'Circolo',
        'referee' => 'Arbitri',
        'institutional' => 'Istituzionali',
        'all' => 'Tutti'
    ];

    // Relazioni
    public function selections()
    {
        return $this->hasMany(NotificationClauseSelection::class, 'clause_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRecipientType($query, string $type)
    {
        return $query->where(function($q) use ($type) {
            $q->where('applies_to', $type)
              ->orWhere('applies_to', 'all');
        });
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
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
