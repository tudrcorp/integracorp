<?php

declare(strict_types=1);

use App\Support\BirthdayNotificationRunReport;
use Tests\TestCase;

uses(TestCase::class);

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

it('registra validaciones por lote y las refleja en el resumen whatsapp', function (): void {
    BirthdayNotificationRunReport::begin();

    BirthdayNotificationRunReport::registerRunConfiguration([
        [
            'title' => 'Tarjeta agentes',
            'data_type' => 'agents',
            'channels' => ['whatsapp', 'email'],
        ],
    ]);

    BirthdayNotificationRunReport::recordValidationBatch('agentes', 414, 'whatsapp');
    BirthdayNotificationRunReport::recordValidationBatch('agentes', 414, 'email');

    BirthdayNotificationRunReport::recordFailure('whatsapp', 'agentes', 'Fecha de cumpleaños es nula o vacia');
    BirthdayNotificationRunReport::recordFailure('email', 'agentes', 'Fecha de cumpleaños es nula o vacia');
    BirthdayNotificationRunReport::recordFailure('whatsapp', 'agentes', 'Formato de fecha de cumpleaños inválido');
    BirthdayNotificationRunReport::recordFailure('email', 'agentes', 'Formato de fecha de cumpleaños inválido');

    $summary = BirthdayNotificationRunReport::summaryMessageForTesting();

    expect($summary)
        ->toContain('Qué hace esta tarea')
        ->toContain('Cómo leer este reporte')
        ->toContain('Tarjetas aprobadas procesadas: 1')
        ->toContain('Registros en base de datos: 414')
        ->toContain('Pasadas de validación: 2')
        ->toContain('Validaciones realizadas: 828')
        ->toContain('Registros únicos estimados con falla: ~2')
        ->toContain('Fecha de cumpleaños nula: 2 (~1 registros únicos)')
        ->toContain('Fecha de cumpleaños inválida: 2 (~1 registros únicos)')
        ->toContain('Las fallas totales no equivalen a personas distintas');
});

it('expone resumen whatsapp al finalizar el job de cumpleaños', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/BirthdayNotificationRunReport.php'))
        ->toContain('ScheduledNotificationPhones::all()')
        ->toContain('04143027250')
        ->toContain('finishAndNotify')
        ->toContain('WhatsAppBrandImage::RELATIVE_PATH')
        ->toContain("'image'")
        ->toContain('Email nulo o vacío')
        ->toContain('Email mal escrito o inválido')
        ->toContain('registerRunConfiguration')
        ->toContain('recordValidationBatch');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationMasiveService.php'))
        ->toContain('BirthdayNotificationRunReport::begin()')
        ->toContain('BirthdayNotificationRunReport::finishAndNotify()')
        ->toContain('BirthdayNotificationRunReport::registerRunConfiguration')
        ->toContain('BirthdayNotificationRunReport::recordValidationBatch');
});
