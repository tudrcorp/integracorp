<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use App\Models\OperationCoordinationService;
use App\Models\User;
use App\Support\Operations\CoordinationServiceCourtesy;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\CoordinationServiceQuoteManager;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    CoordinationServiceItemsManager::flushClinicalItemsCache();

    $this->actingAs(User::factory()->make([
        'id' => 999_999_001,
        'name' => 'QA COORDINACIONES',
        'email' => 'qa.coordinaciones@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));

    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

/**
 * @return array{queries: int, inserts: int}
 */
function medirRenderDelCuadroDeControl(int $porPagina): array
{
    CoordinationServiceItemsManager::flushClinicalItemsCache();

    $componente = Livewire::test(ListOperationCoordinationServices::class)
        ->set('tableRecordsPerPage', $porPagina);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $componente->call('loadTable')->assertSuccessful();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    return [
        'queries' => count($log),
        'inserts' => collect($log)
            ->filter(fn (array $query): bool => preg_match('/^\s*(insert|update|delete)\b/i', $query['query']) === 1)
            ->count(),
    ];
}

/**
 * Coordinaciones reales con la misma precarga que usa el cuadro de control.
 *
 * @return EloquentCollection<int, OperationCoordinationService>
 */
function coordinacionesPrecargadasComoEnLaTabla(int $limite = 40): EloquentCollection
{
    return OperationCoordinationService::query()
        ->where(function ($query): void {
            $query->whereHas('telemedicinePatientMedications')
                ->orWhereHas('telemedicinePatientLabs')
                ->orWhereHas('telemedicinePatientStudies')
                ->orWhereHas('telemedicinePatientSpecialties')
                ->orWhereHas('operationQuoteGenerators')
                ->orWhereHas('operationServiceOrders');
        })
        ->with([
            'telemedicinePatientMedications.operationInventory:id,is_covered',
            'telemedicinePatientLabs',
            'telemedicinePatientStudies',
            'telemedicinePatientSpecialties',
            'operationServiceOrders' => fn ($orders) => $orders->select(['id', 'order_number', 'status', 'operation_coordination_service_id']),
            'operationServiceOrders.operationServiceOrderItems:id,operation_service_order_id,item_name,category',
            'operationQuoteGenerators',
        ])
        ->latest('id')
        ->limit($limite)
        ->get();
}

function sinRelacionesPrecargadas(OperationCoordinationService $coordinacion): OperationCoordinationService
{
    return OperationCoordinationService::query()->findOrFail($coordinacion->getKey());
}

/*
 * ---------------------------------------------------------------------------
 * Presupuesto de consultas
 * ---------------------------------------------------------------------------
 */

it('pinta el cuadro de control con un número fijo de consultas, sin importar las filas', function (): void {
    $diez = medirRenderDelCuadroDeControl(10);
    $cincuenta = medirRenderDelCuadroDeControl(50);

    // Antes: 225 consultas con 10 filas y 1.066 con 50.
    expect($cincuenta['queries'])->toBeLessThanOrEqual(30)
        ->and($cincuenta['queries'] - $diez['queries'])->toBeLessThanOrEqual(2);
});

it('pintar el cuadro de control no escribe en la base', function (): void {
    expect(medirRenderDelCuadroDeControl(50)['inserts'])->toBe(0);
});

/*
 * ---------------------------------------------------------------------------
 * Lo precargado da lo mismo que lo consultado
 * ---------------------------------------------------------------------------
 */

it('arma los mismos ítems clínicos desde la precarga que desde la base', function (): void {
    $coordinaciones = coordinacionesPrecargadasComoEnLaTabla();

    if ($coordinaciones->isEmpty()) {
        $this->markTestSkipped('No hay coordinaciones con ítems clínicos.');
    }

    foreach ($coordinaciones as $coordinacion) {
        $precargados = CoordinationServiceItemsManager::preloadedManagementItems($coordinacion);
        $consultados = CoordinationServiceItemsManager::associatedServiceItemsForManagement(sinRelacionesPrecargadas($coordinacion));

        expect($precargados)->not->toBeNull()
            ->and($precargados->all())->toEqual($consultados->all());
    }
});

it('cuenta las mismas cortesías desde la precarga que desde la base', function (): void {
    $coordinaciones = coordinacionesPrecargadasComoEnLaTabla();

    if ($coordinaciones->isEmpty()) {
        $this->markTestSkipped('No hay coordinaciones con ítems clínicos.');
    }

    foreach ($coordinaciones as $coordinacion) {
        expect(CoordinationServiceCourtesy::courtesyItemsCount($coordinacion))
            ->toBe(CoordinationServiceCourtesy::courtesyItemsCount(sinRelacionesPrecargadas($coordinacion)));
    }
});

it('decide igual si «Gestionar Servicio» va deshabilitado', function (): void {
    $coordinaciones = coordinacionesPrecargadasComoEnLaTabla();

    if ($coordinaciones->isEmpty()) {
        $this->markTestSkipped('No hay coordinaciones con ítems clínicos.');
    }

    foreach ($coordinaciones as $coordinacion) {
        CoordinationServiceItemsManager::flushClinicalItemsCache();

        expect(CoordinationServiceItemsManager::manageServiceActionIsDisabled($coordinacion))
            ->toBe(! CoordinationServiceItemsManager::hasManageServiceSelectableItems(sinRelacionesPrecargadas($coordinacion)));
    }
});

it('resuelve las mismas órdenes y cotizaciones desde la precarga que desde la base', function (): void {
    $coordinaciones = coordinacionesPrecargadasComoEnLaTabla();

    if ($coordinaciones->isEmpty()) {
        $this->markTestSkipped('No hay coordinaciones con órdenes ni cotizaciones.');
    }

    foreach ($coordinaciones as $coordinacion) {
        $fresca = sinRelacionesPrecargadas($coordinacion);

        CoordinationServiceItemsManager::flushClinicalItemsCache();
        $ordenesPrecargadas = CoordinationServiceItemsManager::serviceOrderLinksByClinicalItemKey($coordinacion);
        CoordinationServiceItemsManager::flushClinicalItemsCache();
        $ordenesConsultadas = CoordinationServiceItemsManager::serviceOrderLinksByClinicalItemKey($fresca);

        expect($ordenesPrecargadas)->toEqual($ordenesConsultadas)
            ->and(CoordinationServiceQuoteManager::quoteLinksByClinicalItemKey($coordinacion))
            ->toEqual(CoordinationServiceQuoteManager::quoteLinksByClinicalItemKey($fresca))
            ->and(CoordinationServiceQuoteManager::coordinationQuotesForDisplay($coordinacion)->modelKeys())
            ->toBe(CoordinationServiceQuoteManager::coordinationQuotes($fresca)->modelKeys());
    }
});

/*
 * ---------------------------------------------------------------------------
 * Casos raros
 * ---------------------------------------------------------------------------
 */

it('no usa la precarga si falta alguna de las cuatro relaciones', function (): void {
    $coordinacion = new OperationCoordinationService;
    $coordinacion->setRelation('telemedicinePatientMedications', new EloquentCollection);
    $coordinacion->setRelation('telemedicinePatientLabs', new EloquentCollection);
    $coordinacion->setRelation('telemedicinePatientStudies', new EloquentCollection);

    expect(CoordinationServiceItemsManager::preloadedManagementItems($coordinacion))->toBeNull();

    $coordinacion->setRelation('telemedicinePatientSpecialties', new EloquentCollection);

    expect(CoordinationServiceItemsManager::preloadedManagementItems($coordinacion))->toBeEmpty();
});

it('una TPA/RETAIL standalone sin ítem no se siembra al pintarla', function (): void {
    $coordinacion = OperationCoordinationService::query()->first();

    if ($coordinacion === null) {
        $this->markTestSkipped('No hay coordinaciones.');
    }

    $coordinacion->servicie = 'TPA/RETAIL';
    $coordinacion->specific_service = 'TELEMEDICINA';

    foreach (CoordinationServiceItemsManager::CLINICAL_ITEM_RELATIONS as $relacion) {
        $coordinacion->setRelation($relacion, new EloquentCollection);
    }

    $coordinacion->setRelation('operationServiceOrders', new EloquentCollection);
    $coordinacion->setRelation('operationQuoteGenerators', new EloquentCollection);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $html = (string) CoordinationServiceItemsManager::renderCoordinationClinicalItemsCompactList($coordinacion);
    $disabled = CoordinationServiceItemsManager::manageServiceActionIsDisabled($coordinacion);
    $cortesias = CoordinationServiceCourtesy::courtesyItemsCount($coordinacion);
    $consultas = DB::getQueryLog();
    DB::disableQueryLog();

    expect($consultas)->toBe([])
        ->and($html)->toContain('Sin ítems')
        ->and($disabled)->toBeTrue()
        ->and($cortesias)->toBe(0);
});

/*
 * ---------------------------------------------------------------------------
 * Configuración de la tabla
 * ---------------------------------------------------------------------------
 */

it('la memoria se vacía al leer los registros y no en cada consulta derivada', function (): void {
    $tabla = file_get_contents(base_path('app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'));
    $pagina = file_get_contents(base_path('app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ListOperationCoordinationServices.php'));

    expect($tabla)
        ->not->toContain('CoordinationServiceItemsManager::flushClinicalItemsCache()')
        ->toContain('->selectCurrentPageOnly()')
        ->toContain("'operationServiceOrders.operationServiceOrderItems:id,operation_service_order_id,item_name,category'")
        ->toContain("'operationQuoteGenerators'")
        ->toContain('CoordinationServiceQuoteManager::hasCoordinationQuotesForDisplay($record)')
        ->and($pagina)
        ->toContain('public function getTableRecords(): Collection|Paginator|CursorPaginator')
        ->toContain('CoordinationServiceItemsManager::flushClinicalItemsCache()');
});

it('los catálogos del asistente sólo se consultan al abrirlo', function (): void {
    $tabla = file_get_contents(base_path('app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'));

    expect($tabla)
        ->toContain('->options(fn (): array => OperationTypeService::query()')
        ->toContain('->options(fn (): array => TelemedicinePriority::query()')
        ->toContain('->options(fn (): array => OperationInventoryUbication::query()')
        ->toContain('->options(fn (): array => OperationTypeNegotiation::query()');
});

it('la búsqueda global sólo recorre las columnas que usa Operaciones', function (): void {
    $componente = Livewire::test(ListOperationCoordinationServices::class);

    $buscables = collect($componente->instance()->getTable()->getColumns())
        ->filter(fn ($columna): bool => $columna->isGloballySearchable())
        ->keys()
        ->sort()
        ->values()
        ->all();

    expect($buscables)->toBe([
        'ci_patient',
        'patient',
        'reference_number',
        'servicie',
        'specific_service',
        'status',
        'supplier_service',
        'telemedicineCase.code',
    ]);
});

it('la búsqueda por paciente sigue encontrando la coordinación', function (): void {
    $coordinacion = OperationCoordinationService::query()
        ->where('status', 'PENDIENTE')
        ->whereNotNull('patient')
        ->where('patient', '!=', '')
        ->latest('id')
        ->first();

    if ($coordinacion === null) {
        $this->markTestSkipped('No hay coordinaciones con paciente.');
    }

    Livewire::test(ListOperationCoordinationServices::class)
        ->set('activeTab', 'pendiente')
        ->call('loadTable')
        ->searchTable((string) $coordinacion->patient)
        ->assertSuccessful()
        ->assertSee((string) $coordinacion->patient);
});
