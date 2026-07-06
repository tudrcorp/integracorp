<?php

declare(strict_types=1);

use App\Jobs\NotifyAnalystsOfCompanyAssociateRegistrationJob;
use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Models\CompanyAssociateNotificationSetting;
use App\Models\CompanyResponsible;
use App\Support\Companies\CompanyAssociateRegistrationNotificationMessage;
use App\Support\Companies\CompanyAssociateRegistrationNotifier;
use App\Support\Companies\CompanyAssociatesTableContext;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;

uses(Tests\TestCase::class);

it('el registro publico encola notificaciones para analistas', function (): void {
    $livewire = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/CompanyAssociateRegistration.php');

    expect($livewire)
        ->toContain('CompanyAssociateRegistrationNotifier::notify')
        ->toContain('associate_id');
});

it('expone pagina de configuracion de notificaciones en el panel business', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/ManageCompanyAssociateNotifications.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/Schemas/CompanyAssociateNotificationSettingsForm.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/manage-company-associate-notifications.blade.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_01_102437_create_company_associate_notification_settings_table.php');

    expect($page)
        ->toContain('CONFIGURACIÓN')
        ->toContain('Notificaciones de asociados')
        ->toContain('CompanyAssociateNotificationSettingsForm::configure')
        ->toContain('configuredEmailCount');

    expect($form)
        ->toContain('notification_emails')
        ->toContain('notification_phones')
        ->toContain('collapsible')
        ->toContain('itemLabel');

    expect($view)
        ->toContain('$this->content')
        ->toContain('can-settings-page')
        ->toContain('voucher ILS')
        ->toContain('can-stats');

    expect($migration)
        ->toContain('notification_emails')
        ->toContain('notification_phones');
});

it('el job de notificaciones usa correo whatsapp y recordatorio de voucher ils', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/NotifyAnalystsOfCompanyAssociateRegistrationJob.php');
    $mail = file_get_contents(dirname(__DIR__, 2).'/app/Mail/CompanyAssociateRegisteredAnalystMail.php');
    $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/company-associate-registered-analyst.blade.php');
    $message = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateRegistrationNotificationMessage.php');

    expect($job)
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain('CompanyAssociateRegisteredAnalystMail')
        ->toContain('CompanyAssociateNotificationSetting::instance');

    expect($mail)->toContain('company-associate-registered-analyst');

    expect($template)
        ->toContain('$message->embed($logoPath)')
        ->toContain('logoNewPdf.png')
        ->toContain('voucher ILS')
        ->toContain('Responsable');

    expect($message)->toContain('voucher ILS')
        ->toContain('CompanyAssociatesTableContext::associateViewUrl');
});

it('construye mensaje detallado con empresa responsable y alerta ils', function (): void {
    Filament::setCurrentPanel('business');

    $company = new Company([
        'name' => 'Empresa Demo',
        'rif' => 'J-12345678-9',
    ]);

    $responsible = new CompanyResponsible([
        'full_name' => 'Ana Responsable',
        'identity_card' => 'V12345678',
    ]);

    $associate = new CompanyAssociate([
        'full_name' => 'Juan Asociado',
        'identity_card' => 'V87654321',
        'age' => 32,
        'sex' => 'MASCULINO',
        'email' => 'juan@example.com',
        'phone' => '04141234567',
        'contact_full_name' => 'María Contacto',
        'contact_phone' => '04149876543',
        'contact_email' => 'maria@example.com',
        'birth_date' => Carbon::parse('1993-05-10'),
        'registered_at' => Carbon::parse('2026-07-01 10:30:00'),
    ]);
    $associate->id = 1;

    $associate->setRelation('company', $company);
    $associate->setRelation('responsible', $responsible);

    $whatsapp = CompanyAssociateRegistrationNotificationMessage::whatsappBody($associate);

    expect($whatsapp)
        ->toContain('Empresa Demo')
        ->toContain('Ana Responsable')
        ->toContain('Juan Asociado')
        ->toContain('voucher ILS');

    expect(CompanyAssociateRegistrationNotificationMessage::emailLogoPath())->toEndWith('logoNewPdf.png');

    expect(CompanyAssociatesTableContext::associateViewUrl(1))->toContain('/business/nuevos-negocios/company-associates/1');
});

it('el notificador despacha el job correspondiente', function (): void {
    Bus::fake();

    CompanyAssociateRegistrationNotifier::notify(99);

    Bus::assertDispatched(NotifyAnalystsOfCompanyAssociateRegistrationJob::class, function (NotifyAnalystsOfCompanyAssociateRegistrationJob $job): bool {
        return $job->associateId === 99;
    });
});

it('el modelo de configuracion expone listas normalizadas de destinatarios', function (): void {
    $setting = new CompanyAssociateNotificationSetting([
        'notification_emails' => [' analista@empresa.com ', ''],
        'notification_phones' => [' 04141234567 ', null],
    ]);

    expect($setting->emails())->toBe(['analista@empresa.com'])
        ->and($setting->phones())->toBe(['04141234567']);
});
