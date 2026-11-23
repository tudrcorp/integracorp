<?php

declare(strict_types=1);

use App\Enums\OperationReportFormat;
use App\Enums\OperationReportType;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\Reports\AccountsPayableStatusReportBuilder;
use App\Support\Operations\Reports\CashPaymentReportBuilder;
use App\Support\Operations\Reports\OperationReportFilters;
use App\Support\Operations\Reports\OperationReportGenerator;
use App\Support\Operations\Reports\ServiceOrderStatusReportBuilder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * Reportes «Órdenes de servicio por estatus» y «Cuentas por pagar por estatus» del
 * Generador de reportes de Operaciones. Solo leen la base; los archivos van a un disco falso.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake(OperationReportGenerator::DISK);
    $this->admin = User::query()->where('is_superAdmin', 1)->where('status', 'ACTIVO')->first();

    if ($this->admin === null) {
        $this->markTestSkipped('No hay un superadmin activo para generar los reportes.');
    }

    Auth::setUser($this->admin);
});

afterEach(fn () => DB::rollBack());

/**
 * @param  array<string, mixed>  $extra
 */
function statusReportFilters(array $extra = []): OperationReportFilters
{
    return OperationReportFilters::fromArray(['period' => 'custom', 'from' => '2020-01-01', 'to' => now()->toDateString(), ...$extra]);
}

it('los dos reportes están en su propia sección, con Excel, CSV y PDF', function (): void {
    foreach ([OperationReportType::ServiceOrdersByStatus, OperationReportType::AccountsPayableByStatus] as $type) {
        expect($type->group())->toBe(OperationReportType::GROUP_ORDERS_PAYABLES)
            ->and($type->formats())->toBe([OperationReportFormat::Excel, OperationReportFormat::Csv, OperationReportFormat::Pdf])
            ->and($type->usesPeriod())->toBeTrue();
    }

    expect(OperationReportType::ServiceOrdersByStatus->rowNoun())->toBe(['orden de servicio', 'órdenes de servicio'])
        ->and(OperationReportType::AccountsPayableByStatus->rowNoun())->toBe(['cuenta por pagar', 'cuentas por pagar']);
});

it('cada reporte tiene su permiso propio en Operaciones', function (): void {
    $all = BusinessFilamentActionPermissionRegistry::all();

    expect($all[BusinessFilamentActionPermissionRegistry::REPORT_SERVICE_ORDERS_BY_STATUS]['modules'])->toBe(['OPERACIONES'])
        ->and($all[BusinessFilamentActionPermissionRegistry::REPORT_ACCOUNTS_PAYABLE_BY_STATUS]['modules'])->toBe(['OPERACIONES'])
        ->and(BusinessFilamentActionPermissionRegistry::REPORT_SERVICE_ORDERS_BY_STATUS)->toBe('reporte-ordenes-servicio-por-estatus')
        ->and(BusinessFilamentActionPermissionRegistry::REPORT_ACCOUNTS_PAYABLE_BY_STATUS)->toBe('reporte-cuentas-por-pagar-por-estatus');
});

it('los filtros solo aceptan estatus válidos y sin estatus no hay registros', function (): void {
    $filters = statusReportFilters([
        'order_statuses' => ['FINALIZADO', 'INVENTADO'],
        'document_statuses' => ['PENDIENTE', 'OTRO'],
        'payable_statuses' => ['PAGADA', 'X'],
    ]);

    expect($filters->orderStatuses)->toBe(['FINALIZADO'])
        ->and($filters->documentStatuses)->toBe(['PENDIENTE'])
        ->and($filters->payableStatuses)->toBe(['PAGADA'])
        ->and(OperationReportFilters::fromArray($filters->toArray())->orderStatuses)->toBe(['FINALIZADO'])
        ->and(ServiceOrderStatusReportBuilder::count(statusReportFilters()))->toBe(0)
        ->and(AccountsPayableStatusReportBuilder::count(statusReportFilters()))->toBe(0);
});

it('el resumen de órdenes cuadra con la base y separa el documento del aliado', function (): void {
    $summary = ServiceOrderStatusReportBuilder::summary(statusReportFilters(['order_statuses' => array_keys(ServiceOrderStatusReportBuilder::statusOptions())]));
    $byStatus = DB::table('operation_service_orders')
        ->selectRaw("CASE WHEN status = 'CANCELADO' THEN 'CANCELADA' ELSE status END AS grupo, COUNT(*) AS total")
        ->groupBy('grupo')
        ->pluck('total', 'grupo');

    foreach ($summary as $row) {
        expect($row['count'])->toBe((int) ($byStatus[$row['status']] ?? 0))
            ->and($row['pending'] + $row['registered'])->toBe($row['count'])
            ->and($row['without_sale'] <= $row['count'])->toBeTrue();
    }
});

it('el resumen de cuentas por pagar cuadra con la base y cuenta los pagos de contado', function (): void {
    $summary = AccountsPayableStatusReportBuilder::summary(statusReportFilters(['payable_statuses' => array_keys(AccountsPayableStatusReportBuilder::statusOptions())]));

    expect(array_sum(array_column($summary, 'count')))->toBe(DB::table('operation_accounts_payables')->whereNotNull('invoice_registration_date')->where('invoice_registration_date', '<=', now()->toDateString())->where('invoice_registration_date', '>=', '2020-01-01')->count())
        ->and(array_sum(array_column($summary, 'cash')))->toBe(DB::table('operation_cash_payments')->where('status', 'REGISTRADO')->distinct()->count('operation_accounts_payable_id'));
});

it('genera los dos reportes con el resumen antes del detalle', function (OperationReportType $type, array $filters): void {
    $data = statusReportFilters($filters);

    if (OperationReportGenerator::userCanGenerate($type) === false) {
        $this->markTestSkipped('El usuario no puede generar el reporte.');
    }

    $name = OperationReportGenerator::filename($type, OperationReportFormat::Csv);
    OperationReportGenerator::store($type, OperationReportFormat::Csv, $data, (int) $this->admin->id, $name);
    $lines = file((string) OperationReportGenerator::absolutePathFor((int) $this->admin->id, $name));
    $detailLine = array_key_first(array_filter($lines, fn (string $line): bool => str_contains($line, 'Detalle de')));

    expect($lines[0])->toContain('Resumen por estatus')
        ->and($detailLine)->not->toBeNull()
        ->and($detailLine > 1)->toBeTrue();

    foreach ([OperationReportFormat::Excel, OperationReportFormat::Pdf] as $format) {
        $file = OperationReportGenerator::filename($type, $format);
        OperationReportGenerator::store($type, $format, $data, (int) $this->admin->id, $file);

        expect(filesize((string) OperationReportGenerator::absolutePathFor((int) $this->admin->id, $file)) > 500)->toBeTrue();
    }
})->with([
    'órdenes' => [OperationReportType::ServiceOrdersByStatus, ['order_statuses' => ['FINALIZADO', 'EN GESTION']]],
    'cuentas por pagar' => [OperationReportType::AccountsPayableByStatus, ['payable_statuses' => ['PAGADA', 'PENDIENTE POR PAGAR']]],
]);

it('sin el permiso no se listan ni se generan, tampoco desde la cola', function (): void {
    $analyst = (new User)->forceFill(['id' => 999_999_403, 'name' => 'QA SIN PERMISO', 'email' => 'qa.reportes@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['OPERACIONES']]);
    Auth::setUser($analyst);

    expect(ServiceOrderStatusReportBuilder::allows())->toBeFalse()
        ->and(AccountsPayableStatusReportBuilder::allows())->toBeFalse()
        ->and(OperationReportGenerator::userCanGenerate(OperationReportType::ServiceOrdersByStatus))->toBeFalse()
        ->and(fn () => OperationReportGenerator::store(OperationReportType::ServiceOrdersByStatus, OperationReportFormat::Csv, statusReportFilters(['order_statuses' => ['FINALIZADO']]), 999_999_403, OperationReportGenerator::filename(OperationReportType::ServiceOrdersByStatus, OperationReportFormat::Csv)))
        ->toThrow(AuthorizationException::class);
});

it('la pantalla exige elegir al menos un estatus', function (): void {
    $page = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Pages/GeneradorDeReportes.php');

    expect($page)
        ->toContain("Select::make('order_statuses')")
        ->toContain("Select::make('payable_statuses')")
        ->toContain("'required' => 'Elija al menos un estatus de orden.'")
        ->toContain("'required' => 'Elija al menos un estatus de cuenta por pagar.'")
        ->toContain('OperationReportGenerator::userCanGenerate($type)');
});

it('el reporte de pagos de contado está en la misma sección, con su permiso propio', function (): void {
    expect(OperationReportType::CashPayments->group())->toBe(OperationReportType::GROUP_ORDERS_PAYABLES)
        ->and(OperationReportType::CashPayments->formats())->toBe([OperationReportFormat::Excel, OperationReportFormat::Csv, OperationReportFormat::Pdf])
        ->and(OperationReportType::CashPayments->rowNoun())->toBe(['pago de contado', 'pagos de contado'])
        ->and(BusinessFilamentActionPermissionRegistry::REPORT_CASH_PAYMENTS)->toBe('reporte-pagos-de-contado')
        ->and(BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::REPORT_CASH_PAYMENTS]['modules'])->toBe(['OPERACIONES']);
});

it('los pagos de contado se filtran por fecha del pago, estatus, tipo y moneda', function (): void {
    $filters = statusReportFilters(['cash_statuses' => ['REGISTRADO', 'OTRO'], 'cash_kinds' => ['masivo', 'x'], 'cash_currencies' => ['USD', 'EUR']]);

    expect($filters->cashStatuses)->toBe(['REGISTRADO'])
        ->and($filters->cashKinds)->toBe(['masivo'])
        ->and($filters->cashCurrencies)->toBe(['USD'])
        ->and(CashPaymentReportBuilder::count(statusReportFilters()))->toBe(0);

    $all = statusReportFilters(['cash_statuses' => array_keys(CashPaymentReportBuilder::statusOptions())]);
    $summary = CashPaymentReportBuilder::summary($all);

    expect(array_sum(array_column($summary, 'count')))->toBe(DB::table('operation_cash_payments')->whereBetween('payment_date', ['2020-01-01', now()->toDateString()])->count())
        ->and(array_sum(array_column($summary, 'single')) + array_sum(array_column($summary, 'batch')))->toBe(array_sum(array_column($summary, 'count')));
});

it('genera el reporte de pagos de contado con el resumen antes del detalle', function (): void {
    $filters = statusReportFilters(['cash_statuses' => array_keys(CashPaymentReportBuilder::statusOptions())]);

    if (CashPaymentReportBuilder::count($filters) === 0) {
        $this->markTestSkipped('No hay pagos de contado en la base.');
    }

    $name = OperationReportGenerator::filename(OperationReportType::CashPayments, OperationReportFormat::Csv);
    OperationReportGenerator::store(OperationReportType::CashPayments, OperationReportFormat::Csv, $filters, (int) $this->admin->id, $name);
    $csv = (string) file_get_contents((string) OperationReportGenerator::absolutePathFor((int) $this->admin->id, $name));

    expect(strpos($csv, 'Resumen por estatus') < strpos($csv, 'Detalle de pagos de contado'))->toBeTrue();
});

it('sin el permiso no se genera el reporte de pagos de contado', function (): void {
    Auth::setUser((new User)->forceFill(['id' => 999_999_404, 'name' => 'QA SIN PERMISO', 'email' => 'qa.contado.reporte@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['OPERACIONES']]));

    expect(CashPaymentReportBuilder::allows())->toBeFalse()
        ->and(fn () => OperationReportGenerator::store(OperationReportType::CashPayments, OperationReportFormat::Csv, statusReportFilters(['cash_statuses' => ['REGISTRADO']]), 999_999_404, OperationReportGenerator::filename(OperationReportType::CashPayments, OperationReportFormat::Csv)))
        ->toThrow(AuthorizationException::class);
});
