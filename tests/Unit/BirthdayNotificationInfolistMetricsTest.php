<?php

declare(strict_types=1);

use App\Filament\Marketing\Resources\BirthdayNotifications\BirthdayNotificationResource;
use App\Filament\Marketing\Resources\BirthdayNotifications\RelationManagers\BirthdayNotificationDeliveriesRelationManager;
use App\Models\BirthdayNotification;

it('BirthdayNotificationInfolist muestra resumen de métricas', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/Schemas/BirthdayNotificationInfolist.php');

    expect($src)->toContain('Métricas de envío (correo)')
        ->and($src)->toContain('Métricas de envío (WhatsApp)')
        ->and($src)->toContain('deliveryStats()')
        ->and($src)->toContain('email_metrics_sent')
        ->and($src)->toContain('whatsapp_metrics_sent');
});

it('registra el relation manager de entregas con filtro de fecha', function (): void {
    $resourceSrc = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/BirthdayNotificationResource.php');
    $relationSrc = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/RelationManagers/BirthdayNotificationDeliveriesRelationManager.php');

    expect($resourceSrc)->toContain('BirthdayNotificationDeliveriesRelationManager::class')
        ->and(BirthdayNotificationResource::getRelations())->toContain(BirthdayNotificationDeliveriesRelationManager::class)
        ->and($relationSrc)->toContain("Filter::make('birthday_date')")
        ->and($relationSrc)->toContain('BirthdayNotificationRecipientCatalog::queryFor')
        ->and($relationSrc)->toContain("DatePicker::make('fecha')");
});

it('el modelo BirthdayNotification expone entregas y métricas', function (): void {
    $modelSrc = file_get_contents(dirname(__DIR__, 2).'/app/Models/BirthdayNotification.php');

    expect($modelSrc)->toContain('function deliveries')
        ->and($modelSrc)->toContain('function deliveryStats')
        ->and(method_exists(BirthdayNotification::class, 'deliveries'))->toBeTrue()
        ->and(method_exists(BirthdayNotification::class, 'deliveryStats'))->toBeTrue();
});

it('el servicio de cumpleaños fija la notificación actual para persistir entregas', function (): void {
    $serviceSrc = file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationMasiveService.php');
    $reportSrc = file_get_contents(dirname(__DIR__, 2).'/app/Support/BirthdayNotificationRunReport.php');
    $jobSrc = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/WhatsAppBirthdayNotification.php');

    expect($serviceSrc)->toContain('BirthdayNotificationRunReport::setCurrentNotification')
        ->and($reportSrc)->toContain('BirthdayNotificationRecipientDelivery::recordWhatsappOutcome')
        ->and($reportSrc)->toContain('BirthdayNotificationRecipientDelivery::recordEmailOutcome')
        ->and($jobSrc)->toContain('deliveryId')
        ->and($jobSrc)->toContain('markWhatsappFailed');
});

it('registra el relation manager de entregas en Livewire', function (): void {
    $providerSrc = file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

    expect($providerSrc)
        ->toContain('BirthdayNotificationDeliveriesRelationManager')
        ->toContain('birthday-notification-deliveries-relation-manager');
});
