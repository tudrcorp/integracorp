<?php

declare(strict_types=1);

use App\Filament\Metrics\Widgets\Corporate\CorporateBarChart;
use App\Filament\Operations\Widgets\Dashboard\OperationsDashboardDetail;
use App\Filament\Operations\Widgets\Dashboard\OperationsServiceTypeChart;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierServiceTypeChart;
use App\Models\OperationServiceStatistic;
use App\Models\User;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics as Analytics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use App\Support\Operations\Dashboard\OperationsMacroServices;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se
 * revierte. Las filas se insertan sin Eloquent para no disparar observers
 * (aviso al agente, sincronización de estadísticas). Fechas en 2031.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Analytics::flush();
    Filament::setCurrentPanel('operations');

    test()->actingAs(User::factory()->make([
        'id' => 999_999_062,
        'name' => 'QA MACRO',
        'email' => 'qa.macro@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
    ]));
});

afterEach(function (): void {
    DB::rollBack();
    Analytics::flush();
});

function macroQaServicio(string $name, int $level = 1): int
{
    return (int) DB::table('telemedicine_service_lists')->insertGetId([
        'name' => $name,
        'description' => $name,
        'type' => 'MACRO',
        'level' => (string) $level,
    ]);
}

function macroQaCaso(string $status = 'ASIGNADO'): int
{
    return (int) DB::table('telemedicine_cases')->insertGetId([
        'telemedicine_patient_id' => 999_999_901,
        'code' => 'QA-MACRO-'.random_int(1, 999_999),
        'status' => $status,
        'created_at' => '2031-03-02 09:00:00',
        'updated_at' => '2031-03-02 09:00:00',
    ]);
}

function macroQaConsulta(int $caseId, int $serviceListId, string $createdAt = '2031-03-05 10:00:00'): void
{
    DB::table('telemedicine_consultation_patients')->insert([
        'telemedicine_case_id' => $caseId,
        'telemedicine_case_code' => 'QA-MACRO',
        'telemedicine_doctor_id' => 999_999_902,
        'telemedicine_patient_id' => 999_999_901,
        'telemedicine_service_list_id' => $serviceListId,
        'status' => 'CONSULTA INICIAL',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function macroQaEstadistica(?int $caseId, string $serviceType, string $sourceType = 'lab'): void
{
    OperationServiceStatistic::query()->create([
        'source_type' => $sourceType,
        'source_id' => random_int(1, 999_999),
        'service_on' => '2031-03-06',
        'service_status' => 'PENDIENTE',
        'service_type' => $serviceType,
        'telemedicine_case_id' => $caseId,
        'case_code' => 'QA-MACRO',
        'patient_name' => 'PACIENTE QA MACRO',
        'specific_service' => $serviceType,
    ]);
}

function macroQaPeriodo(): Period
{
    return Period::fromFilters(['period' => Period::CUSTOM, 'from' => '2031-03-01', 'to' => '2031-03-31', 'compare' => false]);
}

/**
 * Escenario: dos niveles de «QA AMD» (mismo nombre), un traslado como consulta,
 * un traslado ordenado en una consulta AMD (cuenta), un AMD RETAIL en un caso que
 * ya tiene consulta AMD (no cuenta: duplicado) y medicamentos derivados.
 *
 * @return array{amd1: int, amd2: int, traslado: int}
 */
function macroQaSembrar(): array
{
    $amd1 = macroQaServicio('QA AMD DOMICILIARIA');
    $amd2 = macroQaServicio('QA AMD DOMICILIARIA', 2);
    $traslado = macroQaServicio('QA TRASLADO');

    $caseA = macroQaCaso();
    macroQaConsulta($caseA, $amd1);
    macroQaConsulta($caseA, $amd2, '2031-03-07 08:00:00');
    macroQaEstadistica($caseA, 'QA TRASLADO', 'ambulance');
    macroQaEstadistica($caseA, 'QA AMD DOMICILIARIA', 'specialty');
    macroQaEstadistica($caseA, 'MEDICAMENTOS', 'medication');
    macroQaEstadistica($caseA, 'MEDICAMENTOS', 'medication');

    $caseB = macroQaCaso();
    macroQaConsulta($caseB, $traslado);
    macroQaEstadistica($caseB, 'QA TRASLADO', 'ambulance');

    macroQaConsulta(macroQaCaso(), $amd1, '2031-03-20 15:30:00');

    // Fuera del período: no cuenta.
    macroQaConsulta($caseB, $amd1, '2031-04-02 10:00:00');

    // Caso eliminado: su consulta no cuenta.
    macroQaConsulta(macroQaCaso('ELIMINADO'), $amd1);

    return ['amd1' => $amd1, 'amd2' => $amd2, 'traslado' => $traslado];
}

it('cuenta los servicios principales desde las consultas sin duplicar los de las estadísticas', function (): void {
    macroQaSembrar();

    $breakdown = Analytics::serviceTypeBreakdown(macroQaPeriodo());

    expect($breakdown['principal'])->toBe(['QA AMD DOMICILIARIA' => 3, 'QA TRASLADO' => 2])
        ->and($breakdown['derived'])->toBe(['MEDICAMENTOS' => 2]);
});

it('normaliza tildes y alias al agrupar servicios macro', function (): void {
    expect(OperationsMacroServices::normalize('  Centro  Médico '))->toBe('CENTRO MEDICO')
        ->and(OperationsMacroServices::keyForStatisticType('MEDICAMENTOS'))->toBeNull()
        ->and(OperationsMacroServices::ALIASES['TELEMEDICINA'])->toBe('ATENCION MEDICA TELEFONICA (TELEMEDICINA)');
});

it('lista en el detalle las consultas y los servicios macro que no duplican', function (): void {
    macroQaSembrar();

    $detail = Analytics::detail(macroQaPeriodo(), 'macro_service', 'QA TRASLADO');

    expect($detail['total'])->toBe(2)
        ->and(array_column($detail['rows'], 'type'))->toEqualCanonicalizing(['Servicio principal', 'QA TRASLADO'])
        ->and(Analytics::dimensions())->toContain('macro_service')
        ->and(Analytics::dimensionLabel('macro_service', 'QA TRASLADO'))->toBe('Servicio principal: QA TRASLADO')
        ->and(Analytics::detail(macroQaPeriodo(), 'macro_service', 'NO EXISTE')['total'])->toBe(0);
});

it('un proveedor solo ve las consultas de los casos de sus coordinaciones', function (): void {
    macroQaSembrar();

    test()->actingAs(User::factory()->make([
        'id' => 999_999_063,
        'email' => 'qa.proveedor@proveedor.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
        'supplier_id' => 999_999_903,
    ]));
    Analytics::flush();

    expect(Analytics::serviceTypeBreakdown(macroQaPeriodo())['principal'])->toBe([]);
});

it('el gráfico pinta dos series con leyenda y abre el detalle según la serie', function (): void {
    macroQaSembrar();
    $filters = ['period' => Period::CUSTOM, 'from' => '2031-03-01', 'to' => '2031-03-31', 'compare' => false];

    $chart = Livewire::test(OperationsServiceTypeChart::class, ['pageFilters' => $filters])->instance();
    $data = (fn (): array => $this->getData())->call($chart);
    $options = $chart->getOptionsForTest();

    expect($data['labels'])->toBe(['QA AMD DOMICILIARIA', 'QA TRASLADO', 'MEDICAMENTOS'])
        ->and($data['datasets'][0]['label'])->toBe('Servicio principal')
        ->and($data['datasets'][0]['data'])->toBe([3, 2, null])
        ->and($data['datasets'][1]['label'])->toBe('Servicio derivado')
        ->and($data['datasets'][1]['data'])->toBe([null, null, 2])
        ->and($options)->toContain('legend: { display: true')
        ->and($options)->toContain("'macro_service' : 'service_type'")
        ->and($options)->toContain('stacked: true')
        ->and($options)->toContain('ops.series2')
        ->and($options)->not->toContain('"');
});

it('el detalle del dashboard acepta la dimensión de servicio principal', function (): void {
    macroQaSembrar();
    $filters = ['period' => Period::CUSTOM, 'from' => '2031-03-01', 'to' => '2031-03-31', 'compare' => false];

    Livewire::test(OperationsDashboardDetail::class, ['pageFilters' => $filters])
        ->call('openDetail', 'macro_service', 'QA AMD DOMICILIARIA')
        ->assertSet('dimension', 'macro_service')
        ->assertSee('Servicio principal: QA AMD DOMICILIARIA');
});

it('el dashboard del proveedor y el panel corporativo conservan una sola serie', function (): void {
    expect(file_get_contents((new ReflectionClass(SupplierServiceTypeChart::class))->getFileName()))
        ->toContain('protected function typeBreakdown(): ?array')
        ->and(file_get_contents((new ReflectionClass(CorporateBarChart::class))->getFileName()))
        ->toContain('protected function typeBreakdown(): ?array');
});
