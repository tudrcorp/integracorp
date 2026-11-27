<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Archivo que el cliente envió por WhatsApp. Vive en el disco privado hasta que se purga; al cerrar la
 * conversación se copia al caso de telemedicina que eligió el analista.
 */
class CrmHandoffAttachment extends Model
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_AUDIO = 'audio';

    protected $fillable = [
        'message_id',
        'crm_handoff_message_id',
        'handoff_id',
        'phone',
        'media_type',
        'mime_type',
        'original_name',
        'extension',
        'size_bytes',
        'sha256',
        'disk',
        'path',
        'caption',
        'telemedicine_case_id',
        'telemedicine_case_attachment_id',
        'filed_at',
        'filed_by',
        'filing_skipped',
        'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'filed_at' => 'datetime',
            'filing_skipped' => 'boolean',
            'purged_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CrmHandoffMessage::class, 'crm_handoff_message_id');
    }

    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    /**
     * Archivos de la conversación que todavía no se agregaron a un caso ni se marcaron como «sin caso».
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePendingFiling(Builder $query): Builder
    {
        return $query->whereNull('filed_at')->where('filing_skipped', false)->whereNull('purged_at');
    }

    public function isPurged(): bool
    {
        return $this->purged_at !== null;
    }

    /**
     * Las imágenes que el navegador muestra sin plugins; HEIC/HEIF se ofrecen como documento.
     */
    public function isPreviewableImage(): bool
    {
        return $this->media_type === self::TYPE_IMAGE && in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }
}
