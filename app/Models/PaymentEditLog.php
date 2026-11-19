<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Edición de un pago PENDIENTE desde «Pagos registrados» de Administración:
 * qué campos cambiaron (antes → después), la foto previa y el motivo. Solo se
 * inserta.
 *
 * @see \App\Support\Affiliations\PendingPaymentEdition
 */
class PaymentEditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'payment_edit_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_type',
        'payment_id',
        'affiliation_id',
        'affiliation_code',
        'changes',
        'snapshot',
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
            'changes' => 'array',
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
