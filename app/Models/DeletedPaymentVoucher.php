<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pago pendiente eliminado desde «Historial de pagos», con la foto de la fila y
 * los comprobantes archivados. Solo se inserta.
 *
 * @see \App\Support\Affiliations\PendingPaymentDeletion
 */
class DeletedPaymentVoucher extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'deleted_payment_vouchers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_type',
        'payment_id',
        'affiliation_id',
        'affiliation_code',
        'snapshot',
        'files',
        'note',
        'panel',
        'actor_id',
        'actor_name',
        'ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'files' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
