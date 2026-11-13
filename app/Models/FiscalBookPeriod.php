<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FiscalBook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estado de un libro fiscal en un mes. Sin fila, el mes está abierto.
 * Cerrado, nadie agrega, edita, anula ni elimina líneas de ese mes.
 */
class FiscalBookPeriod extends Model
{
    public const STATUS_OPEN = 'ABIERTO';

    public const STATUS_CLOSED = 'CERRADO';

    protected $table = 'fiscal_book_periods';

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
            'book' => FiscalBook::class,
            'period' => 'date',
            'totals' => 'array',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * @return HasMany<FiscalBookPeriodEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(FiscalBookPeriodEvent::class);
    }
}
