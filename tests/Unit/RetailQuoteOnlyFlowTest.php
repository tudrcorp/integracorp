<?php

declare(strict_types=1);

use App\Http\Controllers\OperationServiceOrderController;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceFlowEvent;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicinePatientLab;
use App\Models\User;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\RetailServiceRegistration;
use App\Support\Operations\ServiceOrderFlowTrace;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Servicios RETAIL: siempre cotización → orden, nunca orden directa. Escribe dentro de una
 * transacción que siempre se revierte, sobre copias de filas reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Auth::setUser(User::factory()->make(['name' => 'Analista de prueba', 'departament' => ['SUPERADMIN']]));
});

afterEach(function (): void {
    DB::rollBack();
});

function retailTestCoordination(string $servicie = RetailServiceRegistration::SERVICIE): OperationCoordinationService
{
    $template = OperationCoordinationService::query()->where('specific_service', 'LABORATORIOS')->first()
        ?? OperationCoordinationService::query()->first();

    if (! $template instanceof OperationCoordinationService) {
        test()->markTestSkipped('No hay coordinaciones para clonar.');
    }

    $coordination = $template->replicate();
    $coordination->servicie = $servicie;
    $coordination->specific_service = 'LABORATORIOS';
    $coordination->status = 'PENDIENTE';
    $coordination->supplier_id = null;
    $coordination->direct_service_registration_id = null;
    $coordination->save();

    return $coordination;
}

function retailTestLab(OperationCoordinationService $coordination, string $coverage): TelemedicinePatientLab
{
    $template = TelemedicinePatientLab::query()->first();

    if (! $template instanceof TelemedicinePatientLab) {
        test()->markTestSkipped('No hay laboratorios para clonar.');
    }

    $lab = $template->replicate();
    $lab->operation_coordination_service_id = $coordination->id;
    $lab->type = $coverage;
    $lab->status = 'PENDIENTE';
    $lab->save();

    return $lab;
}

it('reconoce solo las coordinaciones RETAIL (no el registro directo ni otras)', function (): void {
    expect(RetailServiceRegistration::isRetailCoordination(['servicie' => 'TPA/RETAIL']))->toBeTrue()
        ->and(RetailServiceRegistration::isRetailCoordination(['servicie' => ' tpa/retail ']))->toBeTrue()
        ->and(RetailServiceRegistration::isRetailCoordination(['servicie' => 'TELEMEDICINA']))->toBeFalse()
        ->and(RetailServiceRegistration::isRetailCoordination(null))->toBeFalse()
        ->and(RetailServiceRegistration::isRetailCoordination(new OperationCoordinationService(['servicie' => 'TPA/RETAIL'])))->toBeTrue();
});

it('en RETAIL todo ítem se gestiona como no cubierto, aunque diga CUBIERTO', function (): void {
    $coordination = retailTestCoordination();
    $covered = retailTestLab($coordination, 'CUBIERTO');
    $notCovered = retailTestLab($coordination, 'NO CUBIERTO');
    $keys = ['lab:'.$covered->id, 'lab:'.$notCovered->id];

    expect(CoordinationServiceItemsManager::coveredSelectedManagementItemKeys($coordination->fresh(), $keys))->toBe([])
        ->and(CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys($coordination->fresh(), $keys))->toEqualCanonicalizing($keys);
});

it('fuera de RETAIL la cobertura del ítem se respeta como siempre', function (): void {
    $coordination = retailTestCoordination('TELEMEDICINA');
    $covered = retailTestLab($coordination, 'CUBIERTO');

    expect(CoordinationServiceItemsManager::coveredSelectedManagementItemKeys($coordination->fresh(), ['lab:'.$covered->id]))->toBe(['lab:'.$covered->id]);
});

it('rechaza en el servidor la orden directa de un RETAIL y deja la traza del intento', function (): void {
    $coordination = retailTestCoordination();
    $lab = retailTestLab($coordination, 'CUBIERTO');
    $orders = OperationServiceOrder::query()->count();

    $created = OperationServiceOrderController::create(
        ['service_type' => 'LABORATORIOS', 'order_number' => 'TEST-RETAIL'],
        $coordination->toArray(),
        new EloquentCollection([$lab]),
    );

    expect($created)->toBeFalse()
        ->and(OperationServiceOrder::query()->count())->toBe($orders)
        ->and(OperationServiceFlowEvent::query()
            ->where('operation_coordination_service_id', $coordination->id)
            ->where('event', ServiceOrderFlowTrace::ACTION_BLOCKED)
            ->exists())->toBeTrue();
});

it('solo la orden que nace de una cotización aprobada lleva la marca que la habilita', function (): void {
    $manager = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceQuoteManager.php');
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OperationServiceOrderController.php');

    expect($manager)->toContain('$payload[OperationServiceOrderController::FROM_APPROVED_QUOTE] = (int) $quote->getKey();')
        ->and(substr_count($manager, 'FROM_APPROVED_QUOTE'))->toBe(1)
        ->and($controller)->toContain('RetailServiceRegistration::isRetailCoordination($ownerRecord) && blank($data[self::FROM_APPROVED_QUOTE] ?? null)');
});

it('señala las órdenes RETAIL vigentes sin cotización y no las cerradas ni las cotizadas', function (): void {
    $retail = new OperationCoordinationService(['servicie' => 'TPA/RETAIL']);
    $order = fn (string $status, ?OperationQuoteGenerator $quote): OperationServiceOrder => (new OperationServiceOrder(['status' => $status]))
        ->setRelation('operationCoordinationService', $retail)
        ->setRelation('approvedOperationQuote', $quote);

    expect(RetailServiceRegistration::isRetailOrderWithoutQuote($order('EN GESTION', null)))->toBeTrue()
        ->and(RetailServiceRegistration::isRetailOrderWithoutQuote($order('FINALIZADO', null)))->toBeTrue()
        ->and(RetailServiceRegistration::isRetailOrderWithoutQuote($order('CANCELADA', null)))->toBeFalse()
        ->and(RetailServiceRegistration::isRetailOrderWithoutQuote($order('EN GESTION', new OperationQuoteGenerator)))->toBeFalse()
        ->and(RetailServiceRegistration::isRetailOrderWithoutQuote(
            (new OperationServiceOrder(['status' => 'EN GESTION']))
                ->setRelation('operationCoordinationService', new OperationCoordinationService(['servicie' => 'TELEMEDICINA']))
                ->setRelation('approvedOperationQuote', null)
        ))->toBeFalse();
});

it('el filtro de la tabla encuentra las órdenes RETAIL sin cotización con el mismo criterio', function (): void {
    $flagged = RetailServiceRegistration::scopeRetailOrdersWithoutQuote(OperationServiceOrder::query())
        ->with(['operationCoordinationService', 'approvedOperationQuote'])
        ->get();

    expect($flagged->every(fn (OperationServiceOrder $order): bool => RetailServiceRegistration::isRetailOrderWithoutQuote($order)))->toBeTrue();
});

it('oculta en pantalla los caminos de orden directa para RETAIL', function (): void {
    $root = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/';

    foreach (['Specialties', 'Labs', 'Studies', 'Medications'] as $kind) {
        expect((string) file_get_contents($root."OperationCoordinationServices/RelationManagers/TelemedicinePatient{$kind}RelationManager.php"))
            ->toContain('RetailServiceRegistration::isRetailCoordination($this->getOwnerRecord())');
    }

    expect((string) file_get_contents($root.'OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'))
        ->toContain('! RetailServiceRegistration::isRetailCoordination($record)')
        ->and((string) file_get_contents($root.'OperationServiceOrders/Schemas/OperationServiceOrderInfolist.php'))
        ->toContain('Servicio RETAIL sin cotización')
        ->and((string) file_get_contents($root.'OperationServiceOrders/Tables/OperationServiceOrdersTable.php'))
        ->toContain("Filter::make('retail_without_quote')");
});

it('la orden RETAIL que viene de una cotización aprobada pasa el bloqueo', function (): void {
    $coordination = retailTestCoordination();
    $lab = retailTestLab($coordination, 'NO CUBIERTO');

    OperationServiceOrderController::create(
        ['service_type' => 'LABORATORIOS', 'order_number' => 'TEST-RETAIL-Q', OperationServiceOrderController::FROM_APPROVED_QUOTE => 123],
        $coordination->toArray(),
        new EloquentCollection([$lab]),
    );

    expect(OperationServiceFlowEvent::query()
        ->where('operation_coordination_service_id', $coordination->id)
        ->where('event', ServiceOrderFlowTrace::ACTION_BLOCKED)
        ->exists())->toBeFalse();
});
