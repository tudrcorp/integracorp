<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderListDisplay;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    TelemedicinePatientDisplayName::flushCache();
});

afterEach(fn () => DB::rollBack());

/*
 * ---------------------------------------------------------------------------
 * Schema::hasTable cacheado
 * ---------------------------------------------------------------------------
 */

it('consulta information_schema una sola vez aunque resuelva muchos nombres', function (): void {
    $ordenes = OperationServiceOrder::query()
        ->with(['operationCoordinationService.telemedicinePatient'])
        ->limit(25)
        ->get();

    if ($ordenes->isEmpty()) {
        $this->markTestSkipped('No hay órdenes de servicio en la base.');
    }

    TelemedicinePatientDisplayName::flushCache();
    DB::flushQueryLog();
    DB::enableQueryLog();

    foreach ($ordenes as $orden) {
        OperationServiceOrderListDisplay::patientFullName($orden);
    }

    $schemaQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'information_schema'))
        ->count();

    DB::disableQueryLog();

    expect($schemaQueries)->toBeLessThanOrEqual(2);
});

it('vacía las cachés estáticas cuando se le pide', function (): void {
    TelemedicinePatientDisplayName::flushCache();

    expect(method_exists(TelemedicinePatientDisplayName::class, 'flushCache'))->toBeTrue();
});

/*
 * ---------------------------------------------------------------------------
 * Memoria del nombre resuelto
 * ---------------------------------------------------------------------------
 */

it('no repite consultas cuando la columna pide el mismo nombre dos veces', function (): void {
    $orden = OperationServiceOrder::query()
        ->whereHas('operationCoordinationService', fn ($q) => $q->whereNotNull('telemedicine_patient_id'))
        ->with(['operationCoordinationService.telemedicinePatient'])
        ->first();

    if ($orden === null) {
        $this->markTestSkipped('No hay órdenes con paciente de telemedicina asociado.');
    }

    TelemedicinePatientDisplayName::flushCache();

    OperationServiceOrderListDisplay::patientFullName($orden);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $primerValor = OperationServiceOrderListDisplay::patientFullName($orden);
    $segundoValor = OperationServiceOrderListDisplay::patientFullName($orden);
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($consultas)->toBe(0)
        ->and($primerValor)->toBe($segundoValor);
});

/*
 * ---------------------------------------------------------------------------
 * Vía rápida indexada
 * ---------------------------------------------------------------------------
 */

it('busca el documento por igualdad antes de recurrir al barrido tolerante', function (): void {
    $fuente = file_get_contents(base_path('app/Support/Telemedicine/TelemedicinePatientDisplayName.php'));

    expect($fuente)
        ->toContain('exactDocumentLookup')
        ->toContain("whereIn('nro_identificacion', \$values)")
        // el respaldo tolerante sigue existiendo para los documentos que no calzan exacto
        ->toContain('constrainAffiliateLookup');
});

/*
 * ---------------------------------------------------------------------------
 * El barrido de caducidad ya no corre en cada render
 * ---------------------------------------------------------------------------
 */

it('la tabla usa el barrido con freno y no el barrido completo', function (): void {
    $tabla = file_get_contents(base_path('app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php'));

    expect($tabla)
        ->toContain('expireEligibleOrdersThrottled')
        ->not->toContain("expireEligibleOrders('system')");
});

it('el barrido con freno solo se ejecuta una vez dentro de su ventana', function (): void {
    Cache::forget('operation-service-orders:expiry-sweep');

    expect(Cache::add('operation-service-orders:expiry-sweep', true, OperationServiceOrderValidity::SWEEP_THROTTLE_SECONDS))->toBeTrue();
    expect(Cache::add('operation-service-orders:expiry-sweep', true, OperationServiceOrderValidity::SWEEP_THROTTLE_SECONDS))->toBeFalse();

    Cache::forget('operation-service-orders:expiry-sweep');
});

it('conserva el barrido completo para el scheduler', function (): void {
    expect(method_exists(OperationServiceOrderValidity::class, 'expireEligibleOrders'))->toBeTrue()
        ->and(method_exists(OperationServiceOrderValidity::class, 'expireEligibleOrdersThrottled'))->toBeTrue();
});

/*
 * ---------------------------------------------------------------------------
 * Índices
 * ---------------------------------------------------------------------------
 */

it('indexa las columnas por las que se filtra, ordena y caduca', function (string $indice): void {
    expect(Schema::hasIndex('operation_service_orders', $indice))->toBeTrue();
})->with([
    'operation_service_orders_status_index',
    'operation_service_orders_administrative_status_index',
    'operation_service_orders_created_at_index',
    'operation_service_orders_status_approved_at_index',
    'operation_service_orders_coordination_index',
    'operation_service_orders_supplier_id_index',
]);

it('indexa el número de referencia del servicio de coordinación', function (): void {
    expect(Schema::hasIndex(
        'operation_coordination_services',
        'operation_coordination_services_reference_number_index',
    ))->toBeTrue();
});

it('difiere la carga de la tabla para pintar la estructura de inmediato', function (): void {
    $tabla = file_get_contents(base_path('app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php'));

    expect($tabla)->toContain('->deferLoading()');
});
