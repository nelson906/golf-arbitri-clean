<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 📁 Document Model - Gestione documenti
 *
 * @property int $id
 * @property string $name Nome visualizzato
 * @property string $original_name Nome file originale
 * @property string $file_path Path nel storage
 * @property int $file_size Dimensione in bytes
 * @property string $mime_type Tipo MIME del file
 * @property string $category Categoria del documento
 * @property string $type Tipo di file dedotto dal MIME
 * @property string|null $description Descrizione opzionale
 * @property int|null $tournament_id
 * @property int|null $zone_id
 * @property int $uploader_id
 * @property bool $is_public Visibile a tutti gli utenti
 * @property int $download_count Numero di download
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $download_url
 * @property-read string $file_size_human
 * @property-read string $file_url
 * @property-read string $type_icon
 * @property-read \App\Models\Tournament|null $tournament
 * @property-read \App\Models\User $uploader
 * @property-read \App\Models\Zone|null $zone
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document category(string $category)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereDownloadCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereOriginalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereUploaderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereZoneId($value)
 * @mixin \Eloquent
 */
class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'original_name',
        'file_path',
        'file_size',
        'mime_type',
        'category',
        'type',
        'description',
        'tournament_id',
        'zone_id',
        'uploader_id',
        'is_public',
        'download_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'file_size' => 'integer',
        'download_count' => 'integer',
    ];

    // Document categories
    const CATEGORY_GENERAL = 'general';
    const CATEGORY_TOURNAMENT = 'tournament';
    const CATEGORY_REGULATION = 'regulation';
    const CATEGORY_FORM = 'form';
    const CATEGORY_TEMPLATE = 'template';

    const CATEGORIES = [
        self::CATEGORY_GENERAL => 'Generale',
        self::CATEGORY_TOURNAMENT => 'Torneo',
        self::CATEGORY_REGULATION => 'Regolamento',
        self::CATEGORY_FORM => 'Modulo',
        self::CATEGORY_TEMPLATE => 'Template',
    ];

    // Document types
    const TYPE_PDF = 'pdf';
    const TYPE_DOCUMENT = 'document';
    const TYPE_SPREADSHEET = 'spreadsheet';
    const TYPE_IMAGE = 'image';
    const TYPE_TEXT = 'text';
    const TYPE_OTHER = 'other';

    const TYPES = [
        self::TYPE_PDF => 'PDF',
        self::TYPE_DOCUMENT => 'Documento',
        self::TYPE_SPREADSHEET => 'Foglio di calcolo',
        self::TYPE_IMAGE => 'Immagine',
        self::TYPE_TEXT => 'Testo',
        self::TYPE_OTHER => 'Altro',
    ];

    /**
     * Get the uploader (user) who uploaded this document
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * Get the tournament this document belongs to
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the zone this document belongs to
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Get download URL
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('documents.download', $this);
    }

    /**
     * Get type icon
     */
    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            self::TYPE_PDF => 'fas fa-file-pdf text-red-500',
            self::TYPE_DOCUMENT => 'fas fa-file-word text-blue-500',
            self::TYPE_SPREADSHEET => 'fas fa-file-excel text-green-500',
            self::TYPE_IMAGE => 'fas fa-file-image text-purple-500',
            self::TYPE_TEXT => 'fas fa-file-alt text-gray-500',
            default => 'fas fa-file text-gray-400',
        };
    }

    /**
     * Scope for public documents
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for documents in a specific category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
