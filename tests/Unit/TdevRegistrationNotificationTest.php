<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Jobs\NotifyAnalystsOfTdevRegistrationJob;
use App\Models\TdevAgency;
use App\Models\TdevAgent;
use App\Support\Tdev\TdevAgencyRegistrar;
use App\Support\Tdev\TdevRegistrationNotificationMessage;
use App\Support\Tdev\TdevRegistrationNotifier;
use App\Support\Tdev\TdevWhatsAppBrandImage;
use Carbon\Carbon;

uses(Tests\TestCase::class);

it('expone el tipo de notificacion tdev en el centro de notificaciones', function (): void {
    $enum = file_get_contents(dirname(__DIR__, 2).'/app/Enums/SystemNotificationKey.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_12_090832_add_tdev_registration_notification_recipient_setting.php');

    expect($enum)
        ->toContain("case TdevRegistration = 'tdev_registration'")
        ->toContain("self::TdevRegistration => 'Registros TDEV'");

    expect(SystemNotificationKey::TdevRegistration->label())
        ->toBe('Registros TDEV')
        ->and(SystemNotificationKey::managed())
        ->toContain(SystemNotificationKey::TdevRegistration)
        ->and(SystemNotificationKey::TdevRegistration->pausesScheduledTask())
        ->toBeFalse();

    expect($migration)
        ->toContain('SystemNotificationKey::TdevRegistration');
});

it('el registro publico de agencias y agentes dispara notificaciones tdev', function (): void {
    $registrar = file_get_contents(dirname(__DIR__, 2).'/app/Support/Tdev/TdevAgencyRegistrar.php');

    expect($registrar)
        ->toContain('TdevRegistrationNotifier::notifyAgency')
        ->toContain('TdevRegistrationNotifier::notifyAgent')
        ->toContain('REGISTRATION_SOURCE_PUBLIC');
});

it('el job de notificaciones tdev usa correo whatsapp y logo tdev', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/NotifyAnalystsOfTdevRegistrationJob.php');
    $mail = file_get_contents(dirname(__DIR__, 2).'/app/Mail/TdevRegisteredAnalystMail.php');
    $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/tdev-registered-analyst.blade.php');
    $notifier = file_get_contents(dirname(__DIR__, 2).'/app/Support/Tdev/TdevRegistrationNotifier.php');
    $brand = file_get_contents(dirname(__DIR__, 2).'/app/Support/Tdev/TdevWhatsAppBrandImage.php');

    expect($job)
        ->toContain('SendNotificacionWhatsApp::dispatchSync')
        ->toContain('TdevRegisteredAnalystMail')
        ->toContain('SystemNotificationRecipients::emails')
        ->toContain('SystemNotificationKey::TdevRegistration')
        ->toContain('TdevWhatsAppBrandImage::publicUrl')
        ->toContain('sendWhatsAppWithBrandFallback')
        ->toContain('WhatsAppBrandImage::publicUrl');

    expect($mail)->toContain('tdev-registered-analyst');

    expect($template)
        ->toContain('$message->embed($logoPath)')
        ->toContain('intcorp-tdev.png')
        ->toContain('INTEGRACORP');

    expect($notifier)
        ->toContain('NotifyAnalystsOfTdevRegistrationJob::dispatch')
        ->toContain('afterResponse()')
        ->toContain("onConnection('sync')")
        ->toContain('notifyAgency')
        ->toContain('notifyAgent');

    expect($brand)
        ->toContain('intcorp-tdev.png')
        ->toContain('emailLogoPath')
        ->toContain('hostIsPubliclyReachable');
});

it('construye mensajes detallados para agencia nivel 3 y agentes', function (): void {
    $parent = new TdevAgency([
        'name' => 'AGENCIA PRINCIPAL',
        'email' => 'principal@example.com',
        'phone' => '04141111111',
        'level' => TdevAgency::LEVEL_TWO,
    ]);
    $parent->id = 10;

    $agency = new TdevAgency([
        'name' => 'AGENCIA ASOCIADA',
        'email' => 'asociada@example.com',
        'phone' => '04142222222',
        'phone_additional' => '04143333333',
        'representative_name' => 'REPRESENTANTE DEMO',
        'identification_number' => 'J-123',
        'instagram_username' => 'agencia_demo',
        'address' => 'Calle 1',
        'level' => TdevAgency::LEVEL_THREE,
        'parent_id' => 10,
        'created_at' => Carbon::parse('2026-08-12 09:00:00'),
    ]);
    $agency->id = 20;
    $agency->setRelation('parentAgency', $parent);
    $agency->setRelation('country', null);
    $agency->setRelation('state', null);
    $agency->setRelation('city', null);

    $agencyWhatsapp = TdevRegistrationNotificationMessage::whatsappBodyForAgency($agency);
    $agencyEmail = TdevRegistrationNotificationMessage::emailPayloadForAgency($agency);

    expect($agencyWhatsapp)
        ->toContain('AGENCIA NIVEL 3')
        ->toContain('AGENCIA ASOCIADA')
        ->toContain('AGENCIA PRINCIPAL')
        ->toContain('asociada@example.com')
        ->toContain('@agencia_demo');

    expect($agencyEmail)
        ->toBeArray()
        ->and($agencyEmail['registrationType'])->toBe(TdevRegistrationNotificationMessage::TYPE_AGENCY_LEVEL_THREE)
        ->and($agencyEmail['title'])->toContain('agencia nivel 3');

    $agentN3 = new TdevAgent([
        'full_name' => 'AGENTE NIVEL TRES',
        'position' => 'ASESOR',
        'email' => 'agente3@example.com',
        'phone' => '04144444444',
        'birth_date' => Carbon::parse('1990-01-15'),
        'registered_at' => Carbon::parse('2026-08-12 10:00:00'),
        'registration_source' => TdevAgencyRegistrar::REGISTRATION_SOURCE_PUBLIC,
    ]);
    $agentN3->id = 30;
    $agentN3->setRelation('agency', $agency);

    expect(TdevRegistrationNotificationMessage::resolveAgentType($agentN3))
        ->toBe(TdevRegistrationNotificationMessage::TYPE_AGENT_LEVEL_THREE);

    $agentN3Whatsapp = TdevRegistrationNotificationMessage::whatsappBodyForAgent($agentN3);

    expect($agentN3Whatsapp)
        ->toContain('AGENTE NIVEL TRES')
        ->toContain('Agente de agencia nivel 3')
        ->toContain('AGENCIA ASOCIADA')
        ->toContain('AGENCIA PRINCIPAL');

    $agencyN2 = new TdevAgency([
        'name' => 'AGENCIA FREELANCE HOST',
        'email' => 'host@example.com',
        'level' => TdevAgency::LEVEL_TWO,
    ]);
    $agencyN2->id = 40;

    $agentFreelance = new TdevAgent([
        'full_name' => 'AGENTE FREELANCE',
        'email' => 'freelance@example.com',
        'phone' => '04145555555',
        'registered_at' => Carbon::parse('2026-08-12 11:00:00'),
        'registration_source' => TdevAgencyRegistrar::REGISTRATION_SOURCE_PUBLIC,
    ]);
    $agentFreelance->id = 50;
    $agentFreelance->setRelation('agency', $agencyN2);

    expect(TdevRegistrationNotificationMessage::resolveAgentType($agentFreelance))
        ->toBe(TdevRegistrationNotificationMessage::TYPE_AGENT_FREELANCE_LEVEL_TWO);

    $freelanceWhatsapp = TdevRegistrationNotificationMessage::whatsappBodyForAgent($agentFreelance);

    expect($freelanceWhatsapp)
        ->toContain('AGENTE FREELANCE')
        ->toContain('Agente freelance')
        ->toContain('AGENCIA FREELANCE HOST')
        ->not->toContain('Agencia principal (nivel 2)');

    expect(TdevWhatsAppBrandImage::publicUrl())
        ->not->toContain('.test')
        ->not->toContain('localhost');

    expect(class_exists(TdevRegistrationNotifier::class))->toBeTrue();
    expect(class_exists(NotifyAnalystsOfTdevRegistrationJob::class))->toBeTrue();
});
