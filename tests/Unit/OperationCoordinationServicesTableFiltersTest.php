<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTableFilters;
use App\Models\OperationCoordinationService;
use App\Models\User;
use App\Support\Operations\CoordinationServiceCaseDeletion;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Solo lee la base; la transacción revertida garantiza que nada quede escrito.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

/**
 * SUPERADMIN: un analista sin el permiso del recurso recibe 403.
 */
function analistaTdgDeCoordinaciones(): User
{
    return User::factory()->make([
        'id' => 999_999_301,
        'email' => 'qa.filtros.coordinacion@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
    ]);
}

/**
 * @param  list<string>  $statuses
 * @return list<string>
 */
function estatusDevueltos(array $statuses, bool $eliminados = false): array
{
    return OperationCoordinationServicesTableFilters::applyStatusFilter(OperationCoordinationService::query(), $statuses, $eliminados)
        ->toBase()
        ->selectRaw('DISTINCT UPPER(TRIM(status)) AS estatus')
        ->pluck('estatus')
        ->all();
}

it('agrupa las variantes de estatus como las antiguas pestañas', function (): void {
    expect(OperationCoordinationServicesTableFilters::statusValues(['CANCELADA', 'NOVEDAD ADMON', 'PENDIENTE']))
        ->toBe(['CANCELADA', 'CANCELADO', 'NOVEDAD ADMON', 'NOVEDAD ADMON ESTUDIO', 'PENDIENTE'])
        ->and(OperationCoordinationServicesTableFilters::statusValues(['INVENTADO']))->toBe([])
        ->and(OperationCoordinationServicesTableFilters::DEFAULT_STATUSES)->toBe(['EN GESTION', 'PENDIENTE']);
});

it('por defecto solo trae servicios EN GESTION y PENDIENTE', function (): void {
    expect(estatusDevueltos(OperationCoordinationServicesTableFilters::DEFAULT_STATUSES))
        ->each->toBeIn(['EN GESTION', 'PENDIENTE']);
});

it('sin estatus elegido trae lo que está por gestionar, igual que la antigua pestaña «Todas»', function (): void {
    $porGestionar = OperationCoordinationServicesTable::applyHideFullyFinalizedScope(OperationCoordinationService::query())->count();

    expect(OperationCoordinationServicesTableFilters::applyStatusFilter(OperationCoordinationService::query(), [])->count())
        ->toBe($porGestionar)
        ->and(estatusDevueltos([]))->not->toContain('REASIGNADO A TDG');
});

it('FINALIZADO trae los servicios con todos sus ítems cerrados y se suma a los demás estatus', function (): void {
    $finalizados = OperationCoordinationServicesTable::applyFullyFinalizedScope(OperationCoordinationService::query())->count();
    $enGestion = OperationCoordinationServicesTableFilters::applyStatusFilter(OperationCoordinationService::query(), ['EN GESTION'])->count();

    expect(OperationCoordinationServicesTableFilters::applyStatusFilter(OperationCoordinationService::query(), ['FINALIZADO'])->count())
        ->toBe($finalizados)
        // Las dos vistas son complementarias: sumar no duplica servicios.
        ->and(OperationCoordinationServicesTableFilters::applyStatusFilter(OperationCoordinationService::query(), ['EN GESTION', 'FINALIZADO'])->count())
        ->toBe($enGestion + $finalizados);
});

it('ignora valores de estatus que no están en la lista', function (): void {
    $sql = OperationCoordinationServicesTableFilters::applyStatusFilter(OperationCoordinationService::query(), ['DROP TABLE'])->toRawSql();

    expect($sql)->not->toContain('DROP TABLE')
        ->and(OperationCoordinationServicesTableFilters::applyStatusFilter(OperationCoordinationService::query(), ['DROP TABLE'])->count())
        ->toBe(OperationCoordinationServicesTable::applyHideFullyFinalizedScope(OperationCoordinationService::query())->count());
});

it('en ELIMINADOS el estatus filtra la columna directa y vacío no restringe', function (): void {
    $base = OperationCoordinationService::query()->toRawSql();

    expect(OperationCoordinationServicesTableFilters::applyStatusFilter(OperationCoordinationService::query(), [], true)->toRawSql())
        ->toBe($base)
        ->and(OperationCoordinationServicesTableFilters::applyStatusFilter(OperationCoordinationService::query(), ['CANCELADA'], true)->toRawSql())
        ->toContain("'CANCELADA'")
        ->toContain("'CANCELADO'")
        // Sin el scope de ítems abiertos: en ELIMINADOS no hay trabajo que separar.
        ->not->toContain('telemedicine_patient_medications');
});

it('el tipo de afiliado filtra por la afiliación del paciente', function (string $tipo): void {
    $tipos = OperationCoordinationServicesTableFilters::applyAffiliationTypeFilter(OperationCoordinationService::query(), mb_strtolower($tipo))
        ->with('telemedicinePatient:id,type_affiliation')
        ->limit(50)
        ->get()
        ->map(fn (OperationCoordinationService $servicio): string => mb_strtoupper(trim((string) $servicio->telemedicinePatient?->type_affiliation)))
        ->unique()
        ->values()
        ->all();

    expect($tipos)->each->toBe($tipo);
})->with(['INDIVIDUAL', 'CORPORATIVO']);

it('un tipo de afiliado desconocido no restringe', function (): void {
    expect(OperationCoordinationServicesTableFilters::applyAffiliationTypeFilter(OperationCoordinationService::query(), 'OTRO')->toRawSql())
        ->toBe(OperationCoordinationService::query()->toRawSql());
});

it('la fecha de registro incluye los días completos y corrige un rango invertido', function (): void {
    $sql = OperationCoordinationServicesTableFilters::applyRegistrationDateFilter(OperationCoordinationService::query(), [
        'desde' => '2026-10-10',
        'hasta' => '2026-10-01',
    ])->toRawSql();

    expect($sql)
        ->toContain("`operation_coordination_services`.`created_at` >= '2026-10-01 00:00:00'")
        ->toContain("`operation_coordination_services`.`created_at` <= '2026-10-10 23:59:59'")
        ->not->toContain('date(');

    expect(OperationCoordinationServicesTableFilters::applyRegistrationDateFilter(OperationCoordinationService::query(), ['desde' => 'no-es-fecha'])->toRawSql())
        ->toBe(OperationCoordinationService::query()->toRawSql());
});

it('el cuadro de control arranca con EN GESTION y PENDIENTE y ofrece los cinco filtros', function (): void {
    $this->actingAs(analistaTdgDeCoordinaciones());

    $component = Livewire::test(ListOperationCoordinationServices::class)
        ->assertOk()
        ->assertTableFilterExists('created_at')
        ->assertTableFilterExists('servicie')
        ->assertTableFilterExists('managed_by')
        ->assertTableFilterExists('affiliation_type')
        ->assertTableFilterExists(OperationCoordinationServicesTableFilters::STATUS_FILTER)
        ->assertTableFilterVisible('managed_by');

    expect($component->instance()->getTableFilterState(OperationCoordinationServicesTableFilters::STATUS_FILTER)['values'] ?? null)
        ->toBe(['EN GESTION', 'PENDIENTE']);
});

it('el listado ya no tiene pestañas', function (): void {
    $this->actingAs(analistaTdgDeCoordinaciones());

    expect(Livewire::test(ListOperationCoordinationServices::class)->instance()->getTabs())->toBe([]);
});

it('el filtro de eliminados solo cuenta con un isActive explícito', function (): void {
    $this->actingAs(analistaTdgDeCoordinaciones());

    $component = Livewire::test(ListOperationCoordinationServices::class);
    $filter = $component->instance()->getTable()->getFilter(CoordinationServiceCaseDeletion::DELETED_TAB);
    $base = OperationCoordinationService::query()->toRawSql();

    // Estado vacío (filtros guardados en sesión antes de que existiera): no aplica.
    expect($filter->applyToBaseQuery(OperationCoordinationService::query(), [])->toRawSql())->toBe($base)
        ->and($filter->applyToBaseQuery(OperationCoordinationService::query(), ['isActive' => false])->toRawSql())->toBe($base)
        ->and($filter->applyToBaseQuery(OperationCoordinationService::query(), ['isActive' => true])->toRawSql())
        ->not->toBe($base)
        ->toContain("'ELIMINADO'");
});

it('un usuario de proveedor no ve el filtro de proveedor', function (): void {
    $this->actingAs(User::factory()->make([
        'id' => 999_999_303,
        'email' => 'qa.proveedor@clinica.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
        'supplier_id' => 77,
    ]));

    $puedeFiltrar = (new ReflectionMethod(OperationCoordinationServicesTableFilters::class, 'userCanFilterBySupplier'))->invoke(null);

    expect($puedeFiltrar)->toBeFalse();
});
