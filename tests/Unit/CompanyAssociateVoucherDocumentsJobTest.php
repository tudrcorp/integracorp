<?php

declare(strict_types=1);

use App\Jobs\GenerateCompanyAssociateDocumentsAfterVoucherJob;
use App\Support\Companies\CompanyAssociateDocumentsGeneratedNotificationMessage;
use App\Support\Companies\CompanyAssociateInclusionQrGenerator;

it('encola generacion asincrona de documentos al guardar voucher ils', function (): void {
    $actions = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Actions/CompanyAssociatesTableActions.php');

    expect($actions)
        ->toContain('CompanyAssociateVoucherIlsDocumentsNotifier::queueGenerationAfterVoucherSave')
        ->toContain('La tarjeta y el QR se están generando en segundo plano');
});

it('el job genera qr, carnet y notifica al analista sin encolar la notificacion', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GenerateCompanyAssociateDocumentsAfterVoucherJob.php');
    $notifier = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateDocumentsAnalystNotifier.php');

    expect($job)
        ->toContain('GenerateCompanyAssociateDocumentsAfterVoucherJob')
        ->toContain('CompanyAssociateInclusionQrGenerator::ensurePublished')
        ->toContain('CompanyAssociateCarnetGenerator::generate')
        ->toContain('CompanyAssociateDocumentsAnalystNotifier::notifySuccess')
        ->toContain('CompanyAssociateDocumentsAnalystNotifier::notifyFailure');

    expect($notifier)
        ->toContain('notifyNow')
        ->toContain('DatabaseNotificationsSent::dispatch')
        ->toContain('toBroadcast');
});

it('expone notificador asincrono para documentos despues del voucher', function (): void {
    expect(class_exists(GenerateCompanyAssociateDocumentsAfterVoucherJob::class))->toBeTrue();

    $notifier = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateVoucherIlsDocumentsNotifier.php');

    expect($notifier)
        ->toContain(GenerateCompanyAssociateDocumentsAfterVoucherJob::class)
        ->toContain('queueGenerationAfterVoucherSave');
});

it('asegura qr de inclusion publicado cuando falta pero existe el pdf', function (): void {
    expect(method_exists(CompanyAssociateInclusionQrGenerator::class, 'ensurePublished'))->toBeTrue();
});

it('arma mensaje de exito con informacion principal del asociado', function (): void {
    $associate = new \App\Models\CompanyAssociate([
        'full_name' => 'María López',
        'identity_card' => 'V-123',
        'vaucher_ils' => 'ILS-99',
    ]);
    $associate->setRelation('company', new \App\Models\Company(['name' => 'Empresa Demo']));

    $body = CompanyAssociateDocumentsGeneratedNotificationMessage::toastBody($associate);

    expect(CompanyAssociateDocumentsGeneratedNotificationMessage::title())->toBe('Carnet generado');

    expect($body)
        ->toContain('María López')
        ->toContain('V-123')
        ->toContain('ILS-99')
        ->toContain('Empresa Demo')
        ->toContain('Abrir carnet');
});

it('arma mensaje de fallo pidiendo contacto con sistemas', function (): void {
    $associate = new \App\Models\CompanyAssociate([
        'full_name' => 'María López',
        'identity_card' => 'V-123',
    ]);

    $body = CompanyAssociateDocumentsGeneratedNotificationMessage::failureBody($associate, 'Error de prueba');

    expect(CompanyAssociateDocumentsGeneratedNotificationMessage::failureTitle())
        ->toBe('No se generaron los documentos del asociado');

    expect($body)
        ->toContain('María López')
        ->toContain('V-123')
        ->toContain('equipo de sistemas')
        ->toContain('Error de prueba');
});

it('registra polling mas frecuente de notificaciones en business', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/BusinessPanelProvider.php');

    expect($provider)
        ->toContain("->databaseNotificationsPolling('10s')")
        ->toContain('isLazy: false')
        ->toContain('filament.business.partials.database-notifications-alert');
});

it('anima la campana cuando llegan notificaciones en business', function (): void {
    $partial = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/partials/database-notifications-alert.blade.php');
    $notifier = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateDocumentsAnalystNotifier.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($partial)
        ->toContain('fi-db-notifications-alert')
        ->toContain('fi-db-bell-ring')
        ->toContain('pollBellAlertSignal')
        ->toContain('business.notifications.bell-alert');

    expect($notifier)->toContain('CompanyAssociateDocumentsBellAlert::markPending');
    expect($routes)->toContain('business.notifications.bell-alert');
});
