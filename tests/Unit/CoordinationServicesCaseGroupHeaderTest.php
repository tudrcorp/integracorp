<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\OperationCoordinationService;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineMedicalTeam;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    OperationCoordinationServicesTable::rememberCaseRegistrationRanges([]);
});
afterEach(function (): void {
    OperationCoordinationServicesTable::rememberCaseRegistrationRanges([]);
    DB::rollBack();
});

function headerAnalyst(?int $supplierId = null): User
{
    return (new User)->forceFill(['id' => 999_997, 'supplier_id' => $supplierId, 'is_proveedor_amd' => false, 'departament' => ['OPERACIONES']]);
}

function serviceOfCaseManagedBy(string $operator, string $managedBy): OperationCoordinationService
{
    $service = OperationCoordinationService::query()
        ->with(OperationCoordinationServicesTable::listEagerLoads())
        ->whereHas('telemedicineCase', fn ($case) => $case->where('managed_by', $operator, $managedBy))
        ->whereNotNull('telemedicine_doctor_id')
        ->latest('id')
        ->first();

    if ($service === null) {
        test()->markTestSkipped("No hay servicios de casos con managed_by {$operator} {$managedBy}.");
    }

    return $service;
}

it('muestra «TDG» para un caso gestionado por TDG', function (): void {
    $case = new TelemedicineCase(['managed_by' => ' tdg ']);

    expect(TelemedicineMedicalTeam::caseManagerLabel($case))->toBe('TDG')
        ->and(TelemedicineMedicalTeam::caseManagerLabel(null))->toBeNull();
});

it('muestra el alias del proveedor del médico del caso, o su nombre sin saltos de línea', function (): void {
    $supplier = new Supplier(['name' => "CORPORACION VMC, C.A.\n(ATENMEDI)", 'integracorp_alias' => 'ATENMEDI']);
    $withoutAlias = new Supplier(['name' => "AUXILIO MEDICO 24 - PROVEEDOR REGIONAL\nOPERADO POR: VMC ", 'integracorp_alias' => null]);

    $doctor = new TelemedicineDoctor;
    $doctor->setRelation('supplier', $supplier);
    $case = new TelemedicineCase(['managed_by' => 'CORPORACION VMC, C.A. (ATENMEDI)']);
    $case->setRelation('telemedicineDoctor', $doctor);

    $otherDoctor = new TelemedicineDoctor;
    $otherDoctor->setRelation('supplier', $withoutAlias);
    $otherCase = new TelemedicineCase(['managed_by' => 'AUXILIO']);
    $otherCase->setRelation('telemedicineDoctor', $otherDoctor);

    expect(TelemedicineMedicalTeam::caseManagerLabel($case))->toBe('ATENMEDI')
        ->and(TelemedicineMedicalTeam::caseManagerLabel($otherCase))->toBe('AUXILIO MEDICO 24 - PROVEEDOR REGIONAL OPERADO POR: VMC');
});

it('un caso del equipo de un proveedor muestra el proveedor del equipo aunque nadie lo haya tomado', function (): void {
    $case = new TelemedicineCase(['managed_by' => 'CORPORACION VMC, C.A. (ATENMEDI)', 'assigned_to_medical_team' => true]);
    $case->setRelation('medicalTeamSupplier', new Supplier(['name' => 'CORPORACION VMC', 'integracorp_alias' => 'ATENMEDI']));
    $case->setRelation('telemedicineDoctor', null);

    $orphan = new TelemedicineCase(['managed_by' => "  OTRO   PROVEEDOR\n"]);
    $orphan->setRelation('telemedicineDoctor', null);

    expect(TelemedicineMedicalTeam::caseManagerLabel($case))->toBe('ATENMEDI')
        ->and(TelemedicineMedicalTeam::caseManagerLabel($orphan))->toBe('OTRO PROVEEDOR');
});

it('el analista TDG ve quién gestiona el caso en el encabezado del grupo', function (): void {
    $service = serviceOfCaseManagedBy('!=', 'TDG');
    $this->actingAs(headerAnalyst());

    $expected = TelemedicineMedicalTeam::caseManagerLabel($service->telemedicineCase);

    $html = OperationCoordinationServicesTable::caseGroupDescription($service)?->toHtml();

    expect($html)
        ->toContain('Médico: ')
        ->toContain('Gestiona: '.e($expected))
        ->toContain('fi-badge')
        ->toContain('fi-color-warning');

    $tdgService = serviceOfCaseManagedBy('=', 'TDG');
    expect(OperationCoordinationServicesTable::caseGroupDescription($tdgService)?->toHtml())
        ->toContain('Gestiona: TDG')
        ->toContain('fi-color-info');
});

it('el usuario de un proveedor no ve quién gestiona el caso', function (): void {
    $service = serviceOfCaseManagedBy('!=', 'TDG');
    $this->actingAs(headerAnalyst((int) ($service->supplier_id ?? 15)));

    expect((string) OperationCoordinationServicesTable::caseGroupDescription($service)?->toHtml())
        ->not->toContain('Gestiona:')
        ->not->toContain('fi-badge');
});

it('calcula el rango de registro con una sola consulta, respetando la consulta filtrada', function (): void {
    $records = OperationCoordinationService::query()
        ->whereNotNull('telemedicine_case_id')
        ->latest('id')
        ->limit(15)
        ->get();

    if ($records->isEmpty()) {
        $this->markTestSkipped('No hay servicios con caso vinculado.');
    }

    $caseIds = $records->pluck('telemedicine_case_id')->unique()->values();

    $expected = OperationCoordinationService::query()
        ->whereIn('telemedicine_case_id', $caseIds)
        ->selectRaw('telemedicine_case_id, min(created_at) as f, max(created_at) as t')
        ->groupBy('telemedicine_case_id')
        ->get()
        ->mapWithKeys(fn ($row): array => [(int) $row->telemedicine_case_id => ['from' => (string) $row->f, 'to' => (string) $row->t]])
        ->all();

    $filtered = OperationCoordinationService::query()
        ->with(OperationCoordinationServicesTable::listEagerLoads())
        ->withCount('operationServiceOrders')
        ->orderByDesc('date_solicitud');

    DB::enableQueryLog();
    DB::flushQueryLog();
    $ranges = OperationCoordinationServicesTable::caseRegistrationRanges($filtered, $records);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(1)
        ->and($ranges)->toEqual($expected);
});

it('el rango sigue los filtros activos del cuadro', function (): void {
    $caseId = OperationCoordinationService::query()
        ->whereNotNull('telemedicine_case_id')
        ->groupBy('telemedicine_case_id')
        ->havingRaw('count(*) > 1')
        ->value('telemedicine_case_id');

    if ($caseId === null) {
        $this->markTestSkipped('No hay casos con más de un servicio.');
    }

    $latest = OperationCoordinationService::query()->where('telemedicine_case_id', $caseId)->latest('created_at')->latest('id')->firstOrFail();

    $ranges = OperationCoordinationServicesTable::caseRegistrationRanges(
        OperationCoordinationService::query()->whereKey($latest->id),
        collect([$latest]),
    );

    expect($ranges[(int) $caseId]['from'])->toBe((string) $latest->getRawOriginal('created_at'))
        ->and($ranges[(int) $caseId]['to'])->toBe((string) $latest->getRawOriginal('created_at'));
});

it('sin casos o sin consulta no calcula rangos', function (): void {
    expect(OperationCoordinationServicesTable::caseRegistrationRanges(null, collect([new OperationCoordinationService(['telemedicine_case_id' => 5])])))->toBe([])
        ->and(OperationCoordinationServicesTable::caseRegistrationRanges(OperationCoordinationService::query(), collect([new OperationCoordinationService])))->toBe([]);
});

it('describe el rango en un día o entre dos fechas', function (): void {
    OperationCoordinationServicesTable::rememberCaseRegistrationRanges([
        7 => ['from' => '2026-10-01 08:00:00', 'to' => '2026-10-01 18:30:00'],
        8 => ['from' => '2026-09-28 10:00:00', 'to' => '2026-10-01 09:00:00'],
    ]);

    expect(OperationCoordinationServicesTable::caseRegistrationRangeLabel(7))->toBe('Registrado el 01/10/2026')
        ->and(OperationCoordinationServicesTable::caseRegistrationRangeLabel('8'))->toBe('Registrados del 28/09/2026 al 01/10/2026')
        ->and(OperationCoordinationServicesTable::caseRegistrationRangeLabel(9))->toBeNull()
        ->and(OperationCoordinationServicesTable::caseRegistrationRangeLabel(null))->toBeNull();
});

it('ordena los servicios por fecha de registro y mantiene juntos los servicios de cada caso', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ListOperationCoordinationServices.php');

    expect($source)
        ->toContain("->defaultSort('created_at', 'desc')")
        ->not->toContain("->defaultSort('date_solicitud', 'desc')")
        ->toContain("->orderByDesc((new OperationCoordinationService)->getTable().'.telemedicine_case_id')")
        ->toContain('self::caseGroupDescription($record)')
        ->and($page)->toContain('OperationCoordinationServicesTable::caseRegistrationRanges($this->getFilteredTableQuery(), $records)');
});

it('pinta TDG y proveedor con color e ícono distintos', function (): void {
    $tdg = OperationCoordinationServicesTable::caseManagerBadgeHtml('TDG');
    $provider = OperationCoordinationServicesTable::caseManagerBadgeHtml('ATENMEDI');

    expect($tdg)->toContain('fi-badge')->toContain('fi-color-info')->toContain('Gestiona: TDG')->toContain('<svg')
        ->and($provider)->toContain('fi-color-warning')->toContain('Gestiona: ATENMEDI')->not->toContain('fi-color-info')
        ->and($tdg)->not->toBe(str_replace('TDG', 'ATENMEDI', $provider));
});

it('escapa el nombre del proveedor y el resto del encabezado', function (): void {
    $badge = OperationCoordinationServicesTable::caseManagerBadgeHtml('<script>alert(1)</script>');

    expect($badge)->not->toContain('<script>')
        ->toContain('&lt;script&gt;');

    $this->actingAs(headerAnalyst());

    $doctor = new TelemedicineDoctor(['full_name' => '<b>DRA</b>']);
    $case = new TelemedicineCase(['managed_by' => 'TDG']);
    $case->setRelation('telemedicineDoctor', null);
    $service = new OperationCoordinationService(['telemedicine_case_id' => 1]);
    $service->setRelation('telemedicineDoctor', $doctor);
    $service->setRelation('telemedicineCase', $case);

    expect(OperationCoordinationServicesTable::caseGroupDescription($service)?->toHtml())
        ->toContain('Médico: &lt;b&gt;DRA&lt;/b&gt;')
        ->not->toContain('<b>DRA</b>');
});

it('el grupo de la tabla entrega la descripción como HTML, no como texto escapado', function (): void {
    $service = serviceOfCaseManagedBy('=', 'TDG');
    $this->actingAs(headerAnalyst());

    $table = OperationCoordinationServicesTable::configure(Filament\Tables\Table::make(Mockery::mock(Filament\Tables\Contracts\HasTable::class)));
    $group = $table->getGroup('telemedicineCase.code');

    $description = $group->getDescription($service, null);
    $rendered = Illuminate\Support\Facades\Blade::render('<p>{{ $description }}</p>', ['description' => $description]);

    expect($description)->toBeInstanceOf(Illuminate\Contracts\Support\Htmlable::class)
        ->and($rendered)->toMatch('/<span\s+class="fi-color fi-color-info/')
        ->toContain('fi-ta-group-manager-badge')
        ->not->toContain('&lt;span')
        ->not->toContain('&lt;svg');
});
