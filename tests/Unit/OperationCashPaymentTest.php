<?php

declare(strict_types=1);

use App\Enums\StatusCuentaPorPagar;
use App\Filament\Operations\Resources\OperationCashPayments\Pages\ListOperationCashPayments;
use App\Models\DataChangeAudit;
use App\Models\OperationAccountsPayable;
use App\Models\OperationCashPayment;
use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\Operations\CashPayments\CashPaymentService;
use App\Support\Operations\ServiceOrderAccountsPayableRegistrar;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe la factura, su cuenta por pagar y el pago: todo dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    test()->actingAs(cashPaymentUser());
});

afterEach(fn () => DB::rollBack());

function cashPaymentUser(): User
{
    return User::factory()->make(['id' => 999_999_401, 'name' => 'QA CONTADO', 'email' => 'qa.contado@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'OPERACIONES', 'ADMINISTRACION']]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cashPaymentForm(array $overrides = []): array
{
    return [
        'cash_payment_date' => CarbonImmutable::today()->toDateString(),
        'cash_payment_reference' => '001234567890',
        'cash_payment_currency' => 'USD',
        'cash_payment_bank' => 'BANK OF AMERICA',
        'cash_payment_amount' => 100,
        'cash_bcv_rate' => null,
        'cash_payment_receipt' => 'operation-accounts-payables/payment-receipts/pest.pdf',
        ...$overrides,
    ];
}

/**
 * @return array{order: OperationServiceOrder, payable: OperationAccountsPayable}
 */
function cashPaymentInvoice(float $usd = 100.0): array
{
    $order = OperationServiceOrder::query()->whereNotNull('supplier_id')->first();

    if (! $order instanceof OperationServiceOrder) {
        test()->markTestSkipped('No hay órdenes de servicio con proveedor en la base.');
    }

    OperationCashPayment::query()->where('operation_service_order_id', $order->getKey())->delete();
    OperationAccountsPayable::query()->where('operation_service_order_id', $order->getKey())->delete();

    $result = ServiceOrderAccountsPayableRegistrar::register($order, [
        'invoice_number' => 'PEST-'.uniqid(),
        'invoice_receipt_number' => null,
        'invoice_control_number' => null,
        'invoice_date' => CarbonImmutable::today()->subDays(2)->toDateString(),
        'invoice_registration_date' => CarbonImmutable::today()->toDateString(),
        'invoice_amount_usd' => $usd,
        'invoice_amount_ves' => null,
        'invoice_file_path' => 'operation-service-orders/invoices/pest.pdf',
        'supplier_name' => 'PROVEEDOR PRUEBA',
        'supplier_rif' => 'J-000000000',
        'business_unit_id' => null,
    ], 'QA');

    return ['order' => $order, 'payable' => $result['payable']];
}

it('el pago de contado debe cubrir el total de la factura, en su moneda o convertido a la tasa BCV', function (): void {
    expect(CashPaymentService::normalize(cashPaymentForm(), 100.0, 'USD'))->toMatchArray([
        'payment_currency' => 'USD', 'payment_amount' => 100.0, 'bcv_rate' => null, 'amount_in_invoice_currency' => 100.0, 'bank_scope' => OperationCashPayment::SCOPE_INTERNATIONAL,
    ])
        ->and(CashPaymentService::normalize(cashPaymentForm(['cash_payment_currency' => 'VES', 'cash_payment_bank' => 'BANESCO', 'cash_payment_amount' => 3650, 'cash_bcv_rate' => 36.5]), 100.0, 'USD'))
        ->toMatchArray(['payment_currency' => 'VES', 'bcv_rate' => 36.5, 'amount_in_invoice_currency' => 100.0, 'bank_scope' => OperationCashPayment::SCOPE_NATIONAL, 'bank' => 'BANESCO'])
        ->and(fn () => CashPaymentService::normalize(cashPaymentForm(['cash_payment_amount' => 60]), 100.0, 'USD'))->toThrow(InvalidArgumentException::class, 'debe cubrir el total de la factura')
        ->and(fn () => CashPaymentService::normalize(cashPaymentForm(['cash_payment_currency' => 'VES', 'cash_payment_amount' => 3650]), 100.0, 'USD'))->toThrow(InvalidArgumentException::class, 'tasa BCV')
        ->and(fn () => CashPaymentService::normalize(cashPaymentForm(['cash_payment_date' => CarbonImmutable::tomorrow()->toDateString()]), 100.0, 'USD'))->toThrow(InvalidArgumentException::class, 'posterior a hoy')
        ->and(fn () => CashPaymentService::normalize(cashPaymentForm(), 100.0, 'USD', CarbonImmutable::tomorrow()->toDateString()))->toThrow(InvalidArgumentException::class, 'anterior a la emisión')
        ->and(fn () => CashPaymentService::normalize(cashPaymentForm(['cash_payment_receipt' => null]), 100.0, 'USD'))->toThrow(InvalidArgumentException::class, 'comprobante')
        ->and(fn () => CashPaymentService::normalize(cashPaymentForm(['cash_payment_reference' => ' ']), 100.0, 'USD'))->toThrow(InvalidArgumentException::class, 'referencia')
        ->and(fn () => CashPaymentService::normalize(cashPaymentForm(['cash_payment_bank' => null]), 100.0, 'USD'))->toThrow(InvalidArgumentException::class, 'banco');
});

it('registrar el pago deja la cuenta por pagar PAGADA y no permite pagar dos veces', function (): void {
    ['order' => $order, 'payable' => $payable] = cashPaymentInvoice();
    $payment = CashPaymentService::register($order, $payable, CashPaymentService::normalize(cashPaymentForm(), 100.0, 'USD'), cashPaymentUser(), 'QA');
    $payable = $payable->fresh();

    expect($payment->status)->toBe(OperationCashPayment::STATUS_REGISTERED)
        ->and($payment->payable_snapshot['payment_status'])->toBe(StatusCuentaPorPagar::PendientePorPagar->value)
        ->and($payable->payment_status)->toBe(StatusCuentaPorPagar::Pagada)
        ->and($payable->payment_reference)->toBe('001234567890')
        ->and($payable->international_bank)->toBe('BANK OF AMERICA')
        ->and($payable->national_bank)->toBeNull()
        ->and((float) $payable->payment_amount_usd)->toBe(100.0)
        ->and($payable->payment_receipt_path)->toBe('operation-accounts-payables/payment-receipts/pest.pdf')
        ->and(fn () => CashPaymentService::register($order, $payable, CashPaymentService::normalize(cashPaymentForm(), 100.0, 'USD'), cashPaymentUser(), 'QA'))
        ->toThrow(InvalidArgumentException::class, 'ya tiene un pago de contado');
});

it('reversar deja el pago REVERSADO con su razón y la cuenta por pagar exactamente como estaba', function (): void {
    ['order' => $order, 'payable' => $payable] = cashPaymentInvoice();
    $payment = CashPaymentService::register($order, $payable, CashPaymentService::normalize(cashPaymentForm(), 100.0, 'USD'), cashPaymentUser(), 'QA');

    expect(fn () => CashPaymentService::reverse($payment, 'corta', cashPaymentUser()))->toThrow(InvalidArgumentException::class, 'razón');

    $reversed = CashPaymentService::reverse($payment, 'El pago se registró en la orden equivocada', cashPaymentUser());
    $payable = $payable->fresh();

    expect($reversed->status)->toBe(OperationCashPayment::STATUS_REVERSED)
        ->and($reversed->reversal_reason)->toBe('El pago se registró en la orden equivocada')
        ->and($reversed->reversed_by_name)->toBe('QA CONTADO')
        ->and(OperationCashPayment::query()->find($payment->getKey()))->not->toBeNull()
        ->and($payable->payment_status)->toBe(StatusCuentaPorPagar::PendientePorPagar)
        ->and($payable->payment_reference)->toBeNull()
        ->and($payable->international_bank)->toBeNull()
        ->and($payable->payment_amount_usd)->toBeNull()
        ->and($payable->payment_receipt_path)->toBeNull()
        ->and(fn () => CashPaymentService::reverse($reversed, 'Segundo intento de prueba', cashPaymentUser()))->toThrow(InvalidArgumentException::class, 'ya fue reversado');

    /** Reversado, la factura se puede volver a pagar de contado. */
    $again = CashPaymentService::register($order, $payable, CashPaymentService::normalize(cashPaymentForm(['cash_payment_reference' => '999']), 100.0, 'USD'), cashPaymentUser(), 'QA');

    expect($again->status)->toBe(OperationCashPayment::STATUS_REGISTERED);
});

it('no se reversa si la cuenta por pagar cambió después del pago', function (): void {
    ['order' => $order, 'payable' => $payable] = cashPaymentInvoice();
    $payment = CashPaymentService::register($order, $payable, CashPaymentService::normalize(cashPaymentForm(), 100.0, 'USD'), cashPaymentUser(), 'QA');
    $payable->fresh()->update(['payment_reference' => 'OTRA-REFERENCIA']);

    expect(CashPaymentService::reversalBlocker($payment))->toContain('cambió después de este pago')
        ->and(fn () => CashPaymentService::reverse($payment, 'Intento con conflicto de prueba', cashPaymentUser()))->toThrow(InvalidArgumentException::class, 'cambió después');
});

it('el pago y su efecto en la cuenta por pagar quedan en la auditoría de cambios', function (): void {
    ['order' => $order, 'payable' => $payable] = cashPaymentInvoice();
    $payment = CashPaymentService::register($order, $payable, CashPaymentService::normalize(cashPaymentForm(), 100.0, 'USD'), cashPaymentUser(), 'QA');

    $payableAudit = DataChangeAudit::query()->where('entity', 'operation_accounts_payable')->where('entity_id', $payable->getKey())->where('event', 'updated')->latest('id')->first();

    expect(DataChangeAudit::query()->where('entity', 'operation_cash_payment')->where('entity_id', $payment->getKey())->where('event', 'created')->exists())->toBeTrue()
        ->and($payableAudit?->changes['payment_status'])->toEqual(['old' => StatusCuentaPorPagar::PendientePorPagar->value, 'new' => StatusCuentaPorPagar::Pagada->value])
        ->and($payableAudit?->affects_calculations)->toBeTrue();
});

it('la tabla de pagos de contado lista el pago y lo reversa con razón desde la pantalla', function (): void {
    Filament::setCurrentPanel('operations');
    ['order' => $order, 'payable' => $payable] = cashPaymentInvoice();
    $payment = CashPaymentService::register($order, $payable, CashPaymentService::normalize(cashPaymentForm(), 100.0, 'USD'), cashPaymentUser(), 'QA');

    Livewire::test(ListOperationCashPayments::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$payment])
        ->mountTableAction('reverse', $payment)
        ->set('mountedActions.0.data.reason', 'Prueba de reversión desde la pantalla')
        ->callMountedTableAction()
        ->assertNotified('Pago reversado');

    expect($payment->fresh()->status)->toBe(OperationCashPayment::STATUS_REVERSED);
});

it('la modal de factura trae la sección de pago de contado y el pago se valida antes de guardar', function (): void {
    $table = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');
    $section = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/ServiceOrderCashPaymentSection.php');

    expect($table)
        ->toContain('ServiceOrderCashPaymentSection::make($record, self::IOS_SECTION_CLASS)')
        ->toContain('CashPaymentService::normalize(ServiceOrderCashPaymentSection::paymentData($data)')
        ->toContain('CashPaymentService::register($record, $result[\'payable\']')
        ->and($section)
        ->toContain("Toggle::make('cash_paid')")
        ->toContain('BankCatalog::configureInternationalSelect')
        ->toContain('BankCatalog::configureNationalSelect')
        ->toContain("TextInput::make('cash_bcv_rate')")
        ->toContain("FileUpload::make('cash_payment_receipt')");
});
