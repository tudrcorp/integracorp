<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Services\OperationCoordinationServiceReversalService;
use App\Support\Operations\CoordinationServiceBulkReversal;

it('solo permite reversar servicios en estatus pendiente', function (): void {
    $service = app(OperationCoordinationServiceReversalService::class);

    expect($service->statusIsReversible('PENDIENTE'))->toBeTrue()
        ->and($service->statusIsReversible('pendiente'))->toBeTrue()
        ->and($service->statusIsReversible('EN GESTION'))->toBeFalse()
        ->and($service->statusIsReversible('FINALIZADO'))->toBeFalse()
        ->and($service->statusIsReversible('CANCELADA'))->toBeFalse()
        ->and($service->statusIsReversible('PENDIENTE POR RESULTADOS'))->toBeFalse();
});

it('construye la descripción de bitácora con prefijo, servicio y motivo', function (): void {
    $coordination = new OperationCoordinationService([
        'specific_service' => 'LABORATORIO',
        'servicie' => 'SERVICIO MEDICO',
        'reference_number' => 'REF-100',
        'patient' => 'Juan Pérez',
    ]);
    $coordination->id = 42;

    $description = app(OperationCoordinationServiceReversalService::class)
        ->buildBitacoraDescription($coordination, 'Duplicado por error de carga.');

    expect($description)
        ->toContain(OperationCoordinationServiceReversalService::OBSERVATION_PREFIX)
        ->toContain('Servicio ID: 42')
        ->toContain('Servicio: LABORATORIO')
        ->toContain('Referencia: REF-100')
        ->toContain('Paciente: Juan Pérez')
        ->toContain('Motivo: Duplicado por error de carga.');
});

it('rechaza el reverso masivo si la observación es corta', function (): void {
    $service = app(OperationCoordinationServiceReversalService::class);
    $pending = new OperationCoordinationService(['status' => 'PENDIENTE']);
    $pending->id = 1;

    expect(fn () => $service->reverseMany([$pending], 'corto'))
        ->toThrow(InvalidArgumentException::class, 'al menos 10 caracteres');
});

it('rechaza el reverso masivo si existe un servicio distinto a pendiente', function (): void {
    $service = app(OperationCoordinationServiceReversalService::class);

    $pending = new OperationCoordinationService(['status' => 'PENDIENTE', 'reference_number' => 'REF-OK']);
    $pending->id = 1;

    $inProgress = new OperationCoordinationService(['status' => 'EN GESTION', 'reference_number' => 'REF-GESTION']);
    $inProgress->id = 2;

    expect(fn () => $service->reverseMany(
        [$pending, $inProgress],
        'Motivo válido de reverso con suficiente detalle.',
    ))
        ->toThrow(InvalidArgumentException::class, 'estatus distinto a PENDIENTE');
});

it('expone la bulk action de reverso en la tabla de coordinación', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceBulkReversal.php');
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/OperationCoordinationServiceReversalService.php');

    $action = CoordinationServiceBulkReversal::makeBulkAction();

    expect($action->getName())->toBe('reverse_coordination_services')
        ->and($action->getLabel())->toBe('Reversar servicios');

    expect($table)
        ->toContain('CoordinationServiceBulkReversal::makeBulkAction()');

    expect($support)
        ->toContain('reversal_note')
        ->toContain('OperationCoordinationServiceReversalService')
        ->toContain('Reversar servicios');

    expect($service)
        ->toContain('ObservationCase')
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_OPERATIONS_COORDINATION_SERVICE_BULK_REVERSED')
        ->toContain('AUDIT_OPERATIONS_COORDINATION_SERVICE_REVERSED')
        ->toContain('$service->delete()')
        ->toContain("ALLOWED_STATUS = 'PENDIENTE'");
});
