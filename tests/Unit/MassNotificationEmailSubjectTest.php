<?php

declare(strict_types=1);

use App\Mail\NotificationMasiveMail;
use App\Mail\SendNotificationMailSingle;

it('incluye migración que añade email_subject a mass_notifications', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_06_16_130005_add_email_subject_to_mass_notifications_table.php';
    $src = @file_get_contents($path);

    expect($src)->not->toBeFalse()
        ->and($src)->toContain('email_subject')
        ->and($src)->toContain('mass_notifications');
});

it('MassNotificationForm expone asunto de correo cuando el canal incluye email', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/MassNotifications/Schemas/MassNotificationForm.php';
    $src = file_get_contents($path);

    expect($src)->toContain("TextInput::make('email_subject')")
        ->and($src)->toContain("in_array('email', (array) \$get('channels'), true)");
});

it('NotificationMasiveMail usa el asunto personalizado del registro', function (): void {
    $mailable = new NotificationMasiveMail([
        'email_subject' => 'Promoción especial de verano',
        'content' => 'Contenido de prueba',
    ]);

    expect($mailable->envelope()->subject)->toBe('Promoción especial de verano');
});

it('NotificationMasiveMail usa asunto por defecto cuando no hay email_subject', function (): void {
    $mailable = new NotificationMasiveMail([
        'content' => 'Contenido de prueba',
    ]);

    expect($mailable->envelope()->subject)->toBe('Notificación');
});

it('SendNotificationMailSingle usa el asunto personalizado del modelo', function (): void {
    $record = new class
    {
        public string $email_subject = 'hola mundo';
    };

    $mailable = new SendNotificationMailSingle($record);

    expect($mailable->envelope()->subject)->toBe('hola mundo');
});
