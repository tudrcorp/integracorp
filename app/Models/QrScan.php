<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una lectura del QR (`scan`) o una visita a una página alojada (`view`).
 *
 * No guarda IP ni navegador: `visitor_hash` es un resumen que cambia cada día y
 * solo sirve para contar visitantes únicos.
 */
class QrScan extends Model
{
    public const KIND_SCAN = 'scan';

    public const KIND_VIEW = 'view';

    public const UPDATED_AT = null;

    protected $fillable = [
        'qr_destination_id',
        'qr_hosted_page_id',
        'kind',
        'visitor_hash',
        'device',
        'is_bot',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'created_at' => 'datetime',
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
