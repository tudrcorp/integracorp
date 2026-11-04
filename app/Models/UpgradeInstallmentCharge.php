<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Parte de un upgrade repartido (`billing_mode = CUOTAS`) que se sumó a un aviso de cobro
 * pendiente al agregarlo a mitad de año. Se libera (LIBERADO) si el upgrade se quita.
 */
class UpgradeInstallmentCharge extends Model
{
    public const STATUS_ACTIVE = 'ACTIVO';

    public const STATUS_RELEASED = 'LIBERADO';

    protected $fillable = [
        'upgrade_table',
        'upgrade_id',
        'collection_id',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'upgrade_id' => 'integer',
            'collection_id' => 'integer',
            'amount' => 'decimal:2',
        ];
    }
}
