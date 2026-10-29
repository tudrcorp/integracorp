<?php

declare(strict_types=1);

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\TelemedicinePatient;
use App\Services\AccountsPayableSupplierReportXlsxService;
use App\Support\Operations\AccountsPayableSupplierReport;
use Carbon\CarbonImmutable;

uses(Tests\TestCase::class);

/**
 * Factura en memoria (nunca se guarda) con su orden, ítems y paciente.
 *
 * @param  array<string, mixed>  $attributes
 * @param  list<string>|null  $items  null = carga manual (sin orden)
 */
function cxpRecord(array $attributes = [], ?array $items = ['HEMATOLOGIA COMPLETA (HC)', 'UROANALISIS'], string $orderNumber = 'ORD-0063', string $serviceType = 'LABORATORIOS', ?string $unit = 'PQQ'): OperationAccountsPayable
{
    static $id = 0;
    $id++;

    $record = new OperationAccountsPayable;
    $record->setRawAttributes(array_merge([
        'id' => $id,
        'supplier_id' => 737,
        'supplier_name' => 'INSTITUTO MEDICO MENE GRANDE, C.A.',
        'supplier_rif' => 'J305657386',
        'invoice_date' => '2026-08-28',
        'invoice_registration_date' => '2026-09-10',
        'invoice_number' => '167598',
        'invoice_control_number' => '00-148002',
        'invoice_amount' => '25.0000',
        'invoice_currency' => 'USD',
        'payment_status' => 'PAGADA',
    ], $attributes), true);

    if ($items === null) {
        $record->setRelation('operationServiceOrder', null);

        return $record;
    }

    $patient = new TelemedicinePatient;
    $patient->setRawAttributes(['id' => 1, 'specific_business_unit' => $unit]);
    $coordination = new OperationCoordinationService;
    $coordination->setRawAttributes(['id' => 1]);
    $coordination->setRelation('telemedicinePatient', $patient);

    $order = new OperationServiceOrder;
    $order->setRawAttributes(['id' => $id, 'order_number' => $orderNumber, 'service_type' => $serviceType, 'description' => null]);
    $order->setRelation('operationCoordinationService', $coordination);
    $order->setRelation('operationServiceOrderItems', collect($items)->map(function (string $name): OperationServiceOrderItem {
        $item = new OperationServiceOrderItem;
        $item->setRawAttributes(['item_name' => $name]);

        return $item;
    }));

    $record->setRelation('operationServiceOrder', $order);

    return $record;
}

/**
 * @return array<string, string> hoja => XML
 */
function cxpXlsxSheets(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'cxp-test-');
    file_put_contents($path, $contents);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    preg_match_all('/<sheet name="([^"]+)" sheetId="(\d+)"/', (string) $zip->getFromName('xl/workbook.xml'), $matches, PREG_SET_ORDER);
    $sheets = [];

    foreach ($matches as [, $name, $sheetId]) {
        $sheets[html_entity_decode($name)] = (string) $zip->getFromName('xl/worksheets/sheet'.$sheetId.'.xml');
    }

    $zip->close();
    unlink($path);

    return $sheets;
}

it('abrevia cada ítem con la sigla entre paréntesis y une los servicios con guion', function (): void {
    expect(AccountsPayableSupplierReport::shortItemName('HEMATOLOGIA COMPLETA (HC)'))->toBe('HC')
        ->and(AccountsPayableSupplierReport::shortItemName('  uroanalisis '))->toBe('UROANALISIS')
        ->and(AccountsPayableSupplierReport::shortItemName('PROTEINA C (REACTIVA) CUANTITATIVA'))->toBe('PROTEINA C (REACTIVA) CUANTITATIVA')
        ->and(AccountsPayableSupplierReport::shortItemName('VELOCIDAD DE SEDIMENTACION ( vsg )'))->toBe('VSG');

    $record = cxpRecord(items: ['HEMATOLOGIA COMPLETA (HC)', 'UROANALISIS', 'COPROANALISIS', 'UROANALISIS', '']);

    expect(AccountsPayableSupplierReport::specificServices($record->operationServiceOrder))->toBe('HC-UROANALISIS-COPROANALISIS')
        ->and(AccountsPayableSupplierReport::specificServices(null))->toBe('—');
});

it('arma la fila con la unidad específica del paciente, montos reales y estatus heredados', function (): void {
    $row = AccountsPayableSupplierReport::rowFromRecord(cxpRecord([
        'payment_status' => 'pagado',
        'invoice_amount' => '32.7449',
        'supplier_name' => "ASISTENCIA MEDICA C.A\n(AME SALUD)",
    ]));

    expect($row['specific_business_unit'])->toBe('PQQ')
        ->and($row['service_type'])->toBe('LABORATORIOS')
        ->and($row['services'])->toBe('HC-UROANALISIS')
        ->and($row['order_number'])->toBe('ORD-0063')
        ->and($row['amount'])->toBe(32.74)
        ->and($row['currency'])->toBe('USD')
        ->and($row['status'])->toBe(StatusCuentaPorPagar::Pagada)
        ->and($row['supplier'])->toBe('ASISTENCIA MEDICA C.A (AME SALUD)')
        ->and($row['invoice_date']?->format('d/m/Y'))->toBe('28/08/2026');

    $manual = AccountsPayableSupplierReport::rowFromRecord(cxpRecord([
        'payment_status' => 'ALGO RARO',
        'invoice_currency' => 'VES',
        'invoice_date' => null,
        'supplier_rif' => '',
    ], items: null));

    expect($manual['order_number'])->toBeNull()
        ->and($manual['specific_business_unit'])->toBe('—')
        ->and($manual['service_type'])->toBe('—')
        ->and($manual['currency'])->toBe('VES')
        ->and($manual['status'])->toBe(StatusCuentaPorPagar::PendientePorPagar)
        ->and($manual['invoice_date'])->toBeNull()
        ->and($manual['rif'])->toBe('—');
});

it('agrupa por estatus (pagado, en gestión, pendiente) y ordena por número de orden', function (): void {
    $rows = array_map(AccountsPayableSupplierReport::rowFromRecord(...), [
        cxpRecord(['payment_status' => 'PENDIENTE POR PAGAR'], orderNumber: 'ORD-0392'),
        cxpRecord(['payment_status' => 'PAGADA'], orderNumber: 'ORD-0104'),
        cxpRecord(['payment_status' => 'PAGADA'], items: null),
        cxpRecord(['payment_status' => 'PAGADA'], orderNumber: 'ORD-0063'),
        cxpRecord(['payment_status' => 'EN GESTION'], orderNumber: 'ORD-0500'),
    ]);

    $blocks = AccountsPayableSupplierReport::blocks($rows);

    expect(array_column($blocks, 'label'))->toBe(['Pagado', 'En gestión', 'Pendiente por pagar'])
        ->and(array_column($blocks, 'total_label'))->toBe(['Total Pagado', 'Total En gestión', 'Total Pendiente por pagar'])
        ->and(array_column($blocks[0]['rows'], 'order_number'))->toBe(['ORD-0063', 'ORD-0104', null]);
});

it('nombra el archivo como el formato de Administración y cambia según lo exportado', function (): void {
    $date = CarbonImmutable::create(2026, 10, 27);
    $paid = AccountsPayableSupplierReport::rowFromRecord(cxpRecord(['payment_status' => 'PAGADA']));
    $pending = AccountsPayableSupplierReport::rowFromRecord(cxpRecord(['payment_status' => 'PENDIENTE POR PAGAR']));
    $inProgress = AccountsPayableSupplierReport::rowFromRecord(cxpRecord(['payment_status' => 'EN GESTION']));
    $otherSupplier = AccountsPayableSupplierReport::rowFromRecord(cxpRecord(['supplier_id' => 9, 'supplier_name' => 'CLINICA TIERRA SANTA']));
    $imaging = AccountsPayableSupplierReport::rowFromRecord(cxpRecord(serviceType: 'IMAGENOLOGIA'));

    expect(AccountsPayableSupplierReport::filename([$paid, $pending], $date))
        ->toBe('INSTITUTO MEDICO MENE GRANDE C.A.-PAGADOS O PDTE-LABORATORIOS-27OCT2026.xlsx')
        ->and(AccountsPayableSupplierReport::filename([$paid], $date))
        ->toBe('INSTITUTO MEDICO MENE GRANDE C.A.-PAGADOS-LABORATORIOS-27OCT2026.xlsx')
        ->and(AccountsPayableSupplierReport::filename([$pending], CarbonImmutable::create(2026, 1, 5)))
        ->toBe('INSTITUTO MEDICO MENE GRANDE C.A.-PDTE-LABORATORIOS-05ENE2026.xlsx')
        ->and(AccountsPayableSupplierReport::filename([$pending, $inProgress, $paid], $date))
        ->toBe('INSTITUTO MEDICO MENE GRANDE C.A.-PAGADOS O EN GESTION O PDTE-LABORATORIOS-27OCT2026.xlsx')
        ->and(AccountsPayableSupplierReport::filename([$paid, $otherSupplier], $date))
        ->toBe('VARIOS PROVEEDORES-PAGADOS-LABORATORIOS-27OCT2026.xlsx')
        ->and(AccountsPayableSupplierReport::filename([$paid, $imaging], $date))
        ->toBe('INSTITUTO MEDICO MENE GRANDE C.A.-PAGADOS-VARIOS SERVICIOS-27OCT2026.xlsx');

    $manual = AccountsPayableSupplierReport::rowFromRecord(cxpRecord(['supplier_name' => 'LAB "X" / Y: Z?'], items: null));

    expect(AccountsPayableSupplierReport::filename([$manual], $date))
        ->toBe('LAB X Y Z-PAGADOS-SIN TIPO DE SERVICIO-27OCT2026.xlsx');
});

it('da nombres de hoja válidos y únicos para Excel', function (): void {
    $used = [];

    expect(AccountsPayableSupplierReport::uniqueSheetName('CLINICA [A]: SALUD/VIDA*?', $used))->toBe('CLINICA A SALUD VIDA')
        ->and(AccountsPayableSupplierReport::uniqueSheetName(str_repeat('LABORATORIO ', 5), $used))->toBe('LABORATORIO LABORATORIO')
        ->and(AccountsPayableSupplierReport::uniqueSheetName(str_repeat('LABORATORIO ', 5), $used))->toBe('LABORATORIO LABORATORIO (2)')
        ->and(AccountsPayableSupplierReport::uniqueSheetName('clinica a salud vida', $used))->toBe('clinica a salud vida (2)')
        ->and(AccountsPayableSupplierReport::uniqueSheetName('—', $used))->toBe('Proveedor');
});

it('escribe una hoja por proveedor con encabezado, fechas, montos y subtotales SUBTOTAL', function (): void {
    $rows = array_map(AccountsPayableSupplierReport::rowFromRecord(...), [
        cxpRecord(['payment_status' => 'PAGADA', 'invoice_amount' => '25'], orderNumber: 'ORD-0063'),
        cxpRecord(['payment_status' => 'PAGADA', 'invoice_amount' => '9.77'], orderNumber: 'ORD-0080'),
        cxpRecord(['payment_status' => 'PENDIENTE POR PAGAR', 'invoice_amount' => '8'], orderNumber: 'ORD-0392'),
        cxpRecord(['supplier_id' => 9, 'supplier_name' => 'CLINICA TIERRA SANTA', 'invoice_amount' => '40']),
    ]);

    $sheets = cxpXlsxSheets(AccountsPayableSupplierReportXlsxService::write(AccountsPayableSupplierReport::sheets($rows)));

    expect(array_keys($sheets))->toBe(['CLINICA TIERRA SANTA', 'INSTITUTO MEDICO MENE GRANDE']);

    $xml = $sheets['INSTITUTO MEDICO MENE GRANDE'];

    expect($xml)
        ->toContain('showGridLines="false"')
        ->toContain('Unidad Especifica de negocio')
        ->toContain('Servicios Especificos')
        ->toContain('HC-UROANALISIS')
        ->toContain('<f>SUBTOTAL(9,K2:K3)</f><v>34.77</v>')
        ->toContain('<f>SUBTOTAL(9,K5:K5)</f><v>8.00</v>')
        ->toContain('<f>SUBTOTAL(9,K2:K6)</f><v>42.77</v>')
        ->toContain('Total Pagado')
        ->toContain('Total Pendiente por pagar')
        ->toContain('Total general')
        ->toContain('<v>9.77</v>')
        ->not->toContain('Total En gestión');

    expect(substr_count($xml, '<row '))->toBe(7);
});

it('con US$ y Bs. en el mismo proveedor da un total por moneda en vez de sumarlas', function (): void {
    $rows = array_map(AccountsPayableSupplierReport::rowFromRecord(...), [
        cxpRecord(['invoice_amount' => '25', 'invoice_currency' => 'USD']),
        cxpRecord(['invoice_amount' => '1000.5', 'invoice_currency' => 'VES']),
    ]);

    $xml = cxpXlsxSheets(AccountsPayableSupplierReportXlsxService::write(AccountsPayableSupplierReport::sheets($rows)))['INSTITUTO MEDICO MENE GRANDE'];

    expect($xml)
        ->not->toContain('SUBTOTAL')
        ->toContain('Total Pagado (US$)')
        ->toContain('Total Pagado (Bs.)')
        ->toContain('Total general (US$)')
        ->toContain('<v>1000.5</v>');
});

it('rechaza una exportación sin facturas válidas sin consultar la base', function (): void {
    AccountsPayableSupplierReportXlsxService::build([0, -3, 'x']);
})->throws(RuntimeException::class, 'Selecciona al menos una factura para exportar.');

it('mantiene el CSV y agrega el Excel por proveedor en las acciones de la tabla', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationAccountsPayables/Tables/OperationAccountsPayablesTable.php');

    expect($source)
        ->toContain("BulkAction::make('export_accounts_payables_csv')")
        ->toContain("BulkAction::make('export_accounts_payables_supplier_xlsx')")
        ->toContain('AccountsPayableSupplierReportXlsxService::build(')
        ->toContain('No se pudo generar el Excel');
});
