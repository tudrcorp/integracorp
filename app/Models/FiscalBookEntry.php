<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FiscalBook;
use Database\Factories\FiscalBookEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una línea del Libro de Ventas o del Libro de Compras.
 *
 * Los montos están en bolívares con dos decimales y con signo: una nota de
 * crédito resta. Se escriben solo por {@see \App\Support\Administration\FiscalBooks\FiscalBookEntryWriter},
 * que garantiza que total = exento + exportación + base + IVA al céntimo.
 */
class FiscalBookEntry extends Model
{
    /** @use HasFactory<FiscalBookEntryFactory> */
    use HasFactory;

    use SoftDeletes;

    public const TYPE_INVOICE = 'FACTURA';

    public const TYPE_DEBIT_NOTE = 'NOTA DEBITO';

    public const TYPE_CREDIT_NOTE = 'NOTA CREDITO';

    public const STATUS_ACTIVE = 'VIGENTE';

    public const STATUS_ANNULLED = 'ANULADA';

    public const TAXPAYER = 'C';

    public const NON_TAXPAYER = 'NC';

    public const SOURCE_SALE = 'VENTA';

    public const SOURCE_PAYABLE = 'CXP';

    public const SOURCE_MANUAL = 'MANUAL';

    public const SOURCE_LEGACY = 'EXCEL';

    protected $table = 'fiscal_book_entries';

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
            'document_date' => 'date',
            'withholding_date' => 'date',
            'total_amount' => 'decimal:2',
            'exempt_amount' => 'decimal:2',
            'export_amount' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'withholding_amount' => 'decimal:2',
            'source_amount_usd' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'manually_adjusted' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function documentTypeOptions(): array
    {
        return [
            self::TYPE_INVOICE => 'Factura',
            self::TYPE_DEBIT_NOTE => 'Nota de débito',
            self::TYPE_CREDIT_NOTE => 'Nota de crédito',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_SALE => 'Ventas del sistema',
            self::SOURCE_PAYABLE => 'Cuentas por pagar',
            self::SOURCE_MANUAL => 'Carga manual',
            self::SOURCE_LEGACY => 'Excel histórico',
        ];
    }

    /**
     * @param  Builder<FiscalBookEntry>  $query
     * @return Builder<FiscalBookEntry>
     */
    public function scopeForBook(Builder $query, FiscalBook $book): Builder
    {
        return $query->where('book', $book->value);
    }

    public function isAnnulled(): bool
    {
        return $this->status === self::STATUS_ANNULLED;
    }

    public function isCreditNote(): bool
    {
        return $this->document_type === self::TYPE_CREDIT_NOTE;
    }

    /**
     * Clave de comparación del número de documento: sin espacios ni ceros a la
     * izquierda y en mayúsculas («01011» y «1011» son la misma factura).
     */
    public static function documentNumberKey(string $number): string
    {
        $key = mb_strtoupper(preg_replace('/\s+/', '', $number) ?? '');
        $trimmed = ltrim($key, '0');

        return $trimmed === '' ? ($key === '' ? '' : '0') : $trimmed;
    }
}
