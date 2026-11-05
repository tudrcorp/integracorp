<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila de la foto del cierre: un afiliado del mes, un prorrateo o un retiro.
 */
class CapitationBillingClosingItem extends Model
{
    public const UPDATED_AT = null;

    public const SECTION_MONTH = 'MES';

    public const SECTION_PRORATE = 'PRORRATEO';

    public const SECTION_RETIREMENT = 'RETIRO';

    protected $table = 'capitation_billing_closing_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'immutable_date',
            'enrolled_on' => 'immutable_date',
            'retired_on' => 'immutable_date',
            'monthly_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'prorate_days' => 'integer',
        ];
    }

    public function closing(): BelongsTo
    {
        return $this->belongsTo(CapitationBillingClosing::class, 'closing_id');
    }
}
