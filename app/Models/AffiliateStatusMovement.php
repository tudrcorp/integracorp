<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Alta, baja o cambio de estatus de un afiliado (o de su afiliación), con la fecha en que
 * ocurrió. Solo se escribe; la llena {@see \App\Observers\AffiliateStatusMovementObserver}.
 */
class AffiliateStatusMovement extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_ALTA = 'ALTA';

    public const EVENT_BAJA = 'BAJA';

    public const EVENT_CAMBIO = 'CAMBIO';

    public const EVENT_ELIMINADO = 'ELIMINADO';

    public const EVENT_RESTAURADO = 'RESTAURADO';

    protected $table = 'affiliate_status_movements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
