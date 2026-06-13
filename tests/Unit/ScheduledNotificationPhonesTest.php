<?php

declare(strict_types=1);

use App\Support\ScheduledNotificationPhones;

uses(Tests\TestCase::class);

it('resuelve los dos telefonos por defecto para tareas programadas', function (): void {
    expect(ScheduledNotificationPhones::all())
        ->toBe(['04127018390', '04143027250']);
});

it('envia reportes programados a todos los telefonos configurados', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/ScheduledTaskRunReport.php'))
        ->toContain('ScheduledNotificationPhones::all()');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/BirthdayNotificationRunReport.php'))
        ->toContain('ScheduledNotificationPhones::all()')
        ->toContain('04143027250');
});
