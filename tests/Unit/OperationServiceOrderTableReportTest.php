<?php

declare(strict_types=1);

use App\Http\Controllers\OperationServiceOrderExportCsvController;
use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderTableReportPdfService;

uses(Tests\TestCase::class);

it('construye filas por tipo de servicio con conteos de estado', function (): void {
    $orders = collect([
        new OperationServiceOrder([
            'service_type' => 'MEDICAMENTOS',
            'status' => 'FINALIZADO',
            'total_amount_usd' => 10,
            'total_amount_ves' => 100,
        ]),
        new OperationServiceOrder([
            'service_type' => 'MEDICAMENTOS',
            'status' => 'PENDIENTE',
            'total_amount_usd' => 5,
            'total_amount_ves' => 50,
        ]),
        new OperationServiceOrder([
            'service_type' => 'LABORATORIOS',
            'status' => 'EN GESTION',
            'total_amount_usd' => 8,
            'total_amount_ves' => 80,
        ]),
    ]);

    $rows = OperationServiceOrderTableReportPdfService::buildByServiceRows($orders);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['service_type'])->toBe('MEDICAMENTOS')
        ->and($rows[0]['total'])->toBe(2)
        ->and($rows[0]['finalizado'])->toBe(1)
        ->and($rows[0]['pendiente'])->toBe(1)
        ->and($rows[1]['service_type'])->toBe('LABORATORIOS')
        ->and($rows[1]['en_gestion'])->toBe(1)
        ->and($rows[1]['total'])->toBe(1);
});

it('agrupa por paciente con conteo detallado de órdenes', function (): void {
    $anaCoord = new \App\Models\OperationCoordinationService([
        'patient' => 'Ana Pérez',
        'ci_patient' => 'V123',
    ]);
    $anaCoord->setRelation('telemedicinePatient', null);
    $anaCoord->setRelation('telemedicineCase', null);

    $luisCoord = new \App\Models\OperationCoordinationService([
        'patient' => 'Luis Gómez',
        'ci_patient' => 'V999',
    ]);
    $luisCoord->setRelation('telemedicinePatient', null);
    $luisCoord->setRelation('telemedicineCase', null);

    $orderA = new OperationServiceOrder([
        'order_number' => 'OS-001',
        'service_type' => 'MEDICAMENTOS',
        'status' => 'FINALIZADO',
        'total_amount_usd' => 10,
        'total_amount_ves' => 100,
        'description' => 'A',
    ]);
    $orderA->setRelation('operationCoordinationService', $anaCoord);
    $orderA->setRelation('supplier', null);
    $orderA->setRelation('telemedicineSupplier', null);
    $orderA->setRelation('telemedicinePriority', null);
    $orderA->created_at = now()->subHour();
    $orderA->approved_at = now()->subHour();

    $orderB = new OperationServiceOrder([
        'order_number' => 'OS-002',
        'service_type' => 'LABORATORIOS',
        'status' => 'PENDIENTE',
        'total_amount_usd' => 20,
        'total_amount_ves' => 200,
        'description' => 'B',
    ]);
    $orderB->setRelation('operationCoordinationService', $anaCoord);
    $orderB->setRelation('supplier', null);
    $orderB->setRelation('telemedicineSupplier', null);
    $orderB->setRelation('telemedicinePriority', null);
    $orderB->created_at = now();
    $orderB->approved_at = now();

    $orderC = new OperationServiceOrder([
        'order_number' => 'OS-003',
        'service_type' => 'MEDICAMENTOS',
        'status' => 'EN GESTION',
        'total_amount_usd' => 5,
        'total_amount_ves' => 50,
        'description' => 'C',
    ]);
    $orderC->setRelation('operationCoordinationService', $luisCoord);
    $orderC->setRelation('supplier', null);
    $orderC->setRelation('telemedicineSupplier', null);
    $orderC->setRelation('telemedicinePriority', null);
    $orderC->created_at = now();
    $orderC->approved_at = null;

    $groups = OperationServiceOrderTableReportPdfService::buildByPatientGroups(collect([$orderA, $orderB, $orderC]));

    expect($groups)->toHaveCount(2)
        ->and($groups[0]['patient'])->toBe('Ana Pérez')
        ->and($groups[0]['orders_count'])->toBe(2)
        ->and($groups[0]['document'])->toBe('V123')
        ->and($groups[1]['patient'])->toBe('Luis Gómez')
        ->and($groups[1]['orders_count'])->toBe(1);
});

it('renderiza vistas PDF de reportes de órdenes sin error', function (): void {
    $byPatient = view('documents.operation-service-orders-by-patient-report', [
        'groups' => collect(),
        'totalOrders' => 0,
        'totalPatients' => 0,
        'generatedAt' => now(),
        'logoDataUri' => '',
    ])->render();

    $byService = view('documents.operation-service-orders-by-service-report', [
        'rows' => [],
        'totalOrders' => 0,
        'generatedAt' => now(),
        'logoDataUri' => '',
    ])->render();

    expect($byPatient)
        ->toContain('Reporte detallado de órdenes por paciente')
        ->toContain('INTEGRACORP')
        ->and($byService)
        ->toContain('Reporte de servicios realizados')
        ->toContain('Conteo de órdenes por tipo de servicio');
});

it('expone rutas y wiring de reportes PDF y export CSV de órdenes', function (): void {
    expect(route('operations.operation-service-orders.report.preview', [
        'token' => 'abc',
        'type' => 'by-patient',
    ]))->toContain('operation-service-orders/report/preview')
        ->and(route('operations.operation-service-orders.report.download', [
            'token' => 'abc',
            'type' => 'by-service',
        ]))->toContain('operation-service-orders/report/download')
        ->and(route('operations.operation-service-orders.export-csv', ['token' => 'abc']))
        ->toContain('export-operation-service-orders-csv');

    $list = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Pages/ListOperationServiceOrders.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    expect($list)
        ->toContain("Action::make('report_orders_by_patient')")
        ->toContain("Action::make('report_orders_by_service')")
        ->toContain('OperationServiceOrderTableReportPdfService::TYPE_BY_PATIENT')
        ->toContain('OperationServiceOrderTableReportPdfService::TYPE_BY_SERVICE')
        ->and($table)
        ->toContain("BulkAction::make('export_service_orders_csv')")
        ->toContain('OperationServiceOrderExportCsvController::storeIdsAndGetToken')
        ->toContain('CsvExportDownloadTrigger::fromAction');
});

it('invitado es redirigido al intentar vista previa o export CSV de órdenes', function (): void {
    $token = OperationServiceOrderTableReportPdfService::storeIdsAndGetToken([]);

    $this->get(route('operations.operation-service-orders.report.preview', [
        'token' => $token,
        'type' => OperationServiceOrderTableReportPdfService::TYPE_BY_PATIENT,
    ]))->assertRedirect();

    $csvToken = OperationServiceOrderExportCsvController::storeIdsAndGetToken([1]);

    $this->get(route('operations.operation-service-orders.export-csv', ['token' => $csvToken]))
        ->assertRedirect();
});

it('almacena y recupera token de reporte PDF', function (): void {
    $token = OperationServiceOrderTableReportPdfService::storeIdsAndGetToken([3, 7, 0, 'x']);

    expect(OperationServiceOrderTableReportPdfService::pullIdsFromToken($token))->toBe([3, 7])
        ->and(OperationServiceOrderTableReportPdfService::pullIdsFromToken('missing'))->toBeNull()
        ->and(OperationServiceOrderTableReportPdfService::isValidType('by-patient'))->toBeTrue()
        ->and(OperationServiceOrderTableReportPdfService::isValidType('other'))->toBeFalse();
});
