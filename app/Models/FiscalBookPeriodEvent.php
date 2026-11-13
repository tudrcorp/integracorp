<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de cierres y reaperturas de un libro fiscal.
 */
class FiscalBookPeriodEvent extends Model
{
    public const ACTION_CLOSED = 'CERRADO';

    public const ACTION_REOPENED = 'REABIERTO';

    public const UPDATED_AT = null;

    protected $table = 'fiscal_book_period_events';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'totals' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FiscalBookPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(FiscalBookPeriod::class, 'fiscal_book_period_id');
    }
}
