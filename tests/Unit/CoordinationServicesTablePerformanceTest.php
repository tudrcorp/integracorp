<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use App\Models\OperationCoordinationService;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\CoordinationServiceItemsManager;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    CoordinationServiceItemsManager::flushClinicalItemsCache();
});

afterEach(fn () => DB::rollBack());

function coordinacionConItems(): ?OperationCoordinationService
{
    return OperationCoordinationService::query()
        ->whereHas('telemedicinePatientMedications')
        ->with([
            'telemedicinePatientMedications.operationInventory:id,is_covered',
            'telemedicinePatientLabs',
            'telemedicinePatientStudies',
            'telemedicinePatientSpecialties',
        ])
        ->first();
}

/*
 * ---------------------------------------------------------------------------
 * Índices
 * ---------------------------------------------------------------------------
 */

it('indexa coordinación y estatus en las cuatro tablas de ítems clínicos', function (string $tabla): void {
    expect(Schema::hasIndex($tabla, $tabla.'_coordination_status_index'))->toBeTrue()
        ->and(Schema::hasIndex($tabla, $tabla.'_case_index'))->toBeTrue();
})->with([
    'telemedicine_patient_medications',
    'telemedicine_patient_labs',
    'telemedicine_patient_studies',
    'telemedicine_patient_specialties',
]);

it('resuelve el filtro de pendientes con índice y no con escaneo completo', function (): void {
    $plan = DB::select(
        'EXPLAIN SELECT COUNT(*) FROM operation_coordination_services o WHERE EXISTS ('
        .'SELECT 1 FROM telemedicine_patient_medications m '
        .'WHERE m.operation_coordination_service_id = o.id AND m.status IN (?, ?))',
        ['PENDIENTE', 'EN GESTION']
    );

    $sobreItems = collect($plan)->firstWhere('table', 'm');

    expect($sobreItems)->not->toBeNull()
        ->and($sobreItems->key)->not->toBeNull()
        ->and($sobreItems->type)->not->toBe('ALL');
});

/*
 * ---------------------------------------------------------------------------
 * Memoria por pasada de render
 * ---------------------------------------------------------------------------
 */

it('no repite consultas al resolver dos veces los ítems de la misma coordinación', function (): void {
    $coordinacion = coordinacionConItems();

    if ($coordinacion === null) {
        $this->markTestSkipped('No hay coordinaciones con ítems clínicos.');
    }

    Filament::setCurrentPanel('operations');
    CoordinationServiceItemsManager::flushClinicalItemsCache();

    CoordinationServiceItemsManager::clinicalItemsWithEffectiveDisplayStatus($coordinacion);

    DB::flushQueryLog();
    DB::enableQueryLog();
    CoordinationServiceItemsManager::clinicalItemsWithEffectiveDisplayStatus($coordinacion);
    CoordinationServiceItemsManager::allAssociatedItemsAreClosed($coordinacion);
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($consultas)->toBe(0);
});

it('vuelve a consultar después de vaciar la memoria', function (): void {
    $coordinacion = coordinacionConItems();

    if ($coordinacion === null) {
        $this->markTestSkipped('No hay coordinaciones con ítems clínicos.');
    }

    Filament::setCurrentPanel('operations');
    CoordinationServiceItemsManager::clinicalItemsWithEffectiveDisplayStatus($coordinacion);
    CoordinationServiceItemsManager::flushClinicalItemsCache();

    DB::flushQueryLog();
    DB::enableQueryLog();
    CoordinationServiceItemsManager::clinicalItemsWithEffectiveDisplayStatus($coordinacion);
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($consultas)->toBeGreaterThan(0);
});

it('la tabla vacía la memoria al construir su consulta', function (): void {
    $tabla = file_get_contents(base_path('app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'));

    expect($tabla)
        ->toContain('CoordinationServiceItemsManager::flushClinicalItemsCache()')
        ->toContain('->deferLoading()');
});

/*
 * ---------------------------------------------------------------------------
 * Contadores de pestañas
 * ---------------------------------------------------------------------------
 */

it('calcula los contadores por estatus en una sola agregación', function (): void {
    $pagina = file_get_contents(base_path('app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ListOperationCoordinationServices.php'));

    expect($pagina)
        ->toContain('protected function tabCounts(): array')
        ->toContain("groupBy('estatus')")
        // ya no hay un ->count() por pestaña
        ->not->toContain("->where('status', 'EN GESTION')->count()")
        ->not->toContain("->where('status', 'PENDIENTE')->count()");
});

it('los contadores agregados coinciden con el conteo por estatus', function (): void {
    $porEstatus = OperationsSupplierScope::coordinationServiceQuery()
        ->toBase()
        ->selectRaw('UPPER(TRIM(status)) AS estatus, COUNT(*) AS total')
        ->groupBy('estatus')
        ->pluck('total', 'estatus')
        ->all();

    foreach (['EN GESTION', 'PENDIENTE', 'FINALIZADO'] as $estatus) {
        $directo = OperationsSupplierScope::coordinationServiceQuery()->where('status', $estatus)->count();

        expect((int) ($porEstatus[$estatus] ?? 0))->toBe($directo);
    }
});

/*
 * ---------------------------------------------------------------------------
 * Helpers que dejan de reconsultar
 * ---------------------------------------------------------------------------
 */

it('deriva el permiso de gestión de los ítems ya resueltos', function (): void {
    $manager = file_get_contents(base_path('app/Support/Operations/CoordinationServiceItemsManager.php'));

    expect($manager)
        ->toContain('$canShowManageLink = $itemsForDisplay->contains(')
        ->not->toContain('$canShowManageLink = ! self::manageServiceActionIsDisabled($record)');
});

it('el tipo de orden usa la relación precargada cuando está disponible', function (): void {
    $tabla = file_get_contents(base_path('app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'));

    expect($tabla)
        ->toContain('hasItemOutsideManagement')
        ->toContain('relationLoaded($relacion)')
        // los NULL se descartan igual que en SQL
        ->toContain('$item->status === null');
});

it('el tipo de orden da el mismo resultado por relación precargada que por consulta', function (): void {
    $coordinacion = coordinacionConItems();

    if ($coordinacion === null) {
        $this->markTestSkipped('No hay coordinaciones con ítems clínicos.');
    }

    $relaciones = [
        'telemedicinePatientMedications',
        'telemedicinePatientStudies',
        'telemedicinePatientLabs',
        'telemedicinePatientSpecialties',
    ];

    foreach ($relaciones as $relacion) {
        $precargado = $coordinacion->getRelation($relacion)->contains(function (object $item): bool {
            if ($item->status === null) {
                return false;
            }

            return mb_strtoupper(trim((string) $item->status)) !== 'EN GESTION';
        });

        $consultado = $coordinacion->{$relacion}()->where('status', '!=', 'EN GESTION')->exists();

        expect($precargado)->toBe($consultado);
    }
});

/*
 * ---------------------------------------------------------------------------
 * Render real
 * ---------------------------------------------------------------------------
 */

it('renderiza el cuadro de control con sus pestañas', function (): void {
    $usuario = User::factory()->create([
        'email' => 'qa.coordinaciones@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);

    $this->actingAs($usuario);
    Filament::setCurrentPanel('operations');

    Livewire::test(ListOperationCoordinationServices::class)
        ->assertSuccessful()
        ->assertSee('EN GESTION');
});
