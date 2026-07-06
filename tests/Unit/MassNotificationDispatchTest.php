<?php

declare(strict_types=1);

use App\Enums\MassNotificationDeliveryStatus;

it('incluye migración de métricas por destinatario en data_notifications', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_06_16_133818_add_delivery_metrics_to_data_notifications_table.php';
    $src = @file_get_contents($path);

    expect($src)->not->toBeFalse()
        ->and($src)->toContain('email_status')
        ->and($src)->toContain('whatsapp_status');
});

it('programa el job de notificaciones masivas por fecha', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($src)->toContain('DispatchScheduledMassNotifications')
        ->and($src)->toContain('everyMinute()');
});

it('MassNotificationDispatchService busca campañas programadas pendientes', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Support/MassNotificationDispatchService.php');

    expect($src)->toContain('dueScheduledNotifications')
        ->and($src)->toContain("whereNotNull('date_programed')")
        ->and($src)->toContain("where('is_sent', false)")
        ->and($src)->toContain('applyApprovedScope');
});

it('los jobs de envío masivo reciben el id del destinatario', function (): void {
    $emailJob = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendNotificationMasiveEmail.php');
    $whatsappJob = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendNotificationMasive.php');

    expect($emailJob)->toContain('dataNotificationId')
        ->and($whatsappJob)->toContain('dataNotificationId')
        ->and($emailJob)->toContain('markEmailSent')
        ->and($whatsappJob)->toContain('markWhatsappSent');
});

it('MassNotificationDeliveryStatus expone etiquetas en español', function (): void {
    expect(MassNotificationDeliveryStatus::Sent->label())->toBe('Enviado')
        ->and(MassNotificationDeliveryStatus::Failed->label())->toBe('Fallido')
        ->and(MassNotificationDeliveryStatus::Pending->label())->toBe('Pendiente')
        ->and(MassNotificationDeliveryStatus::Skipped->label())->toBe('Omitido');
});

it('DataNotificationsRelationManager muestra columnas de métricas', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/MassNotifications/RelationManagers/DataNotificationsRelationManager.php');

    expect($src)->toContain("TextColumn::make('email_status')")
        ->and($src)->toContain("TextColumn::make('whatsapp_status')");
});

it('MassNotificationInfolist muestra resumen de métricas', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/MassNotifications/Schemas/MassNotificationInfolist.php');

    expect($src)->toContain('Métricas de envío (correo)')
        ->and($src)->toContain('deliveryStats()')
        ->and($src)->toContain('test_email_success_count');
});

it('sendNotificationEmailSingle registra métricas de prueba', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($src)->toContain('recordTestEmail');
});
