<?php

declare(strict_types=1);

use App\Support\ScheduledTaskRunReport;

uses(Tests\TestCase::class);

it('inicializa y acumula métricas y fallas en el reporte de tareas programadas', function (): void {
    ScheduledTaskRunReport::begin('Tarea de prueba');

    ScheduledTaskRunReport::addMetric('Registros procesados', 10);
    ScheduledTaskRunReport::incrementMetric('Registros actualizados', 3);
    ScheduledTaskRunReport::recordFailure('Email nulo o vacío');
    ScheduledTaskRunReport::recordFailure('Email nulo o vacío');

    $snapshot = ScheduledTaskRunReport::snapshotForTesting();

    expect($snapshot['taskTitle'])->toBe('Tarea de prueba')
        ->and($snapshot['metrics']['Registros procesados'])->toBe(10)
        ->and($snapshot['metrics']['Registros actualizados'])->toBe(3)
        ->and($snapshot['failures']['Email nulo o vacío'])->toBe(2);
});

it('incluye error crítico en el resumen whatsapp de tareas programadas', function (): void {
    ScheduledTaskRunReport::begin('Cobranza anual — días restantes');
    ScheduledTaskRunReport::addMetric('Registros procesados', 0);
    ScheduledTaskRunReport::recordCriticalFailure(new RuntimeException('Fallo de conexión'));

    $summary = ScheduledTaskRunReport::summaryPreviewForTesting();

    expect($summary)
        ->toContain('Cobranza anual — días restantes')
        ->toContain('error crítico')
        ->toContain('Fallo de conexión')
        ->toContain('Registros procesados: 0');
});

it('los jobs programados usan el reporte de ejecución por whatsapp', function (): void {
    $jobsPath = dirname(__DIR__, 2).'/app/Jobs';

    foreach ([
        'SendCollaboratorAnniversaryNotification.php',
        'UpdateAnnualCollectionRemainingDays.php',
        'PrepareAffiliationRenovations.php',
        'UpdateAffiliateIlsRemainingDays.php',
        'AnulateAgentQuotes.php',
    ] as $jobFile) {
        expect(file_get_contents($jobsPath.'/'.$jobFile))
            ->toContain('ReportsScheduledExecution')
            ->toContain('runWithScheduledReport');
    }

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/ScheduledTaskRunReport.php'))
        ->toContain('04127018390')
        ->toContain('finishAndNotify');
});
