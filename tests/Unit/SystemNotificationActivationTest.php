<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\SystemNotificationRecipients;

uses(Tests\TestCase::class);

it('expone el interruptor de activacion en el centro de notificaciones', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/Schemas/SystemNotificationRecipientSettingsForm.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/ManageCompanyAssociateNotifications.php');
    $console = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/NotifyAnalystsOfCompanyAssociateRegistrationJob.php');

    expect($form)
        ->toContain("Toggle::make('is_active')")
        ->toContain('Tarea activa');

    expect($page)
        ->toContain("'is_active' => \$settings->isActive()")
        ->toContain("'is_active' => \$isActive");

    expect($console)
        ->toContain('SystemNotificationRecipients::isActive(SystemNotificationKey::IndividualQuoteFollowUp)')
        ->toContain('SystemNotificationRecipients::isActive(SystemNotificationKey::AgentQuoteAnulation)')
        ->toContain('SystemNotificationRecipients::isActive(SystemNotificationKey::DatabaseBackup)')
        ->toContain('SystemNotificationRecipients::isActive(SystemNotificationKey::StructureBackup)')
        ->toContain('SystemNotificationRecipients::isActive(SystemNotificationKey::DailyAuditSummary)')
        ->toContain('->when($structureBackupIsActive)')
        ->toContain('->when($dailyAuditSummaryIsActive)');

    expect($job)
        ->toContain('notification_inactive')
        ->toContain('SystemNotificationRecipients::isActive(SystemNotificationKey::CompanyAssociateRegistration)');
});

it('permite activar e inactivar una notificacion sin perder destinatarios', function (): void {
    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::DailyAuditSummary);
    $previousActive = $setting->is_active;
    $previousEmails = $setting->notification_emails;
    $previousPhones = $setting->notification_phones;
    $previousUpdatedBy = $setting->updated_by;

    $setting->update([
        'is_active' => false,
        'notification_emails' => ['audit@example.com'],
        'notification_phones' => ['04141112222'],
        'updated_by' => 'pest',
    ]);

    try {
        expect(SystemNotificationRecipients::isActive(SystemNotificationKey::DailyAuditSummary))->toBeFalse()
            ->and(SystemNotificationRecipients::emails(SystemNotificationKey::DailyAuditSummary))->toBe(['audit@example.com'])
            ->and(SystemNotificationRecipients::phones(SystemNotificationKey::DailyAuditSummary))->toBe(['04141112222']);

        $setting->update(['is_active' => true]);

        expect(SystemNotificationRecipients::isActive(SystemNotificationKey::DailyAuditSummary))->toBeTrue();
    } finally {
        $setting->update([
            'is_active' => $previousActive,
            'notification_emails' => $previousEmails,
            'notification_phones' => $previousPhones,
            'updated_by' => $previousUpdatedBy,
        ]);
    }
});
