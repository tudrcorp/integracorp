<?php

declare(strict_types=1);

use App\Enums\StatusCuentaPorPagar;
use App\Filament\Operations\Resources\OperationCashPayments\Tables\OperationCashPaymentsTable;
use App\Filament\Operations\Resources\OperationServiceOrders\Schemas\ServiceOrderCashPaymentSection;
use App\Models\OperationAccountsPayable;
use App\Models\OperationCashPayment;
use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\Operations\CashPayments\CashPaymentService;
use App\Support\Operations\ServiceOrderBulkInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Pago de contado en la carga masiva de facturas. Escribe facturas, cuentas por pagar
 * y pagos: todo dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    test()->actingAs(bulkCashUser());
});

afterEach(fn () => DB::rollBack());

function bulkCashUser(): User
{
    return User::factory()->make(['id' => 999_999_402, 'name' => 'QA CONTADO MASIVO', 'email' => 'qa.contado.masivo@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'OPERACIONES', 'ADMINISTRACION']]);
}

/**
 * Órdenes del mismo proveedor sin cuenta por pagar (no se toca ninguna factura real).
 *
 * @return Collection<int, OperationServiceOrder>
 */
function bulkCashOrders(int $count = 3, int $skipSuppliers = 0): Collection
{
    $supplierId = DB::table('operation_service_orders as o')
        ->whereNotNull('supplier_id')
        ->whereNotExists(fn ($query) => $query->from('operation_accounts_payables as p')->whereColumn('p.operation_service_order_id', 'o.id'))
        ->selectRaw('supplier_id, count(*) as total')
        ->groupBy('supplier_id')
        ->having('total', '>=', $count)
        ->orderByDesc('total')
        ->skip($skipSuppliers)
        ->value('supplier_id');

    if ($supplierId === null) {
        test()->markTestSkipped('No hay órdenes suficientes de un mismo proveedor sin cuenta por pagar.');
    }

    return OperationServiceOrder::query()
        ->where('supplier_id', $supplierId)
        ->whereNotExists(fn ($query) => $query->from('operation_accounts_payables as p')->whereColumn('p.operation_service_order_id', 'operation_service_orders.id'))
        ->with(['supplier', 'telemedicineSupplier', 'approvedOperationQuote', 'operationCoordinationService'])
        ->limit($count)
        ->get();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bulkCashData(array $overrides = []): array
{
    return [
        'invoice_number' => 'PEST-MASIVA-'.uniqid(),
        'invoice_control_number' => null,
        'invoice_date' => CarbonImmutable::today()->subDay()->toDateString(),
        'invoice_registration_date' => CarbonImmutable::today()->toDateString(),
        'invoice_amount_usd' => 100.00,
        'invoice_amount_ves' => null,
        'invoice_file_path' => 'operation-service-orders/invoices/pest.pdf',
        'payable_supplier_name' => 'PROVEEDOR PRUEBA',
        'payable_supplier_rif' => 'J-000000000',
        'payable_business_unit_id' => null,
        'cash_paid' => true,
        'cash_payment_date' => CarbonImmutable::today()->toDateString(),
        'cash_payment_reference' => 'PEST-REF-001',
        'cash_payment_currency' => 'USD',
        'cash_payment_bank_international' => 'BANK OF AMERICA',
        'cash_payment_amount' => 100.00,
        'cash_bcv_rate' => null,
        'cash_payment_receipt' => 'operation-accounts-payables/payment-receipts/pest.pdf',
        ...$overrides,
    ];
}

it('reparte el pago con la misma proporción de la factura y la última parte absorbe el redondeo', function (): void {
    $parts = CashPaymentService::split(['payment_amount' => 100.0, 'amount_in_invoice_currency' => 100.0], [10 => 33.3333, 20 => 33.3333, 30 => 33.3334]);

    expect(array_column($parts, 'payment_amount'))->toBe([33.33, 33.33, 33.34])
        ->and(round(array_sum(array_column($parts, 'payment_amount')), 2))->toBe(100.0)
        ->and(CashPaymentService::split(['payment_amount' => 10.0, 'amount_in_invoice_currency' => 10.0], [5 => 10.0])[5]['payment_amount'])->toBe(10.0);
});

it('la factura masiva pagada de contado deja todas sus cuentas PAGADAS y un solo pago masivo', function (): void {
    $orders = bulkCashOrders();

    $result = ServiceOrderBulkInvoice::apply($orders, bulkCashData(), 'QA', bulkCashUser());

    $payables = OperationAccountsPayable::query()->whereIn('operation_service_order_id', $orders->modelKeys())->get();
    $payments = OperationCashPayment::query()->whereIn('operation_service_order_id', $orders->modelKeys())->get();

    expect($result['cash_payments'])->toBe(3)
        ->and($result['cash_reference'])->toBe('PEST-REF-001')
        ->and($payables->every(fn (OperationAccountsPayable $payable): bool => StatusCuentaPorPagar::fromStored($payable->payment_status) === StatusCuentaPorPagar::Pagada))->toBeTrue()
        ->and($payables->pluck('payment_reference')->unique()->all())->toBe(['PEST-REF-001'])
        ->and($payments)->toHaveCount(3)
        ->and($payments->pluck('payment_batch')->unique())->toHaveCount(1)
        ->and(filled($payments->first()->payment_batch))->toBeTrue()
        ->and(round((float) $payments->sum(fn (OperationCashPayment $payment): float => (float) $payment->payment_amount), 2))->toBe(100.0);
});

it('si el pago no cubre el total no se guarda ni la factura ni el pago', function (): void {
    $orders = bulkCashOrders();

    expect(fn () => ServiceOrderBulkInvoice::apply($orders, bulkCashData(['cash_payment_amount' => 90]), 'QA', bulkCashUser()))
        ->toThrow(InvalidArgumentException::class, 'debe cubrir el total');

    expect(OperationAccountsPayable::query()->whereIn('operation_service_order_id', $orders->modelKeys())->count())->toBe(0);
});

it('no permite el pago de contado masivo si alguna orden ya tiene un pago', function (): void {
    $orders = bulkCashOrders();
    ServiceOrderBulkInvoice::apply($orders, bulkCashData(), 'QA', bulkCashUser());

    expect(ServiceOrderBulkInvoice::cashPaymentBlockers($orders))->toHaveCount(3)
        ->and(fn () => ServiceOrderBulkInvoice::apply($orders, bulkCashData(['cash_payment_reference' => 'PEST-REF-002']), 'QA', bulkCashUser()))
        ->toThrow(InvalidArgumentException::class, 'ya tienen un pago registrado');
});

it('reversar cualquier parte reversa el pago masivo completo', function (): void {
    $orders = bulkCashOrders();
    ServiceOrderBulkInvoice::apply($orders, bulkCashData(), 'QA', bulkCashUser());
    $payments = OperationCashPayment::query()->whereIn('operation_service_order_id', $orders->modelKeys())->orderBy('id')->get();

    expect(OperationCashPaymentsTable::reversalDescription($payments->first()))->toContain('3 órdenes')
        ->toContain('se reversa completo');

    CashPaymentService::reverse($payments->last(), 'Prueba de reversión masiva', bulkCashUser());

    $payables = OperationAccountsPayable::query()->whereIn('operation_service_order_id', $orders->modelKeys())->get();

    expect(OperationCashPayment::query()->whereIn('id', $payments->modelKeys())->where('status', OperationCashPayment::STATUS_REVERSED)->count())->toBe(3)
        ->and($payables->every(fn (OperationAccountsPayable $payable): bool => StatusCuentaPorPagar::fromStored($payable->payment_status) === StatusCuentaPorPagar::PendientePorPagar && blank($payable->payment_reference)))->toBeTrue();
});

it('si una parte cambió después del pago no se reversa ninguna', function (): void {
    $orders = bulkCashOrders(2);
    ServiceOrderBulkInvoice::apply($orders, bulkCashData(), 'QA', bulkCashUser());
    $payments = OperationCashPayment::query()->whereIn('operation_service_order_id', $orders->modelKeys())->orderBy('id')->get();

    OperationAccountsPayable::query()->whereKey($payments->last()->operation_accounts_payable_id)->update(['payment_reference' => 'OTRA-REFERENCIA']);

    expect(fn () => CashPaymentService::reverse($payments->first(), 'Prueba de bloqueo de reversión', bulkCashUser()))
        ->toThrow(InvalidArgumentException::class, 'cambió después de este pago');

    expect(OperationCashPayment::query()->whereIn('id', $payments->modelKeys())->where('status', OperationCashPayment::STATUS_REGISTERED)->count())->toBe(2);
});

it('un pago en bolívares con tasa cubre una factura masiva en US$', function (): void {
    $orders = bulkCashOrders(2);

    ServiceOrderBulkInvoice::apply($orders, bulkCashData([
        'invoice_amount_usd' => 50,
        'cash_payment_currency' => 'VES',
        'cash_payment_bank_national' => 'BANCO DE VENEZUELA',
        'cash_payment_amount' => 12000,
        'cash_bcv_rate' => 240,
    ]), 'QA', bulkCashUser());

    $payments = OperationCashPayment::query()->whereIn('operation_service_order_id', $orders->modelKeys())->get();

    expect($payments)->toHaveCount(2)
        ->and(round((float) $payments->sum(fn (OperationCashPayment $payment): float => (float) $payment->payment_amount), 2))->toBe(12000.0)
        ->and(round((float) $payments->sum(fn (OperationCashPayment $payment): float => (float) $payment->amount_in_invoice_currency), 2))->toBe(50.0);
});

it('la modal masiva trae la sección de pago de contado bloqueable', function (): void {
    $actions = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Actions/ServiceOrderBulkInvoiceActions.php');
    $section = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Schemas/ServiceOrderCashPaymentSection.php');

    expect($actions)
        ->toContain('ServiceOrderCashPaymentSection::makeForBulk($records->count(), ServiceOrderBulkInvoice::cashPaymentBlockers($records)')
        ->toContain("ServiceOrderBulkInvoice::apply(\$records, \$data, \$actor, \$user instanceof User ? \$user : null)")
        ->and($section)
        ->toContain('public static function makeForBulk(')
        ->toContain('->disabled($blockers !== [])')
        ->toContain('self::paymentFields()');
});

it('el monto del pago se rellena con el total de la factura en la moneda del pago', function (): void {
    $state = ServiceOrderCashPaymentSection::initialState(77.294);

    expect($state['cash_payment_amount'])->toBe(77.29)
        ->and($state['cash_payment_currency'])->toBe('USD')
        ->and($state['cash_paid'])->toBeFalse()
        ->and($state['cash_payment_date'])->toBe(now()->toDateString())
        ->and(ServiceOrderCashPaymentSection::initialState(0)['cash_payment_amount'])->toBeNull()
        ->and(ServiceOrderCashPaymentSection::invoiceTotalInCurrency(120.5, null, 'USD', null))->toBe(120.5)
        ->and(ServiceOrderCashPaymentSection::invoiceTotalInCurrency(120.5, null, 'VES', 240))->toBe(28920.0)
        ->and(ServiceOrderCashPaymentSection::invoiceTotalInCurrency(null, 2400, 'USD', 240))->toBe(10.0)
        ->and(ServiceOrderCashPaymentSection::invoiceTotalInCurrency(50, null, 'VES', null))->toBeNull();
});

it('la modal masiva sugiere como pago la suma de los costos y lo recalcula al cambiar la factura', function (): void {
    $actions = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Actions/ServiceOrderBulkInvoiceActions.php');

    expect($actions)
        ->toContain('...ServiceOrderCashPaymentSection::initialState($serviceCostTotal > 0 ? $serviceCostTotal : null)')
        ->toContain('ServiceOrderCashPaymentSection::syncPaymentAmount($get, $set)');
});
