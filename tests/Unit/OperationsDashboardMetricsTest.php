<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Operations\OperationsDashboardMetrics;

it('identifica usuarios de proveedor con acceso al panel de operaciones', function (): void {
    $supplierUser = new User([
        'status' => 'ACTIVO',
        'supplier_id' => 15,
        'departament' => ['OPERACIONES'],
        'is_proveedor_amd' => false,
    ]);

    $amdUser = new User([
        'status' => 'ACTIVO',
        'supplier_id' => 20,
        'departament' => [],
        'is_proveedor_amd' => true,
    ]);

    $invalidUser = new User([
        'status' => 'ACTIVO',
        'supplier_id' => 21,
        'departament' => ['NEGOCIOS'],
        'is_proveedor_amd' => false,
    ]);

    expect(OperationsDashboardMetrics::userHasOperationsPortalAccess($supplierUser))->toBeTrue()
        ->and(OperationsDashboardMetrics::userHasOperationsPortalAccess($amdUser))->toBeTrue()
        ->and(OperationsDashboardMetrics::userHasOperationsPortalAccess($invalidUser))->toBeFalse();
});

it('expone consultas de métricas con scope de proveedor', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationsDashboardMetrics.php');

    expect($contents)
        ->toContain('OperationsSupplierScope::applyToQuery(TelemedicinePatient::query())')
        ->toContain('OperationsSupplierScope::applyToQuery(TelemedicineCase::query())')
        ->toContain('OperationsSupplierScope::coordinationServiceQuery()')
        ->toContain("->where('status', 'ALTA MEDICA')")
        ->toContain("->where('status', 'EN SEGUIMIENTO')")
        ->toContain('whereNotNull(\'afilliation_id\')')
        ->toContain('orWhereNotNull(\'afilliation_corporate_id\')')
        ->toContain('function statisticsQuery')
        ->toContain('countsByServiceStatus')
        ->toContain('countsByBusinessLine')
        ->toContain('countsByServiceType')
        ->toContain("'service_status'")
        ->toContain("'business_line'")
        ->toContain("'service_type'")
        ->toContain("where('case_status', 'ALTA MEDICA')")
        ->toContain('COUNT(DISTINCT telemedicine_case_id)')
        ->toContain('finishedServicesMonthlyCounts')
        ->toContain("where('service_status', 'FINALIZADO')")
        ->toContain('COALESCE(service_on, started_on)')
        ->toContain('medicalDischargeCaseHoverLines')
        ->toContain('GROUP_CONCAT');
});

it('rechaza agrupaciones de métricas sobre columnas no permitidas', function (): void {
    expect(OperationsDashboardMetrics::countsGroupedBy('id', 'x'))->toBe([])
        ->and(OperationsDashboardMetrics::countsGroupedBy('status; drop table', 'x'))->toBe([]);
});

it('arma un resumen de caso para el hover del gráfico de altas médicas', function (): void {
    $lines = OperationsDashboardMetrics::medicalDischargeCaseHoverLines((object) [
        'patient_name' => 'AVILA ISABELA',
        'patient_age' => 30,
        'patient_relationship' => 'TITULAR',
        'patient_document' => '23865467',
        'contractor' => 'INSERVEN, C.A.',
        'plan_holder_name' => 'AVILA ISABELA',
        'business_line' => 'INDIVIDUALES',
        'consultation_reason' => 'TOS',
        'initial_diagnosis' => 'XXX',
        'final_diagnosis' => 'XXX',
        'service_types' => 'LABORATORIOS',
        'services' => 'HEMATOLOGIA COMPLETA (HC)',
        'medical_provider' => 'ARSENIO HENRY',
        'management_provider' => 'CORPORACION VMC, C.A. (ATENMEDI)',
        'city' => 'MARACAIBO',
        'state' => 'ZULIA',
    ]);

    expect($lines)
        ->toContain('Paciente: AVILA ISABELA · 30 años · TITULAR')
        ->toContain('Documento: 23865467')
        ->toContain('Contratante: INSERVEN, C.A.')
        ->not->toContain('Titular: AVILA ISABELA')
        ->toContain('Línea: INDIVIDUALES')
        ->toContain('Motivo: TOS')
        ->toContain('Diagnóstico: XXX')
        ->not->toContain('Dx inicial: XXX')
        ->toContain('Tipos: LABORATORIOS')
        ->toContain('Servicios: HEMATOLOGIA COMPLETA (HC)')
        ->toContain('Médico: ARSENIO HENRY')
        ->toContain('Gestión: CORPORACION VMC, C.A. (ATENMEDI)')
        ->toContain('Ubicación: MARACAIBO, ZULIA');
});

it('resume servicios repetidos y omite campos vacíos en el hover del caso', function (): void {
    $lines = OperationsDashboardMetrics::medicalDischargeCaseHoverLines((object) [
        'patient_name' => 'PACIENTE DEMO',
        'patient_age' => 0,
        'patient_relationship' => null,
        'patient_document' => '  ',
        'contractor' => null,
        'plan_holder_name' => null,
        'business_line' => null,
        'consultation_reason' => null,
        'initial_diagnosis' => 'Gripe',
        'final_diagnosis' => 'Faringitis',
        'service_types' => 'LABORATORIOS||MEDICAMENTOS||LABORATORIOS',
        'services' => 'HC||GLICEMIA||UREA||CREATININA||ACETAMINOFEN||CARDIOLOGO',
        'medical_provider' => null,
        'management_provider' => null,
        'city' => 'CARACAS',
        'state' => 'CARACAS',
    ]);

    expect($lines)
        ->toContain('Paciente: PACIENTE DEMO')
        ->toContain('Dx inicial: Gripe')
        ->toContain('Dx final: Faringitis')
        ->toContain('Tipos: LABORATORIOS · MEDICAMENTOS')
        ->toContain('Servicios: HC · GLICEMIA · UREA · CREATININA · +2')
        ->toContain('Ubicación: CARACAS')
        ->not->toContain('Documento:')
        ->not->toContain('Médico:');
});
