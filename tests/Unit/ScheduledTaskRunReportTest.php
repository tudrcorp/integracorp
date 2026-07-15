<?php

declare(strict_types=1);

use App\Support\ScheduledTaskRunReport;

uses(Tests\TestCase::class);

it('inicializa y acumula métricas y fallas en el reporte de tareas programadas', function (): void {
    ScheduledTaskRunReport::begin(
        'Tarea de prueba',
        'Descripción de la tarea de prueba.',
        ['Nota de lectura de prueba.'],
    );

    ScheduledTaskRunReport::addExecutionDetail('Alcance', 'Registros de prueba');
    ScheduledTaskRunReport::addMetric('Registros procesados', 10);
    ScheduledTaskRunReport::incrementMetric('Registros actualizados', 3);
    ScheduledTaskRunReport::recordFailure('Email nulo o vacío');
    ScheduledTaskRunReport::recordFailure('Email nulo o vacío');
    ScheduledTaskRunReport::setFailureFootnote('Nota aclaratoria sobre fallas.');

    $snapshot = ScheduledTaskRunReport::snapshotForTesting();

    expect($snapshot['taskTitle'])->toBe('Tarea de prueba')
        ->and($snapshot['taskDescription'])->toBe('Descripción de la tarea de prueba.')
        ->and($snapshot['readingNotes'])->toBe(['Nota de lectura de prueba.'])
        ->and($snapshot['executionDetails']['Alcance'])->toBe('Registros de prueba')
        ->and($snapshot['metrics']['Registros procesados'])->toBe(10)
        ->and($snapshot['metrics']['Registros actualizados'])->toBe(3)
        ->and($snapshot['failures']['Email nulo o vacío'])->toBe(2)
        ->and($snapshot['failureFootnote'])->toBe('Nota aclaratoria sobre fallas.');
});

it('incluye secciones explicativas en el resumen whatsapp de tareas programadas', function (): void {
    ScheduledTaskRunReport::begin(
        'Cobranza anual — días restantes',
        'Recalcula los días restantes de cobranza anual.',
        [
            'Registros procesados = filas evaluadas.',
            'Registros actualizados = valor que cambió.',
        ],
    );

    ScheduledTaskRunReport::addExecutionDetail('Fecha de cálculo', '04/06/2026');
    ScheduledTaskRunReport::addMetric('Registros procesados', 120);
    ScheduledTaskRunReport::addMetric('Registros actualizados', 15);
    ScheduledTaskRunReport::recordFailure('Registro con fecha inválida');

    $summary = ScheduledTaskRunReport::summaryPreviewForTesting();

    expect($summary)
        ->toContain('Resumen: Cobranza anual — días restantes')
        ->toContain('Qué hace esta tarea')
        ->toContain('Recalcula los días restantes de cobranza anual.')
        ->toContain('Cómo leer este reporte')
        ->toContain('Detalle de la ejecución')
        ->toContain('Fecha de cálculo: 04/06/2026')
        ->toContain('Resultados')
        ->toContain('Registros procesados: 120')
        ->toContain('Fallas registradas: 1')
        ->toContain('Registro con fecha inválida: 1');
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
        'ExpireOperationServiceOrders.php',
        'BackupDatabase.php',
        'ExportIndividualAffiliations.php',
        'ExportCorporateAffiliations.php',
        'ExportScheduledEntity.php',
    ] as $jobFile) {
        expect(file_get_contents($jobsPath.'/'.$jobFile))
            ->toContain('ReportsScheduledExecution')
            ->toContain('runWithScheduledReport');
    }

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/ScheduledTaskRunReport.php'))
        ->toContain('ScheduledNotificationPhones::all()')
        ->toContain('SystemNotificationRecipients::phones')
        ->toContain('finishAndNotify')
        ->toContain('addExecutionDetail')
        ->toContain('setFailureFootnote')
        ->toContain('setDocumentAttachment');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Jobs/BackupDatabase.php'))
        ->toContain('SystemNotificationKey::DatabaseBackup');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportIndividualAffiliations.php'))
        ->toContain('SystemNotificationKey::StructureBackup');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ExportScheduledEntity.php'))
        ->toContain('SystemNotificationKey::StructureBackup');
});

it('registra adjuntos de documento para tareas con respaldo', function (): void {
    ScheduledTaskRunReport::begin('Respaldo de base de datos');
    ScheduledTaskRunReport::setDocumentAttachment('database-backups/demo.sql', 'demo.sql');

    expect(ScheduledTaskRunReport::snapshotForTesting()['documentAttachment'])
        ->toMatchArray([
            'relative_path' => 'database-backups/demo.sql',
            'filename' => 'demo.sql',
        ]);
});
