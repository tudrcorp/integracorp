<?php

declare(strict_types=1);

use App\Models\OperationServiceStatistic;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientStudy;
use App\Support\Operations\OperationServiceStatisticSync;

it('crea una tabla de hechos con grano por servicio y llave unica de origen', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_11_120000_create_operation_service_statistics_table.php');
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/OperationServiceStatistic.php');

    expect($migration)
        ->toContain("Schema::hasTable('operation_service_statistics')")
        ->toContain("Schema::create('operation_service_statistics'")
        ->toContain("unique(['source_type', 'source_id']")
        ->toContain('ensureIndex')
        ->toContain('oss_case_code_idx')
        ->toContain('case_code')
        ->toContain('coverage')
        ->toContain('case_denied')
        ->toContain('qc_received');

    expect($model)
        ->toContain("protected \$table = 'operation_service_statistics'")
        ->toContain("'service_status'")
        ->toContain('SOURCE_LAB')
        ->toContain('SOURCE_MEDICATION')
        ->toContain('SOURCE_STUDY')
        ->toContain('SOURCE_SPECIALTY')
        ->toContain('SOURCE_AMBULANCE')
        ->toContain('SOURCE_CLINIC_ADMISSION')
        ->toContain('SOURCE_TPA_RETAIL');
});

it('sincroniza por upsert idempotente y no sustituye las pantallas operativas', function (): void {
    $sync = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceStatisticSync.php');
    $command = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/Operations/BackfillOperationServiceStatisticsCommand.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/BackfillOperationServiceStatisticsChunkJob.php');

    expect($sync)
        ->toContain('final class OperationServiceStatisticSync')
        ->toContain('function assembleSnapshot')
        ->toContain('function syncItem')
        ->toContain('function syncFromCase')
        ->toContain('function syncFromCoordination')
        ->toContain('updateOrCreate')
        ->toContain("'source_type'")
        ->toContain("'source_id'")
        ->toContain('forgetStandaloneCoordination')
        ->toContain('markCaseDenied')
        ->toContain("relationLoaded('operationCoordinationService')")
        ->toContain("hasColumn('telemedicine_follow_ups'")
        ->toContain("'service_status'");

    expect($command)
        ->toContain('operations:backfill-service-statistics')
        ->toContain('BackfillOperationServiceStatisticsChunkJob::dispatch')
        ->toContain('--sync');

    expect($job)
        ->toContain("onQueue('system')")
        ->toContain('syncFromCaseId');
});

it('engancha observers en items, coordinacion, OS y caso', function (): void {
    $observer = file_get_contents(dirname(__DIR__, 2).'/app/Observers/OperationServiceStatisticObserver.php');

    expect($observer)
        ->toContain('function saved')
        ->toContain('function deleted')
        ->toContain('function deleting')
        ->toContain('markCaseDenied')
        ->toContain('syncFromModel');

    foreach ([
        'TelemedicinePatientLab',
        'TelemedicinePatientMedications',
        'TelemedicinePatientStudy',
        'TelemedicinePatientSpecialty',
        'OperationCoordinationService',
        'OperationServiceOrder',
        'ObservationCase',
        'TelemedicineFollowUp',
        'TelemedicineConsultationPatient',
        'TelemedicineCase',
    ] as $model) {
        $path = dirname(__DIR__, 2).'/app/Models/'.$model.'.php';
        expect(file_get_contents($path))
            ->toContain('OperationServiceStatisticObserver');
    }
});

it('arma una fila por cada servicio concreto con la misma referencia de caso', function (): void {
    $shared = [
        'telemedicine_case_id' => 10,
        'case_code' => 'TDG-123',
        'case_status' => 'EN SEGUIMIENTO',
        'service_status' => 'PENDIENTE',
        'case_created_by' => 'Analista',
        'patient_name' => 'Juan Perez',
        'patient_document' => 'V123',
        'contractor' => 'GRUPO PQQ',
        'initial_diagnosis' => 'GRIPE',
        'final_diagnosis' => 'GRIPE COMUN',
        'started_on' => '2026-09-11',
        'started_at_time' => '08:15:00',
        'farmadoc_detail' => null,
        'qc_description' => 'Recibido',
        'discount_percent' => 0,
        'discount_amount' => 0,
    ];

    $sources = [
        ['source_type' => OperationServiceStatistic::SOURCE_LAB, 'source_id' => 1, 'specific_service' => 'Hemograma', 'coverage' => 'Cubierto', 'service' => 'LABORATORIOS'],
        ['source_type' => OperationServiceStatistic::SOURCE_LAB, 'source_id' => 2, 'specific_service' => 'Glicemia', 'coverage' => 'No cubierto', 'service' => 'LABORATORIOS'],
        ['source_type' => OperationServiceStatistic::SOURCE_LAB, 'source_id' => 3, 'specific_service' => 'Urea', 'coverage' => 'Cubierto', 'service' => 'LABORATORIOS'],
        ['source_type' => OperationServiceStatistic::SOURCE_LAB, 'source_id' => 4, 'specific_service' => 'Creatinina', 'coverage' => 'Cubierto', 'service' => 'LABORATORIOS'],
        ['source_type' => OperationServiceStatistic::SOURCE_LAB, 'source_id' => 5, 'specific_service' => 'TGO', 'coverage' => 'No cubierto', 'service' => 'LABORATORIOS'],
        ['source_type' => OperationServiceStatistic::SOURCE_MEDICATION, 'source_id' => 11, 'specific_service' => 'Acetaminofen', 'coverage' => 'Cubierto', 'service' => 'MEDICAMENTOS'],
        ['source_type' => OperationServiceStatistic::SOURCE_MEDICATION, 'source_id' => 12, 'specific_service' => 'Ibuprofeno', 'coverage' => 'No cubierto', 'service' => 'MEDICAMENTOS'],
        ['source_type' => OperationServiceStatistic::SOURCE_MEDICATION, 'source_id' => 13, 'specific_service' => 'Loratadina', 'coverage' => 'Cubierto', 'service' => 'MEDICAMENTOS'],
        ['source_type' => OperationServiceStatistic::SOURCE_STUDY, 'source_id' => 21, 'specific_service' => 'Rx Torax', 'coverage' => 'Cubierto', 'service' => 'IMAGENOLOGIA'],
        ['source_type' => OperationServiceStatistic::SOURCE_STUDY, 'source_id' => 22, 'specific_service' => 'Eco Abdominal', 'coverage' => 'No cubierto', 'service' => 'IMAGENOLOGIA'],
    ];

    $payloads = array_map(
        static fn (array $source): array => OperationServiceStatisticSync::assembleSnapshot(array_merge($shared, $source)),
        $sources,
    );

    expect($payloads)->toHaveCount(10)
        ->and(collect($payloads)->pluck('case_code')->unique()->values()->all())->toBe(['TDG-123'])
        ->and(collect($payloads)->pluck('source_id')->unique())->toHaveCount(10)
        ->and($payloads[0]['coverage'])->toBe('Cubierto')
        ->and($payloads[1]['coverage'])->toBe('No cubierto')
        ->and($payloads[0]['qc_received'])->toBe('SI')
        ->and($payloads[0]['farmadoc_derived'])->toBe('NO')
        ->and($payloads[0]['case_denied'])->toBe('NO')
        ->and($payloads[0]['service_status'])->toBe('PENDIENTE')
        ->and($payloads[5]['service'])->toBe('MEDICAMENTOS')
        ->and($payloads[5]['specific_service'])->toBe('Acetaminofen');
});

it('resuelve cobertura por item y no duplica al reensamblar el mismo origen', function (): void {
    $labCovered = new TelemedicinePatientLab;
    $labCovered->type = 'CUBIERTO';

    $labUncovered = new TelemedicinePatientLab;
    $labUncovered->type = 'NO CUBIERTO';

    $medication = new TelemedicinePatientMedications;
    $medication->is_covered = true;

    $study = new TelemedicinePatientStudy;
    $study->type = 'CUBIERTO';

    expect(OperationServiceStatisticSync::coverageLabel($labCovered, OperationServiceStatistic::SOURCE_LAB))->toBe('Cubierto')
        ->and(OperationServiceStatisticSync::coverageLabel($labUncovered, OperationServiceStatistic::SOURCE_LAB))->toBe('No cubierto')
        ->and(OperationServiceStatisticSync::coverageLabel($medication, OperationServiceStatistic::SOURCE_MEDICATION))->toBe('Cubierto')
        ->and(OperationServiceStatisticSync::coverageLabel($study, OperationServiceStatistic::SOURCE_STUDY))->toBe('Cubierto')
        ->and(OperationServiceStatisticSync::sourceTypeForItem($labCovered))->toBe(OperationServiceStatistic::SOURCE_LAB)
        ->and(OperationServiceStatisticSync::standaloneSourceType('TRASLADO EN AMBULANCIA'))->toBe(OperationServiceStatistic::SOURCE_AMBULANCE)
        ->and(OperationServiceStatisticSync::standaloneSourceType('INGRESO A CLINICA'))->toBe(OperationServiceStatistic::SOURCE_CLINIC_ADMISSION);

    $first = OperationServiceStatisticSync::assembleSnapshot([
        'source_type' => OperationServiceStatistic::SOURCE_LAB,
        'source_id' => 99,
        'case_code' => 'TDG-99',
        'specific_service' => 'Hemograma',
        'coverage' => 'Cubierto',
    ]);
    $second = OperationServiceStatisticSync::assembleSnapshot([
        'source_type' => OperationServiceStatistic::SOURCE_LAB,
        'source_id' => 99,
        'case_code' => 'TDG-99',
        'specific_service' => 'Hemograma',
        'coverage' => 'Cubierto',
        'quoted_amount' => 120,
    ]);

    expect($first['source_type'])->toBe($second['source_type'])
        ->and($first['source_id'])->toBe($second['source_id'])
        ->and($second['quoted_amount'])->toBe('120.00');
});

it('marca SI en negacion del caso y QC cuando corresponde', function (): void {
    $denied = OperationServiceStatisticSync::assembleSnapshot([
        'source_type' => OperationServiceStatistic::SOURCE_AMBULANCE,
        'source_id' => 7,
        'case_status' => 'REVERSADO',
        'qc_description' => '...',
        'farmadoc_detail' => 'FARMADOC-01',
        'discount_percent' => 15,
    ]);

    expect($denied['case_denied'])->toBe('SI')
        ->and($denied['qc_received'])->toBe('NO')
        ->and($denied['farmadoc_derived'])->toBe('SI')
        ->and($denied['discount_negotiation'])->toBe('SI');
});
