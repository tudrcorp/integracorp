<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\IndividualQuotes\IndividualQuoteFollowUpInternalCopies;
use App\Support\SystemNotificationRecipients;

uses(Tests\TestCase::class);

it('resuelve destinatarios por tipo de notificacion desde la configuracion', function (): void {
    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::IndividualQuoteFollowUp);
    $previousEmails = $setting->notification_emails;
    $previousPhones = $setting->notification_phones;
    $previousUpdatedBy = $setting->updated_by;

    $setting->update([
        'notification_emails' => [' control@integracorp.test ', ''],
        'notification_phones' => [' 04140001111 ', null],
        'updated_by' => 'pest',
    ]);

    try {
        expect(SystemNotificationRecipients::emails(SystemNotificationKey::IndividualQuoteFollowUp))
            ->toBe(['control@integracorp.test'])
            ->and(SystemNotificationRecipients::phones(SystemNotificationKey::IndividualQuoteFollowUp))
            ->toBe(['04140001111']);
    } finally {
        $setting->update([
            'notification_emails' => $previousEmails,
            'notification_phones' => $previousPhones,
            'updated_by' => $previousUpdatedBy,
        ]);
    }
});

it('expone defaults de follow-up para copias internas', function (): void {
    expect(SystemNotificationKey::IndividualQuoteFollowUp->defaultPhones())
        ->toBe(['04127018390', '04143027250']);
});

it('el helper de copias internas usa el resolver y el mail dedicado', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/IndividualQuotes/IndividualQuoteFollowUpInternalCopies.php');

    expect($source)
        ->toContain('SystemNotificationKey::IndividualQuoteFollowUp')
        ->toContain('SystemNotificationRecipients::emails')
        ->toContain('SystemNotificationRecipients::phones')
        ->toContain('IndividualQuoteFollowUpInternalCopyMail')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain('Copia interna de seguimiento');

    expect(class_exists(IndividualQuoteFollowUpInternalCopies::class))->toBeTrue();
});

it('el centro de notificaciones gestiona asociados y follow-up por pestañas', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/ManageCompanyAssociateNotifications.php');
    $enum = file_get_contents(dirname(__DIR__, 2).'/app/Enums/SystemNotificationKey.php');

    expect($page)
        ->toContain('Centro de notificaciones')
        ->toContain('activeNotificationKey')
        ->toContain('savedRecipientsMessage');

    expect($enum)
        ->toContain("case CompanyAssociateRegistration = 'company_associate_registration'")
        ->toContain("case IndividualQuoteFollowUp = 'individual_quote_follow_up'")
        ->toContain("case AgentQuoteAnulation = 'agent_quote_anulation'")
        ->toContain("case DatabaseBackup = 'database_backup'")
        ->toContain("case StructureBackup = 'structure_backup'")
        ->toContain("case DailyAuditSummary = 'daily_audit_summary'");

    expect(SystemNotificationKey::AgentQuoteAnulation->defaultEmails())
        ->toBe(['cotizaciones@tudrencasa.com']);

    expect(SystemNotificationKey::DatabaseBackup->defaultPhones())
        ->toBe(['04127018390', '04143027250']);

    expect(SystemNotificationKey::StructureBackup->label())
        ->toBe('Respaldo de Estructura');

    expect(SystemNotificationKey::DailyAuditSummary->label())
        ->toBe('Auditorías completas')
        ->and(SystemNotificationKey::DailyAuditSummary->defaultEmails())
        ->toBe(['solrodriguez@tudrencasa.com']);
});
