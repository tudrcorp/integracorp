<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora de un cierre: cuándo se cerró o reabrió, quién y con qué totales o razón.
 */
class CapitationBillingClosingEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_CLOSED = 'CERRADO';

    public const ACTION_REOPENED = 'REABIERTO';

    protected $table = 'capitation_billing_closing_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'totals' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
