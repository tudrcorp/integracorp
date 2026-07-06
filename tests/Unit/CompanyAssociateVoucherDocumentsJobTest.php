<?php

declare(strict_types=1);

use App\Jobs\GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob;
use App\Support\Companies\CompanyAssociateCarnetGenerator;
use App\Support\Companies\CompanyAssociateDocumentsDeliverer;
use App\Support\Companies\CompanyAssociateDocumentsDeliveryMessage;
use App\Support\Companies\CompanyAssociateInclusionQrGenerator;

uses(Tests\TestCase::class);

it('encola generacion y envio asincrono de documentos al registrar asociado', function (): void {
    $notifier = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateRegistrationNotifier.php');

    expect($notifier)
        ->toContain('GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob::dispatch')
        ->toContain('NotifyAnalystsOfCompanyAssociateRegistrationJob::dispatch');
});

it('el job de registro genera qr, carnet y entrega documentos', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob.php');
    $deliverer = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateDocumentsDeliverer.php');

    expect($job)
        ->toContain('GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob')
        ->toContain('CompanyAssociateInclusionQrGenerator::ensurePublished')
        ->toContain('CompanyAssociateCarnetGenerator::generate')
        ->toContain('CompanyAssociateDocumentsDeliverer::deliver')
        ->toContain("config('affiliate-card.documents_queue'");

    expect($deliverer)
        ->toContain('CompanyAssociateDocumentsMail')
        ->toContain('SendNotificacionWhatsAppDocument')
        ->toContain('SendNotificacionWhatsApp')
        ->toContain('CompanyAssociateNotificationSetting::instance');
});

it('ya no encola documentos al guardar voucher ils', function (): void {
    $actions = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Actions/CompanyAssociatesTableActions.php');
    $panel = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/Business/CompanyResponsiblesAssociatesPanel.php');

    expect($actions)
        ->not->toContain('CompanyAssociateVoucherIlsDocumentsNotifier::queueGenerationAfterVoucherSave')
        ->not->toContain('La tarjeta y el QR se están generando en segundo plano');

    expect($panel)
        ->not->toContain('CompanyAssociateVoucherIlsDocumentsNotifier::queueGenerationAfterVoucherSave');
});

it('asegura qr de inclusion publicado cuando falta pero existe el pdf', function (): void {
    expect(method_exists(CompanyAssociateInclusionQrGenerator::class, 'ensurePublished'))->toBeTrue()
        ->and(method_exists(CompanyAssociateInclusionQrGenerator::class, 'isAutomaticGenerationEnabled'))->toBeTrue();
});

it('usa la fecha de vuelo como vigencia de la tarjeta cuando no hay voucher ils', function (): void {
    $associate = new \App\Models\CompanyAssociate([
        'full_name' => 'Juan Pérez',
        'flight_date' => '2026-08-15',
    ]);

    $validity = CompanyAssociateCarnetGenerator::cardValidityDates($associate);

    expect($validity['desde'])->toBe('15/08/2026')
        ->and($validity['hasta'])->toBe('15/08/2026');
});

it('prioriza fechas del voucher ils sobre la fecha de vuelo en la tarjeta', function (): void {
    $associate = new \App\Models\CompanyAssociate([
        'flight_date' => '2026-08-15',
        'date_init' => '01/08/2026',
        'date_end' => '10/08/2026',
    ]);

    $validity = CompanyAssociateCarnetGenerator::cardValidityDates($associate);

    expect($validity['desde'])->toBe('01/08/2026')
        ->and($validity['hasta'])->toBe('10/08/2026');
});

it('arma mensajes de entrega con vigencia y nombre del asociado', function (): void {
    $associate = new \App\Models\CompanyAssociate([
        'full_name' => 'María López',
        'flight_date' => '2026-08-15',
    ]);

    expect(CompanyAssociateDocumentsDeliveryMessage::emailSubject($associate))
        ->toContain('María López')
        ->and(CompanyAssociateDocumentsDeliveryMessage::whatsappIntro($associate))
        ->toContain('15/08/2026')
        ->toContain('María López');
});

it('expone clase de entrega de documentos para registro publico', function (): void {
    expect(class_exists(GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob::class))->toBeTrue()
        ->and(class_exists(CompanyAssociateDocumentsDeliverer::class))->toBeTrue();
});
