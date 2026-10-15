<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una versión del QR de un destino: su imagen, a dónde llevaba, con qué estilo y
 * quién la creó. No se edita ni se borra; restaurar crea una versión nueva.
 */
class QrDestinationVersion extends Model
{
    protected $fillable = [
        'qr_destination_id',
        'version',
        'target_type',
        'target_url',
        'qr_hosted_page_id',
        'style',
        'image_path',
        'image_sha256',
        'note',
        'restored_from_version_id',
        'created_by',
        'created_by_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'style' => 'array',
            'restored_from_version_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<QrDestination, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(QrDestination::class, 'qr_destination_id');
    }

    /**
     * @return BelongsTo<QrHostedPage, $this>
     */
    public function hostedPage(): BelongsTo
    {
        return $this->belongsTo(QrHostedPage::class, 'qr_hosted_page_id');
    }
}
