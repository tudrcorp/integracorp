<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusCuentaPorPagar;
use Database\Factories\OperationAccountsPayableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Factura de proveedor registrada en cuentas por pagar (panel de Operaciones).
 */
class OperationAccountsPayable extends Model
{
    /** @use HasFactory<OperationAccountsPayableFactory> */
    use HasFactory;

    protected $table = 'operation_accounts_payables';

    protected $fillable = [
        'operation_service_order_id',
        'invoice_date',
        'invoice_registration_date',
        'invoice_number',
        'invoice_control_number',
        'supplier_id',
        'supplier_name',
        'supplier_rif',
        'business_unit_id',
        'invoice_amount',
        'invoice_currency',
        'invoice_file_path',
        'payment_status',
        'payment_reference',
        'payment_date',
        'national_bank',
        'international_bank',
        'payment_amount_usd',
        'payment_amount_ves',
        'observations',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'invoice_registration_date' => 'date',
            'payment_date' => 'date',
            'invoice_amount' => 'decimal:4',
            'payment_amount_usd' => 'decimal:4',
            'payment_amount_ves' => 'decimal:4',
            'payment_status' => StatusCuentaPorPagar::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function operationServiceOrder(): BelongsTo
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }

    public function hasInvoiceDocument(): bool
    {
        return filled($this->invoice_file_path);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === StatusCuentaPorPagar::Pagada;
    }

    /**
     * Nombre del proveedor tal como quedó congelado en la factura.
     */
    public function supplierLabel(): string
    {
        return trim($this->supplier_name) !== '' ? $this->supplier_name : '—';
    }
}
