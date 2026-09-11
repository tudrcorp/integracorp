<?php

declare(strict_types=1);

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Models\OperationServiceOrder;
use App\Support\Operations\AccountsPayableInvoicePreview;
use App\Support\Operations\ServiceOrderAccountsPayableRegistrar;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function facturaDeOrden(array $overrides = []): array
{
    return array_merge([
        'invoice_number' => 'F-9001',
        'invoice_control_number' => '00-12345',
        'invoice_date' => '2026-09-01',
        'invoice_registration_date' => '2026-09-05',
        'invoice_amount_usd' => 250.50,
        'invoice_amount_ves' => null,
        'invoice_file_path' => 'operation-service-orders/invoices/prueba.pdf',
        'supplier_name' => 'CLINICA QA',
        'supplier_rif' => 'J-30011122-3',
        'business_unit_id' => 1,
    ], $overrides);
}

function ordenDePrueba(): OperationServiceOrder
{
    return OperationServiceOrder::query()->firstOrFail();
}

/*
 * ---------------------------------------------------------------------------
 * Regla del monto
 * ---------------------------------------------------------------------------
 */

it('toma el monto en US$ cuando la factura trae un importe real', function (): void {
    expect(ServiceOrderAccountsPayableRegistrar::resolveAmount([
        'invoice_amount_usd' => 120.5, 'invoice_amount_ves' => 4400.0,
    ]))->toBe([120.5, 'USD']);
});

it('cae a bolívares cuando sólo hay monto en Bs.', function (): void {
    expect(ServiceOrderAccountsPayableRegistrar::resolveAmount([
        'invoice_amount_usd' => null, 'invoice_amount_ves' => 4400.0,
    ]))->toBe([4400.0, 'VES']);
});

it('cae a bolívares cuando el importe en US$ viene en cero', function (): void {
    expect(ServiceOrderAccountsPayableRegistrar::resolveAmount([
        'invoice_amount_usd' => 0, 'invoice_amount_ves' => 813.74,
    ]))->toBe([813.74, 'VES']);
});

it('conserva el cero en US$ cuando no hay bolívares', function (): void {
    expect(ServiceOrderAccountsPayableRegistrar::resolveAmount([
        'invoice_amount_usd' => 0, 'invoice_amount_ves' => null,
    ]))->toBe([0.0, 'USD']);
});

/*
 * ---------------------------------------------------------------------------
 * Registro desde la orden
 * ---------------------------------------------------------------------------
 */

it('crea la cuenta por pagar en PENDIENTE POR PAGAR al cargar la factura', function (): void {
    $orden = ordenDePrueba();
    OperationAccountsPayable::query()->where('operation_service_order_id', $orden->getKey())->delete();

    $resultado = ServiceOrderAccountsPayableRegistrar::register($orden, facturaDeOrden(), 'Analista QA');

    expect($resultado['created'])->toBeTrue()
        ->and($resultado['payment_preserved'])->toBeFalse();

    $cuenta = $resultado['payable'];

    expect($cuenta->payment_status)->toBe(StatusCuentaPorPagar::PendientePorPagar)
        ->and($cuenta->operation_service_order_id)->toBe($orden->getKey())
        ->and($cuenta->invoice_number)->toBe('F-9001')
        ->and($cuenta->invoice_control_number)->toBe('00-12345')
        ->and($cuenta->supplier_name)->toBe('CLINICA QA')
        ->and($cuenta->supplier_rif)->toBe('J-30011122-3')
        ->and($cuenta->invoice_currency)->toBe('USD')
        ->and($cuenta->created_by)->toBe('Analista QA')
        ->and($cuenta->hasInvoiceDocument())->toBeTrue();
});

it('no duplica la cuenta por pagar cuando se actualiza la factura', function (): void {
    $orden = ordenDePrueba();
    OperationAccountsPayable::query()->where('operation_service_order_id', $orden->getKey())->delete();

    $primero = ServiceOrderAccountsPayableRegistrar::register($orden, facturaDeOrden(), 'Analista QA');
    $segundo = ServiceOrderAccountsPayableRegistrar::register($orden, facturaDeOrden(['invoice_number' => 'F-9002']), 'Analista QA');

    expect($segundo['created'])->toBeFalse()
        ->and($segundo['payable']->getKey())->toBe($primero['payable']->getKey())
        ->and($segundo['payable']->invoice_number)->toBe('F-9002');

    expect(OperationAccountsPayable::query()->where('operation_service_order_id', $orden->getKey())->count())->toBe(1);
});

it('respeta el pago ya registrado al corregir la factura', function (): void {
    $orden = ordenDePrueba();
    OperationAccountsPayable::query()->where('operation_service_order_id', $orden->getKey())->delete();

    $cuenta = ServiceOrderAccountsPayableRegistrar::register($orden, facturaDeOrden(), 'Analista QA')['payable'];

    $cuenta->update([
        'payment_status' => StatusCuentaPorPagar::Pagada->value,
        'payment_reference' => 'REF-777',
        'payment_date' => '2026-09-08',
        'national_bank' => 'BANESCO',
        'payment_amount_usd' => 250.50,
    ]);

    $resultado = ServiceOrderAccountsPayableRegistrar::register(
        $orden,
        facturaDeOrden(['invoice_number' => 'F-CORREGIDA', 'invoice_amount_usd' => 300]),
        'Otro Analista'
    );

    expect($resultado['payment_preserved'])->toBeTrue();

    $recargada = $resultado['payable']->refresh();

    expect($recargada->invoice_number)->toBe('F-CORREGIDA')
        ->and($recargada->invoice_amount)->toBe('300.0000')
        ->and($recargada->payment_status)->toBe(StatusCuentaPorPagar::Pagada)
        ->and($recargada->payment_reference)->toBe('REF-777')
        ->and($recargada->national_bank)->toBe('BANESCO')
        ->and($recargada->payment_amount_usd)->toBe('250.5000');
});

it('sugiere proveedor, RIF y unidad de negocio desde la orden', function (): void {
    $orden = OperationServiceOrder::query()
        ->whereNotNull('supplier_id')
        ->with(['supplier', 'operationCoordinationService'])
        ->firstOrFail();

    expect(ServiceOrderAccountsPayableRegistrar::suggestedSupplierName($orden))->not->toBe('')
        ->and(ServiceOrderAccountsPayableRegistrar::suggestedSupplierRif($orden))->toBeString();
});

/*
 * ---------------------------------------------------------------------------
 * Colores del estatus
 * ---------------------------------------------------------------------------
 */

it('pinta pendiente por pagar en rojo, en gestión en naranja y pagada en verde', function (): void {
    expect(StatusCuentaPorPagar::PendientePorPagar->filamentColor())->toBe('danger')
        ->and(StatusCuentaPorPagar::EnGestion->filamentColor())->toBe('warning')
        ->and(StatusCuentaPorPagar::Pagada->filamentColor())->toBe('success');
});

it('mantiene PENDIENTE como alias del valor anterior', function (): void {
    expect(StatusCuentaPorPagar::fromStored('PENDIENTE'))->toBe(StatusCuentaPorPagar::PendientePorPagar);
});

/*
 * ---------------------------------------------------------------------------
 * Vista previa
 * ---------------------------------------------------------------------------
 */

it('incrusta el PDF de la factura en la vista previa', function (): void {
    $cuenta = OperationAccountsPayable::factory()->make([
        'invoice_file_path' => 'operation-service-orders/invoices/demo.pdf',
        'invoice_number' => 'F-1',
    ]);

    $html = AccountsPayableInvoicePreview::render($cuenta)->toHtml();

    expect(AccountsPayableInvoicePreview::isPdf($cuenta))->toBeTrue()
        ->and($html)->toContain('<iframe')
        ->and($html)->toContain('demo.pdf')
        // el alto va en línea porque el panel de Operaciones no compila tema propio
        ->and($html)->toContain('height:78vh')
        ->and($html)->toContain('min-height:560px')
        ->and($html)->not->toContain('class="');
});

it('muestra la imagen cuando la factura no es PDF', function (): void {
    $cuenta = OperationAccountsPayable::factory()->make([
        'invoice_file_path' => 'operation-service-orders/invoices/demo.jpg',
        'invoice_number' => 'F-2',
    ]);

    $html = AccountsPayableInvoicePreview::render($cuenta)->toHtml();

    expect(AccountsPayableInvoicePreview::isPdf($cuenta))->toBeFalse()
        ->and($html)->toContain('<img')
        ->and($html)->toContain('max-height:76vh')
        ->and($html)->not->toContain('<iframe')
        ->and($html)->not->toContain('class="');
});

it('avisa cuando la cuenta por pagar no tiene documento adjunto', function (): void {
    $cuenta = OperationAccountsPayable::factory()->make(['invoice_file_path' => null]);

    expect(AccountsPayableInvoicePreview::url($cuenta))->toBeNull()
        ->and(AccountsPayableInvoicePreview::render($cuenta)->toHtml())->toContain('no tiene documento');
});

/*
 * ---------------------------------------------------------------------------
 * La acción de la orden de servicio
 * ---------------------------------------------------------------------------
 */

it('la acción de carga de factura pide los datos de cuentas por pagar', function (): void {
    $tabla = file_get_contents(base_path('app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php'));

    expect($tabla)
        ->toContain('Datos para cuentas por pagar')
        ->toContain("make('payable_supplier_name')")
        ->toContain("make('payable_supplier_rif')")
        ->toContain("make('payable_business_unit_id')")
        ->toContain('ServiceOrderAccountsPayableRegistrar::register')
        ->toContain('DB::transaction');
});

it('abre el visor a ancho 7xl y sin rótulo sobrante', function (): void {
    $src = file_get_contents(base_path('app/Support/Operations/AccountsPayableInvoicePreview.php'));

    expect($src)
        ->toContain('Width::SevenExtraLarge')
        ->toContain('->hiddenLabel()')
        ->not->toContain("->label('')");
});
