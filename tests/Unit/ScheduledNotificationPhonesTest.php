<?php

declare(strict_types=1);

use App\Support\ScheduledNotificationPhones;

uses(Tests\TestCase::class);

it('resuelve los dos telefonos por defecto para tareas programadas', function (): void {
    expect(ScheduledNotificationPhones::all())
        ->toBe(['04127018390', '04143027250']);
});

it('envia reportes programados a todos los telefonos configurados', function (): void {
    /**
     * Desde la entrega B1 de Telegram, las tareas sin aviso propio usan la entrada
     * «Resúmenes de tareas programadas» del Centro de notificaciones, que arranca
     * con estos mismos teléfonos y ahora se puede editar sin tocar el servidor.
     */
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/ScheduledTaskRunReport.php'))
        ->toContain('SystemNotificationKey::ScheduledTaskSummary');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Enums/SystemNotificationKey.php'))
        ->toContain('self::ScheduledTaskSummary => ScheduledNotificationPhones::all()');
});
