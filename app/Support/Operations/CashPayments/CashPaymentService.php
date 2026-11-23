<?php

declare(strict_types=1);

namespace App\Support\Operations\CashPayments;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Models\OperationCashPayment;
use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\BankCatalog;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Pago de contado de la factura de una orden de servicio.
 *
 * Una factura masiva (varias órdenes) se paga con un solo pago: se reparte entre las
 * órdenes con la misma proporción que la factura ({@see self::split()}), cada parte queda
 * con el mismo `payment_batch` y se reversan todas juntas.
 *
 * - **Registrar** (al cargar la factura, dentro de su misma transacción): valida que el pago
 *   cubra el total de la factura —en la moneda de la factura o convertido a la tasa BCV—,
 *   guarda la foto de los datos de pago que tenía la cuenta por pagar y la deja PAGADA.
 * - **Reversar** (con razón): marca el pago REVERSADO y devuelve la cuenta por pagar a la foto,
 *   solo si nadie cambió sus datos de pago después. Nunca se borra un pago.
 */
final class CashPaymentService
{
    /** Diferencia admitida contra el total de la factura, en su moneda (redondeo de la conversión). */
    public const TOLERANCE = 0.01;

    public const MIN_REASON_LENGTH = 10;

    /** Datos de pago de la cuenta por pagar que el pago de contado toca (y la reversión restaura). */
    public const PAYABLE_PAYMENT_FIELDS = [
        'payment_status', 'payment_reference', 'payment_date', 'national_bank', 'international_bank',
        'payment_amount_usd', 'payment_amount_ves', 'payment_receipt_path',
    ];

    /**
     * Datos del formulario con el banco que corresponde a la moneda del pago.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function paymentData(array $data): array
    {
        return [
            ...$data,
            'cash_payment_bank' => ($data['cash_payment_currency'] ?? 'USD') === 'VES'
                ? ($data['cash_payment_bank_national'] ?? null)
                : ($data['cash_payment_bank_international'] ?? null),
        ];
    }

    /**
     * Valida los datos del formulario contra la factura y devuelve el pago listo para guardar.
     *
     * @param  array<string, mixed>  $data  cash_payment_date, cash_payment_reference, cash_payment_currency, cash_payment_bank, cash_payment_amount, cash_bcv_rate, cash_payment_receipt
     * @return array{payment_date: string, payment_reference: string, payment_currency: string, payment_amount: float, bcv_rate: ?float, amount_in_invoice_currency: float, bank_scope: string, bank: string, receipt_path: string}
     */
    public static function normalize(array $data, float $invoiceAmount, string $invoiceCurrency, mixed $invoiceDate = null): array
    {
        $date = self::date($data['cash_payment_date'] ?? null) ?? throw new InvalidArgumentException('Indica la fecha del pago de contado.');

        if ($date->greaterThan(CarbonImmutable::today())) {
            throw new InvalidArgumentException('La fecha del pago de contado no puede ser posterior a hoy.');
        }

        $emitted = self::date($invoiceDate);

        if ($emitted !== null && $date->lessThan($emitted)) {
            throw new InvalidArgumentException('La fecha del pago de contado no puede ser anterior a la emisión de la factura ('.$emitted->format('d/m/Y').').');
        }

        $reference = Str::squish((string) ($data['cash_payment_reference'] ?? ''));

        if ($reference === '' || mb_strlen($reference) > 120) {
            throw new InvalidArgumentException('Indica la referencia del pago de contado (máximo 120 caracteres).');
        }

        $currency = strtoupper((string) ($data['cash_payment_currency'] ?? ''));

        if (! in_array($currency, ['USD', 'VES'], true)) {
            throw new InvalidArgumentException('Indica si el pago de contado fue en US$ o en bolívares.');
        }

        $bank = BankCatalog::normalizeStoredBank($data['cash_payment_bank'] ?? null) ?? throw new InvalidArgumentException('Indica el banco desde donde se hizo el pago.');
        $amount = is_numeric($data['cash_payment_amount'] ?? null) ? round((float) $data['cash_payment_amount'], 2) : 0.0;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Indica el monto del pago de contado.');
        }

        $receipt = self::path($data['cash_payment_receipt'] ?? null) ?? throw new InvalidArgumentException('Adjunta el comprobante del pago de contado.');

        $rate = null;
        $converted = $amount;

        if ($currency !== $invoiceCurrency) {
            $rate = is_numeric($data['cash_bcv_rate'] ?? null) ? round((float) $data['cash_bcv_rate'], 4) : 0.0;

            if ($rate <= 0) {
                throw new InvalidArgumentException('Indica la tasa BCV del día del pago para convertir el monto a la moneda de la factura.');
            }

            $converted = $invoiceCurrency === 'USD' ? round($amount / $rate, 2) : round($amount * $rate, 2);
        }

        $invoiceAmount = round($invoiceAmount, 2);

        if (abs($converted - $invoiceAmount) > self::TOLERANCE + 0.000001) {
            throw new InvalidArgumentException(
                'El pago de contado debe cubrir el total de la factura: '.self::money($invoiceAmount, $invoiceCurrency)
                .($rate !== null ? '. A la tasa '.number_format($rate, 4, ',', '.').' equivale a '.self::money($invoiceCurrency === 'USD' ? $invoiceAmount * $rate : $invoiceAmount / $rate, $currency).'; el pago indicado da '.self::money($converted, $invoiceCurrency) : '; el pago indicado es '.self::money($amount, $currency))
                .'.'
            );
        }

        return [
            'payment_date' => $date->toDateString(),
            'payment_reference' => $reference,
            'payment_currency' => $currency,
            'payment_amount' => $amount,
            'bcv_rate' => $rate,
            'amount_in_invoice_currency' => $converted,
            'bank_scope' => $currency === 'USD' ? OperationCashPayment::SCOPE_INTERNATIONAL : OperationCashPayment::SCOPE_NATIONAL,
            'bank' => $bank,
            'receipt_path' => $receipt,
        ];
    }

    /**
     * Registra el pago y deja la cuenta por pagar PAGADA. Debe llamarse dentro de la
     * transacción que guarda la factura.
     *
     * @param  array{payment_date: string, payment_reference: string, payment_currency: string, payment_amount: float, bcv_rate: ?float, amount_in_invoice_currency: float, bank_scope: string, bank: string, receipt_path: string}  $payment
     */
    public static function register(OperationServiceOrder $order, OperationAccountsPayable $payable, array $payment, ?User $user, string $actor, ?string $batch = null): OperationCashPayment
    {
        $locked = OperationAccountsPayable::query()->lockForUpdate()->findOrFail($payable->getKey());

        if (OperationCashPayment::query()->where('operation_accounts_payable_id', $locked->getKey())->where('status', OperationCashPayment::STATUS_REGISTERED)->exists()) {
            throw new InvalidArgumentException('Esta factura ya tiene un pago de contado registrado. Para cambiarlo, reverse primero ese pago.');
        }

        if (StatusCuentaPorPagar::fromStored($locked->payment_status) !== StatusCuentaPorPagar::PendientePorPagar) {
            throw new InvalidArgumentException('La cuenta por pagar de esta factura ya no está pendiente: tiene un pago registrado. No se puede pagar dos veces.');
        }

        $snapshot = [];

        foreach (self::PAYABLE_PAYMENT_FIELDS as $field) {
            $snapshot[$field] = $locked->getRawOriginal($field);
        }

        $isUsd = $payment['payment_currency'] === 'USD';

        $locked->forceFill([
            'payment_status' => StatusCuentaPorPagar::Pagada->value,
            'payment_reference' => $payment['payment_reference'],
            'payment_date' => $payment['payment_date'],
            'national_bank' => $isUsd ? null : $payment['bank'],
            'international_bank' => $isUsd ? $payment['bank'] : null,
            'payment_amount_usd' => $isUsd ? $payment['payment_amount'] : null,
            'payment_amount_ves' => $isUsd ? null : $payment['payment_amount'],
            'payment_receipt_path' => $payment['receipt_path'],
            'updated_by' => $actor,
        ])->save();

        return OperationCashPayment::query()->create([
            'operation_service_order_id' => $order->getKey(),
            'operation_accounts_payable_id' => $locked->getKey(),
            'supplier_id' => $locked->supplier_id,
            'supplier_name' => $locked->supplier_name,
            'supplier_rif' => $locked->supplier_rif,
            'invoice_number' => $locked->invoice_number,
            'invoice_receipt_number' => $locked->invoice_receipt_number,
            'invoice_amount' => $locked->invoice_amount,
            'invoice_currency' => $locked->invoice_currency,
            ...$payment,
            'payment_batch' => $batch,
            'status' => OperationCashPayment::STATUS_REGISTERED,
            'payable_snapshot' => $snapshot,
            'registered_by_user_id' => $user?->getKey(),
            'registered_by_name' => $user?->name ?? $actor,
        ]);
    }

    /**
     * Reparte un pago que cubre una factura de varias órdenes, con la misma proporción
     * que la factura. La última parte absorbe el redondeo: la suma cuadra exacta.
     *
     * @template TKey of array-key
     *
     * @param  array{payment_amount: float, amount_in_invoice_currency: float}&array<string, mixed>  $payment  pago validado contra el total de la factura
     * @param  array<TKey, float>  $invoiceShares  parte de la factura de cada orden, en la moneda de la factura
     * @return array<TKey, array<string, mixed>>
     */
    public static function split(array $payment, array $invoiceShares): array
    {
        if ($invoiceShares === []) {
            return [];
        }

        $total = array_sum(array_map(static fn (mixed $share): float => max(0.0, (float) $share), $invoiceShares));
        $keys = array_keys($invoiceShares);
        $lastKey = end($keys);
        $count = count($invoiceShares);
        $assignedPayment = 0.0;
        $assignedInvoice = 0.0;
        $parts = [];

        foreach ($invoiceShares as $key => $share) {
            if ($key === $lastKey) {
                $paymentPart = round((float) $payment['payment_amount'] - $assignedPayment, 2);
                $invoicePart = round((float) $payment['amount_in_invoice_currency'] - $assignedInvoice, 2);
            } else {
                $ratio = $total > 0 ? max(0.0, (float) $share) / $total : 1 / $count;
                $paymentPart = round((float) $payment['payment_amount'] * $ratio, 2);
                $invoicePart = round((float) $payment['amount_in_invoice_currency'] * $ratio, 2);
                $assignedPayment += $paymentPart;
                $assignedInvoice += $invoicePart;
            }

            $parts[$key] = [...$payment, 'payment_amount' => $paymentPart, 'amount_in_invoice_currency' => $invoicePart];
        }

        return $parts;
    }

    /**
     * Pagos activos que se reversan junto con este: él mismo o, si es masivo, todo su grupo.
     *
     * @return Collection<int, OperationCashPayment>
     */
    public static function reversalGroup(OperationCashPayment $payment, bool $lock = false): Collection
    {
        $query = OperationCashPayment::query()->with('serviceOrder:id,order_number');

        if ($payment->isBatch()) {
            $query->where('payment_batch', $payment->payment_batch)->where('status', OperationCashPayment::STATUS_REGISTERED);
        } else {
            $query->whereKey($payment->getKey());
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Si el pago (y, si es masivo, cada parte de su grupo) se puede reversar; si no, por qué.
     */
    public static function reversalBlocker(OperationCashPayment $payment): ?string
    {
        if (! $payment->isBatch()) {
            return self::singleReversalBlocker($payment);
        }

        if (! $payment->isActive()) {
            return self::singleReversalBlocker($payment);
        }

        foreach (self::reversalGroup($payment) as $member) {
            $blocker = self::singleReversalBlocker($member);

            if ($blocker !== null) {
                return 'Orden '.($member->serviceOrder?->order_number ?: '#'.$member->operation_service_order_id).': '.$blocker;
            }
        }

        return null;
    }

    private static function singleReversalBlocker(OperationCashPayment $payment): ?string
    {
        if (! $payment->isActive()) {
            return 'Este pago ya fue reversado el '.$payment->reversed_at?->format('d/m/Y h:i A').' por '.$payment->reversed_by_name.'.';
        }

        $payable = OperationAccountsPayable::query()->find($payment->operation_accounts_payable_id);

        if (! $payable instanceof OperationAccountsPayable) {
            return 'La cuenta por pagar de este pago ya no existe.';
        }

        /** Solo se reversa si la cuenta por pagar sigue con los datos que dejó este pago. */
        $expected = [
            'payment_reference' => $payment->payment_reference,
            'payment_date' => $payment->payment_date?->toDateString(),
            'payment_receipt_path' => $payment->receipt_path,
        ];

        foreach ($expected as $field => $value) {
            $current = $field === 'payment_date' ? $payable->payment_date?->toDateString() : $payable->getAttribute($field);

            if ((string) $current !== (string) $value) {
                return 'La cuenta por pagar cambió después de este pago (por ejemplo, se cargó otro comprobante). Revise la cuenta por pagar antes de reversar.';
            }
        }

        return null;
    }

    public static function reverse(OperationCashPayment $payment, string $reason, User $user): OperationCashPayment
    {
        $reason = Str::squish($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('Indica la razón de la reversión (mínimo '.self::MIN_REASON_LENGTH.' caracteres).');
        }

        return DB::transaction(function () use ($payment, $reason, $user): OperationCashPayment {
            $locked = OperationCashPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            /** Un pago masivo se reversa completo: nunca queda una factura medio pagada. */
            $group = $locked->isBatch() && $locked->isActive()
                ? self::reversalGroup($locked, lock: true)
                : collect([$locked]);

            OperationAccountsPayable::query()->whereIn('id', $group->pluck('operation_accounts_payable_id'))->lockForUpdate()->get();

            $blocker = self::reversalBlocker($locked);

            if ($blocker !== null) {
                throw new InvalidArgumentException($blocker);
            }

            foreach ($group as $member) {
                self::reverseOne($member, $reason, $user, $group->count());
            }

            return $locked->refresh();
        });
    }

    private static function reverseOne(OperationCashPayment $locked, string $reason, User $user, int $groupSize): void
    {
        $payable = OperationAccountsPayable::query()->findOrFail($locked->operation_accounts_payable_id);
        $snapshot = (array) $locked->payable_snapshot;
        $restore = [];

        foreach (self::PAYABLE_PAYMENT_FIELDS as $field) {
            $restore[$field] = $snapshot[$field] ?? null;
        }

        $restore['payment_status'] = $restore['payment_status'] ?: StatusCuentaPorPagar::PendientePorPagar->value;
        $payable->setRawAttributes(array_merge($payable->getAttributes(), $restore, ['updated_by' => $user->name]));
        $payable->save();

        $locked->forceFill([
            'status' => OperationCashPayment::STATUS_REVERSED,
            'reversed_at' => now(),
            'reversed_by_user_id' => $user->getKey(),
            'reversed_by_name' => $user->name,
            'reversal_reason' => Str::limit($reason, 2000, ''),
        ])->save();

        SecurityAudit::log('AUDIT_OPERATION_CASH_PAYMENT_REVERSED', 'operations.cash-payments.reverse', [
            'cash_payment_id' => $locked->getKey(),
            'accounts_payable_id' => $payable->getKey(),
            'service_order_id' => $locked->operation_service_order_id,
            'payment_batch' => $locked->payment_batch,
            'batch_size' => $groupSize,
            'reason' => $reason,
        ], $user);
    }

    public static function money(float $amount, string $currency): string
    {
        return ($currency === 'USD' ? 'US$ ' : 'Bs. ').number_format($amount, 2, ',', '.');
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private static function path(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
