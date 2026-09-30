<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\CoordinationServiceTabCounts;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Lee la base real dentro de una transacción que siempre se revierte (la caché
 * local usa la tabla `cache`), con la caché en memoria para no tocar la de la app.
 */
beforeEach(function (): void {
    config(['cache.default' => 'array']);
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

function analistaDeOperacionesParaCuadro(): User
{
    $user = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->where('departament', 'like', '%OPERACIONES%')
        ->whereNull('supplier_id')
        ->first();

    if (! $user instanceof User) {
        test()->markTestSkipped('No hay analistas de Operaciones en la base.');
    }

    return $user;
}

/*
 * ---------------------------------------------------------------------------
 * Columnas visibles por defecto
 * ---------------------------------------------------------------------------
 */

it('oculta por defecto las columnas financieras y de detalle del paciente', function (string $column): void {
    Filament::setCurrentPanel('operations');
    $this->actingAs(analistaDeOperacionesParaCuadro());

    $table = Livewire::test(ListOperationCoordinationServices::class)->instance()->getTable();
    $tableColumn = $table->getColumn($column);

    expect($tableColumn)->not->toBeNull()
        ->and($tableColumn->isToggleable())->toBeTrue()
        ->and($tableColumn->isToggledHiddenByDefault())->toBeTrue();
})->with([
    'type_negotiation', 'status_negotiation', 'neto', 'porcen_tdec', 'quote_price', 'negotiation',
    'porcen_discount', 'price_discount', 'quote_number', 'approved_number', 'bill_number', 'bill_price',
    'bill_date', 'negotiation_description', 'birth_date_patient', 'relationship_patient', 'age_patient',
    'contractor', 'state_id', 'city_id', 'address', 'phone_holder', 'symptoms_diagnosis', 'observations',
    'incidence', 'qc_description', 'farmadoc',
]);

it('mantiene visibles las columnas con las que se opera', function (string $column): void {
    Filament::setCurrentPanel('operations');
    $this->actingAs(analistaDeOperacionesParaCuadro());

    $tableColumn = Livewire::test(ListOperationCoordinationServices::class)->instance()->getTable()->getColumn($column);

    expect($tableColumn)->not->toBeNull()
        ->and($tableColumn->isToggledHiddenByDefault())->toBeFalse();
})->with([
    'telemedicineCase.code', 'clinical_management_items', 'servicie', 'specific_service', 'status',
    'telemedicinePriority.name', 'patient', 'ci_patient', 'supplier_service', 'service_order_number', 'updated_at',
]);

/*
 * ---------------------------------------------------------------------------
 * Caché de contadores
 * ---------------------------------------------------------------------------
 */

it('la caché devuelve lo mismo que el cálculo y no recalcula dentro del TTL', function (): void {
    $this->actingAs(analistaDeOperacionesParaCuadro());
    $calls = 0;
    $compute = function () use (&$calls): array {
        $calls++;

        return ['todas' => 7, 'en_gestion' => 3];
    };

    expect(CoordinationServiceTabCounts::remember($compute))->toBe(['todas' => 7, 'en_gestion' => 3])
        ->and(CoordinationServiceTabCounts::remember($compute))->toBe(['todas' => 7, 'en_gestion' => 3])
        ->and($calls)->toBe(1);
});

it('separa la caché por proveedor, ATENMEDI y permiso de eliminados', function (): void {
    $analista = analistaDeOperacionesParaCuadro();
    $this->actingAs($analista);
    $tdg = CoordinationServiceTabCounts::scopeSignature();

    $proveedor = $analista->replicate();
    $proveedor->supplier_id = 999_999;
    $this->actingAs($proveedor);
    $deProveedor = CoordinationServiceTabCounts::scopeSignature();

    $atenmedi = $analista->replicate();
    $atenmedi->departament = ['OPERACIONES', 'ATENMEDI'];
    $this->actingAs($atenmedi);
    $deAtenmedi = CoordinationServiceTabCounts::scopeSignature();

    expect($tdg)->toContain('supplier=tdg')
        ->and($deProveedor)->toContain('supplier=999999')
        ->and($deAtenmedi)->toContain('atenmedi=1')
        ->and(array_unique([$tdg, $deProveedor, $deAtenmedi]))->toHaveCount(3);
});

it('invalidar cambia la clave, pero espera a que cierre la transacción', function (): void {
    $this->actingAs(analistaDeOperacionesParaCuadro());
    $antes = CoordinationServiceTabCounts::cacheKey();

    // Con una transacción abierta la invalidación queda pendiente del commit.
    CoordinationServiceTabCounts::invalidate();
    expect(CoordinationServiceTabCounts::cacheKey())->toBe($antes);

    // Sin transacción (se revierte la del test, que no escribió nada) se aplica en el acto.
    DB::rollBack();
    try {
        CoordinationServiceTabCounts::invalidate();
        expect(CoordinationServiceTabCounts::cacheKey())->not->toBe($antes);
    } finally {
        DB::beginTransaction();
    }
});

it('registra la invalidación al escribir coordinaciones, ítems y casos', function (string $model): void {
    expect(Event::hasListeners('eloquent.saved: '.$model))->toBeTrue()
        ->and(Event::hasListeners('eloquent.deleted: '.$model))->toBeTrue();
})->with([
    OperationCoordinationService::class,
    TelemedicinePatientMedications::class,
    TelemedicineCase::class,
]);

/*
 * ---------------------------------------------------------------------------
 * Paginador de «Todas»
 * ---------------------------------------------------------------------------
 */

it('en «Todas» el total del paginador coincide con el conteo directo', function (): void {
    Filament::setCurrentPanel('operations');
    $user = analistaDeOperacionesParaCuadro();
    $this->actingAs($user);

    $directo = OperationCoordinationServicesTable::applyHideFullyFinalizedScope(
        OperationsSupplierScope::coordinationServiceQuery()
    )->count();

    $records = Livewire::test(ListOperationCoordinationServices::class)
        ->call('loadTable')
        ->instance()
        ->getTableRecords();

    expect($records->total())->toBe($directo);
});

it('con búsqueda el paginador vuelve a contar y no usa el conteo de la pestaña', function (): void {
    Filament::setCurrentPanel('operations');
    $this->actingAs(analistaDeOperacionesParaCuadro());

    $records = Livewire::test(ListOperationCoordinationServices::class)
        ->call('loadTable')
        ->set('tableSearch', 'zzz-no-existe-zzz')
        ->instance()
        ->getTableRecords();

    expect($records->total())->toBe(0);
});

/*
 * ---------------------------------------------------------------------------
 * Índices
 * ---------------------------------------------------------------------------
 */

it('la tabla de coordinaciones tiene los índices del cuadro de control', function (): void {
    $indexes = collect(DB::select(
        'SELECT DISTINCT INDEX_NAME AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        ['operation_coordination_services'],
    ))->pluck('name')->all();

    expect($indexes)->toContain('ocs_supplier_id_index', 'ocs_telemedicine_case_id_index', 'ocs_status_index', 'ocs_managed_by_index');
});
