<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\BirthdayNotificationRunReport;
use App\Support\SystemNotificationRecipients;

uses(Tests\TestCase::class);

it('separa copia testigo y resumen de cumpleaños en el centro de notificaciones', function (): void {
    expect(SystemNotificationKey::BirthdayNotificationWitnessCopy->description())
        ->toContain('Copias testigo')
        ->not->toContain('resumen de ejecución de la tarea diaria')
        ->and(SystemNotificationKey::BirthdayNotificationSummary->description())
        ->toContain('resumen de ejecución')
        ->not->toContain('Copias testigo de cada tarjeta');

    expect(SystemNotificationKey::managed())
        ->toContain(SystemNotificationKey::BirthdayNotificationWitnessCopy)
        ->toContain(SystemNotificationKey::BirthdayNotificationSummary);

    $report = file_get_contents(dirname(__DIR__, 2).'/app/Support/BirthdayNotificationRunReport.php');

    expect($report)
        ->toContain('BirthdayNotificationWitnessCopy')
        ->toContain('BirthdayNotificationSummary')
        ->toContain('queueWitnessWhatsAppCopies')
        ->toContain('mailerWithWitnessCopies')
        ->toContain('BirthdayNotificationSummaryMail');
});

it('respeta el interruptor de copia testigo al resolver destinatarios', function (): void {
    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::BirthdayNotificationWitnessCopy);
    $previous = [
        'is_active' => $setting->is_active,
        'notification_emails' => $setting->notification_emails,
        'notification_phones' => $setting->notification_phones,
        'updated_by' => $setting->updated_by,
    ];

    $setting->update([
        'is_active' => true,
        'notification_emails' => ['testigo@integracorp.test'],
        'notification_phones' => ['04141110000'],
        'updated_by' => 'pest',
    ]);

    try {
        expect(BirthdayNotificationRunReport::witnessCopyEmails())
            ->toBe(['testigo@integracorp.test'])
            ->and(BirthdayNotificationRunReport::witnessCopyPhones())
            ->toBe(['04141110000']);

        $setting->update(['is_active' => false]);

        expect(BirthdayNotificationRunReport::witnessCopyEmails())
            ->toBe([])
            ->and(BirthdayNotificationRunReport::witnessCopyPhones())
            ->toBe([])
            ->and(SystemNotificationRecipients::emails(SystemNotificationKey::BirthdayNotificationWitnessCopy))
            ->toBe(['testigo@integracorp.test']);
    } finally {
        $setting->update($previous);
    }
});

it('usa defaults historicos para copia testigo y resumen', function (): void {
    expect(SystemNotificationKey::BirthdayNotificationWitnessCopy->defaultEmails())
        ->toBe(['solrodriguez@tudrencasa.com'])
        ->and(SystemNotificationKey::BirthdayNotificationWitnessCopy->defaultPhones())
        ->toBe(['04143027250'])
        ->and(SystemNotificationKey::BirthdayNotificationSummary->defaultEmails())
        ->toBe([])
        ->and(SystemNotificationKey::BirthdayNotificationSummary->defaultPhones())
        ->toBe(['04127018390', '04143027250']);
});
