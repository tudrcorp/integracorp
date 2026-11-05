<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de contado de la factura de una orden de servicio. Nunca se borra: al reversarse
 * queda REVERSADO con quién, cuándo y por qué ({@see \App\Support\Operations\CashPayments\CashPaymentService}).
 */
class OperationCashPayment extends Model
{
    public const STATUS_REGISTERED = 'REGISTRADO';

    public const STATUS_REVERSED = 'REVERSADO';

    public const SCOPE_NATIONAL = 'nacional';

    public const SCOPE_INTERNATIONAL = 'internacional';

    protected $table = 'operation_cash_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'invoice_amount' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'bcv_rate' => 'decimal:4',
            'amount_in_invoice_currency' => 'decimal:2',
            'payable_snapshot' => 'array',
            'reversed_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_REGISTERED;
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(OperationServiceOrder::class, 'operation_service_order_id');
    }

    public function accountsPayable(): BelongsTo
    {
        return $this->belongsTo(OperationAccountsPayable::class, 'operation_accounts_payable_id');
    }

    public function documentLabel(): string
    {
        $parts = array_filter([
            filled($this->invoice_number) ? 'Factura '.$this->invoice_number : null,
            filled($this->invoice_receipt_number) ? 'Recibo '.$this->invoice_receipt_number : null,
        ]);

        return $parts === [] ? 'Factura' : implode(' · ', $parts);
    }
}
