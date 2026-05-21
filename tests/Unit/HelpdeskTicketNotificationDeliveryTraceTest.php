<?php

declare(strict_types=1);

it('registra trazas de correo y whatsapp en flujo de creacion de tickets helpdesk', function (): void {
    $createPaths = [
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/CreateHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Pages/CreateHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Pages/CreateHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Pages/CreateHelpdesk.php',
    ];

    foreach ($createPaths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('dispatchHelpdeskCreateNotifications')
            ->toContain('DispatchesHelpdeskCreateNotifications');
    }

    $traitContents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Concerns/DispatchesHelpdeskCreateNotifications.php');

    expect($traitContents)
        ->toContain('AUDIT_HELPDESK_TICKET_NOTIFICATIONS_PROCESSED')
        ->toContain('mail_sent_count')
        ->toContain('whatsapp_dispatched_count')
        ->toContain('team_whatsapp_report');
});

it('mantiene trazabilidad por destinatario en servicios de notificacion helpdesk', function (): void {
    $mailServicePath = dirname(__DIR__, 2).'/app/Services/HelpdeskTicketAssigneeMailService.php';
    $whatsAppServicePath = dirname(__DIR__, 2).'/app/Services/HelpdeskTicketAssigneeWhatsAppService.php';
    $jobPath = dirname(__DIR__, 2).'/app/Jobs/SendNotificacionWhatsApp.php';

    $mailServiceContents = file_get_contents($mailServicePath);
    $whatsAppServiceContents = file_get_contents($whatsAppServicePath);
    $jobContents = file_get_contents($jobPath);

    expect($mailServiceContents)
        ->toContain('sendToEachAssigneeWithReport')
        ->toContain('AUDIT_HELPDESK_EMAIL_SENT')
        ->toContain('AUDIT_HELPDESK_EMAIL_FAILED')
        ->toContain('AUDIT_HELPDESK_EMAIL_SKIPPED');

    $teamWhatsAppServicePath = dirname(__DIR__, 2).'/app/Services/HelpdeskTeamMembersWhatsAppService.php';
    $teamWhatsAppServiceContents = file_get_contents($teamWhatsAppServicePath);

    expect($whatsAppServiceContents)
        ->toContain('normalizePhoneForWhatsApp')
        ->toContain('dispatchCustomMessageToEachAssigneeWithReport')
        ->toContain('dispatchToTicketCreatorWithReport')
        ->toContain('buildStatusUpdatedBody')
        ->toContain('buildNoteAddedBody')
        ->toContain('buildTicketClosedByCreatorBody')
        ->toContain('AUDIT_HELPDESK_WHATSAPP_DISPATCHED')
        ->toContain('AUDIT_HELPDESK_WHATSAPP_DISPATCH_FAILED')
        ->toContain('AUDIT_HELPDESK_WHATSAPP_SKIPPED');

    expect($teamWhatsAppServiceContents)
        ->toContain('dispatchToEachTeamMemberWithReport')
        ->toContain('buildWhatsAppBodyForTeamTicket')
        ->toContain('Integrantes del equipo');

    expect($jobContents)
        ->toContain('AUDIT_HELPDESK_WHATSAPP_SENT')
        ->toContain('AUDIT_HELPDESK_WHATSAPP_SEND_FAILED')
        ->toContain('AUDIT_HELPDESK_WHATSAPP_SEND_FAILED_FINAL')
        ->toContain('resolveAuditRoute');
});
