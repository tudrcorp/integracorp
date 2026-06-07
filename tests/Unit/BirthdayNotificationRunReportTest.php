<?php

declare(strict_types=1);

use App\Support\BirthdayNotificationRunReport;

it('inicializa los seis grupos al comenzar el reporte de cumpleaños', function (): void {
    BirthdayNotificationRunReport::begin();

    $stats = BirthdayNotificationRunReport::statsForTesting();

    expect(array_keys($stats))->toBe([
        'agentes',
        'agencias',
        'afiliaciones',
        'afiliaciones_corporativas',
        'colaboradores',
        'proveedores',
    ]);
});

it('agrupa envios y fallas por grupo en el reporte de cumpleaños', function (): void {
    BirthdayNotificationRunReport::begin();
    BirthdayNotificationRunReport::setCurrentGroup('agentes');

    BirthdayNotificationRunReport::recordFailure('email', 'agentes', 'Email es nulo o vacio');
    BirthdayNotificationRunReport::recordFailure('email', 'agentes', 'Email mal escrito o inválido');
    BirthdayNotificationRunReport::recordFailure('whatsapp', 'agentes', 'Numero de telefono es nulo o vacio');

    BirthdayNotificationRunReport::setCurrentGroup('proveedores');
    BirthdayNotificationRunReport::recordFailure('email', 'proveedores', 'Formato de fecha de cumpleaños inválido');

    $stats = BirthdayNotificationRunReport::statsForTesting();

    expect($stats['agentes']['failures'])
        ->toMatchArray([
            'email_nulo' => 1,
            'email_invalido' => 1,
            'telefono_nulo' => 1,
        ])
        ->and($stats['proveedores']['failures']['fecha_invalida'])->toBe(1);
});

it('expone resumen whatsapp al finalizar el job de cumpleaños', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/BirthdayNotificationRunReport.php'))
        ->toContain('04127018390')
        ->toContain('finishAndNotify')
        ->toContain('images-whatsapp/integracorp.png')
        ->toContain("'image'")
        ->toContain('Email nulo o vacío')
        ->toContain('Email mal escrito o inválido');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationMasiveService.php'))
        ->toContain('BirthdayNotificationRunReport::begin()')
        ->toContain('BirthdayNotificationRunReport::finishAndNotify()')
        ->toContain('use App\Models\Agent;');
});
