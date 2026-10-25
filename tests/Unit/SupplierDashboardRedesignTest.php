<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\DashboardProveedor;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierCaseStatusChart;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierConsultationsTrendChart;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierDashboardDetail;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierDoctorsRanking;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierIdentityHeader;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierKpiTiles;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierPatientsProfile;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierPriorityRanking;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierServiceTypeChart;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierWorkloadHeatmap;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics as Analytics;
use App\Support\Operations\SupplierDashboardMetrics;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se
 * revierte. Las fechas (2031) no chocan con datos reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    OperationsDashboardAnalytics::flush();
    SupplierDashboardMetrics::flush();
    Filament::setCurrentPanel('operations');
});

afterEach(function (): void {
    DB::rollBack();
    OperationsDashboardAnalytics::flush();
    SupplierDashboardMetrics::flush();
    Auth::forgetUser();
});

function proveedorDePrueba(string $name = 'PROVEEDOR QA'): Supplier
{
    return Supplier::query()->create(['name' => $name.' '.uniqid(), 'gestion_integracorp' => true]);
}

function actuarComoAnalistaDe(?Supplier $supplier): User
{
    $user = User::factory()->make([
        'id' => 999_999_071,
        'name' => 'ANALISTA QA',
        'email' => 'analista.qa@proveedor.test',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
        'supplier_id' => $supplier?->id,
        'is_proveedor_amd' => true,
    ]);

    test()->actingAs($user);
    SupplierDashboardMetrics::flush();
    OperationsDashboardAnalytics::flush();

    return $user;
}

function casoDelProveedor(Supplier $supplier, string $createdAt, string $status = 'EN SEGUIMIENTO', array $attributes = []): TelemedicineCase
{
    $patient = TelemedicinePatient::query()->create(['full_name' => 'PACIENTE QA '.uniqid(), 'managed_by' => 'QA']);

    $case = new TelemedicineCase;
    $case->forceFill($attributes + [
        'telemedicine_patient_id' => $patient->id,
        'code' => 'QAP-'.uniqid(),
        'status' => $status,
        'supplier_id' => $supplier->id,
        'patient_name' => $patient->full_name,
        'patient_age' => 40,
        'patient_sex' => 'FEMENINO',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->save();

    return $case;
}

function consultaDe(TelemedicineCase $case, string $createdAt, string $status = 'EN SEGUIMIENTO', int $doctorId = 1): TelemedicineConsultationPatient
{
    $consultation = new TelemedicineConsultationPatient;
    $consultation->forceFill([
        'telemedicine_case_id' => $case->id,
        'telemedicine_case_code' => $case->code,
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'telemedicine_doctor_id' => $doctorId,
        'status' => $status,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->save();

    return $consultation;
}

/**
 * Marzo 2031: 3 casos, 4 consultas, 1 alta a los 2 días. Otro proveedor tiene un caso que nunca debe contarse.
 */
function sembrarProveedorMarzo2031(): Supplier
{
    $supplier = proveedorDePrueba();
    $other = proveedorDePrueba('OTRO PROVEEDOR');

    $discharged = casoDelProveedor($supplier, '2031-03-03 09:00:00', 'ALTA MEDICA');
    consultaDe($discharged, '2031-03-03 10:15:00');
    consultaDe($discharged, '2031-03-05 09:00:00', 'ALTA MEDICA');

    $followUp = casoDelProveedor($supplier, '2031-03-10 08:00:00', 'EN SEGUIMIENTO', ['patient_sex' => 'MASCULINO', 'patient_age' => 8]);
    consultaDe($followUp, '2031-03-10 10:40:00', 'EN SEGUIMIENTO', 2);

    casoDelProveedor($supplier, '2031-03-20 08:00:00', 'ASIGNADO', ['patient_age' => null, 'patient_sex' => null]);

    $febrero = casoDelProveedor($supplier, '2031-02-10 08:00:00', 'ALTA MEDICA');
    consultaDe($febrero, '2031-02-14 08:00:00', 'ALTA MEDICA');

    $ajeno = casoDelProveedor($other, '2031-03-04 08:00:00', 'ALTA MEDICA');
    consultaDe($ajeno, '2031-03-04 10:00:00', 'ALTA MEDICA', 3);

    return $supplier;
}

function filtrosProveedorMarzo2031(): array
{
    return ['period' => Period::CUSTOM, 'from' => '2031-03-01', 'to' => '2031-03-31', 'compare' => true];
}

it('calcula los indicadores del proveedor, incluidos los días hasta el alta', function (): void {
    actuarComoAnalistaDe(sembrarProveedorMarzo2031());
    $kpis = Analytics::kpis(Period::fromFilters(filtrosProveedorMarzo2031()));

    expect($kpis['current'])->toBe([
        'new_cases' => 3,
        'consultations' => 3,
        'discharges' => 1,
        'average_days' => 2.0,
        'patients' => 2,
        'doctors' => 2,
    ])
        ->and($kpis['previous']['new_cases'])->toBe(1)
        ->and($kpis['previous']['average_days'])->toBe(4.0)
        ->and($kpis['sparklines']['consultations'])->toHaveCount(31);
});

it('agrupa estado, prioridad, médicos, mapa de calor y pacientes del proveedor', function (): void {
    actuarComoAnalistaDe(sembrarProveedorMarzo2031());
    $period = Period::fromFilters(filtrosProveedorMarzo2031());

    $patients = Analytics::patients($period);

    expect(Analytics::caseStatus($period))->toBe(['ASIGNADO' => 1, 'EN SEGUIMIENTO' => 1, 'ALTA MEDICA' => 1])
        ->and(Analytics::priorities($period))->toBe(['rows' => [['label' => 'Sin prioridad', 'value' => 3]], 'others' => 0, 'total' => 3])
        ->and(Analytics::doctors($period)['total'])->toBe(3)
        ->and(Analytics::heatmap($period)['matrix'][0][10])->toBe(2)
        ->and(Analytics::heatmap($period)['total'])->toBe(3)
        ->and($patients['total'])->toBe(3)
        ->and($patients['sexes'])->toBe(['Femenino' => 1, 'Masculino' => 1, 'Sin dato' => 1])
        ->and($patients['ages']['0–12'])->toBe(1)
        ->and($patients['ages']['30–44'])->toBe(1);
});

it('nunca mezcla datos de otro proveedor ni entrega datos sin proveedor', function (): void {
    $supplier = sembrarProveedorMarzo2031();
    $period = Period::fromFilters(filtrosProveedorMarzo2031());

    actuarComoAnalistaDe($supplier);
    $own = Analytics::detail($period, 'all', '');

    expect($own['total'])->toBe(3)
        ->and(collect($own['rows'])->pluck('case_code')->every(fn ($code): bool => str_starts_with((string) $code, 'QAP-')))->toBeTrue()
        ->and(Analytics::detail($period, 'doctor', '3')['total'])->toBe(0);

    actuarComoAnalistaDe(null);

    expect(Analytics::kpis($period)['current']['new_cases'])->toBe(0)
        ->and(Analytics::detail($period, 'all', '')['total'])->toBe(0)
        ->and(SupplierDashboardMetrics::caseIdsQuery()->count())->toBe(0);
});

it('el detalle del proveedor filtra por corte y rechaza valores inválidos', function (): void {
    actuarComoAnalistaDe(sembrarProveedorMarzo2031());
    $period = Period::fromFilters(filtrosProveedorMarzo2031());

    expect(Analytics::detail($period, 'case_status', 'ALTA MEDICA')['total'])->toBe(1)
        ->and(Analytics::detail($period, 'doctor', '2')['total'])->toBe(1)
        ->and(Analytics::detail($period, 'slot', '0-10')['total'])->toBe(2)
        ->and(Analytics::detail($period, 'sex', 'Masculino')['total'])->toBe(1)
        ->and(Analytics::detail($period, 'age_band', '0–12')['total'])->toBe(1)
        ->and(Analytics::detail($period, 'all', '')['rows'][0])->toHaveKeys(['case_code', 'patient', 'status', 'priority', 'doctor', 'consultations'])
        ->and(Analytics::detail($period, 'doctor', '1 OR 1=1'))->toBe(['rows' => [], 'total' => 0])
        ->and(Analytics::detail($period, 'slot', '0-10; DROP'))->toBe(['rows' => [], 'total' => 0])
        ->and(Analytics::detail($period, 'service_type', 'Inventado'))->toBe(['rows' => [], 'total' => 0])
        ->and(Analytics::detail($period, 'sex', 'Otro'))->toBe(['rows' => [], 'total' => 0])
        ->and(Analytics::detail($period, 'inventada', 'x'))->toBe(['rows' => [], 'total' => 0]);
});

it('el conteo por tipo de servicio respeta el rango y el histórico no cambia', function (): void {
    actuarComoAnalistaDe(sembrarProveedorMarzo2031());

    $march = SupplierDashboardMetrics::casesByServiceTypeBetween(Carbon\CarbonImmutable::parse('2031-03-01'), Carbon\CarbonImmutable::parse('2031-03-31'));

    expect(array_keys($march))->toBe(SupplierDashboardMetrics::SERVICE_TYPE_LABELS)
        ->and(SupplierDashboardMetrics::casesByServiceType())->toBe(SupplierDashboardMetrics::casesByServiceTypeBetween(null, null));
});

it('la página mantiene el encabezado del proveedor primero y arma los widgets nuevos', function (): void {
    expect((new DashboardProveedor)->getWidgets())->toBe([
        SupplierIdentityHeader::class,
        SupplierKpiTiles::class,
        SupplierConsultationsTrendChart::class,
        SupplierCaseStatusChart::class,
        SupplierServiceTypeChart::class,
        SupplierDoctorsRanking::class,
        SupplierPriorityRanking::class,
        SupplierWorkloadHeatmap::class,
        SupplierPatientsProfile::class,
        SupplierDashboardDetail::class,
    ])
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/OperationsPanelProvider.php'))
        ->toContain("view('filament.operations.dashboard.page-start', ['branding' => false]),\n                scopes: DashboardProveedor::class,");
});

it('cada widget del proveedor se renderiza con sus datos', function (): void {
    actuarComoAnalistaDe(sembrarProveedorMarzo2031());
    $filters = filtrosProveedorMarzo2031();

    Livewire::test(SupplierKpiTiles::class, ['pageFilters' => $filters])
        ->assertSee('Equipo')
        ->assertSee('Días promedio hasta el alta')
        ->assertSee('2,0 días')
        ->assertSee('Médicos activos');

    Livewire::test(SupplierCaseStatusChart::class, ['pageFilters' => $filters])
        ->assertSee('Estado de los casos')
        ->assertSee('ASIGNADO')
        ->assertSee('casos');

    foreach ([SupplierConsultationsTrendChart::class, SupplierServiceTypeChart::class] as $chart) {
        $options = Livewire::test($chart, ['pageFilters' => $filters])->instance()->getOptionsForTest();

        expect($options)->not->toContain('"')
            ->toContain('renderTip(context,');
    }

    expect(Livewire::test(SupplierConsultationsTrendChart::class, ['pageFilters' => $filters])->instance()->getOptionsForTest())
        ->toContain("'consultas'");

    Livewire::test(SupplierDoctorsRanking::class, ['pageFilters' => $filters])->assertSee('Médicos con más consultas');
    Livewire::test(SupplierPriorityRanking::class, ['pageFilters' => $filters])->assertSee('Sin prioridad');
    Livewire::test(SupplierWorkloadHeatmap::class, ['pageFilters' => $filters])->assertSee('Consultas por día y hora')->assertSee('Clic para ver los casos de este tramo');
    Livewire::test(SupplierPatientsProfile::class, ['pageFilters' => $filters])->assertSee('Masculino')->assertSee('Por edad');

    Livewire::test(SupplierDashboardDetail::class, ['pageFilters' => $filters])
        ->call('openDetail', 'case_status', 'ASIGNADO')
        ->assertDispatched('open-modal')
        ->assertSee('Estado: ASIGNADO')
        ->assertSee('Médico asignado')
        ->assertSee('1 casos');

    Livewire::test(SupplierDashboardDetail::class, ['pageFilters' => $filters])
        ->call('openDetail', 'service_status', 'FINALIZADO')
        ->assertNotDispatched('open-modal');
});
