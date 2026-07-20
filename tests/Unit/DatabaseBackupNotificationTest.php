<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;

uses(Tests\TestCase::class);

it('el respaldo de base de datos usa destinatarios del centro de notificaciones', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/BackupDatabase.php');
    $report = file_get_contents(dirname(__DIR__, 2).'/app/Support/ScheduledTaskRunReport.php');
    $console = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($job)
        ->toContain('SystemNotificationKey::DatabaseBackup')
        ->toContain('runWithScheduledReport');

    expect($report)
        ->toContain('SystemNotificationRecipients::phones')
        ->toContain('SystemNotificationRecipients::emails')
        ->toContain('ScheduledTaskRunReportMail')
        ->toContain('recipientPhones')
        ->toContain('ScheduledNotificationPhones::all()');

    expect($console)
        ->toContain('BackupDatabase')
        ->toContain('database_backup');
});

it('expone defaults de telefono para el respaldo de base de datos', function (): void {
    expect(SystemNotificationKey::DatabaseBackup->defaultPhones())
        ->toBe(['04127018390', '04143027250'])
        ->and(SystemNotificationKey::DatabaseBackup->label())
        ->toBe('Respaldo de base de datos');
});
