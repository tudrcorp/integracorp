<?php

declare(strict_types=1);

it('alinea el envío de enlace de registro de agentes con el patrón master', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/General/Resources/Agents/Pages/ListAgents.php';
    $contents = file_get_contents($listPath);

    expect($contents)
        ->toContain('Enviar enlace de registro')
        ->toContain('heroicon-m-paper-airplane')
        ->toContain('modalHeading(\'Enviar enlace de registro de agentes\')')
        ->toContain('modalWidth(Width::TwoExtraLarge)')
        ->toContain('Section::make(\'Agencia en tu sesión\')')
        ->toContain('Section::make(\'Destinatarios\')')
        ->toContain('session_agency_preview')
        ->toContain('->label(\'WhatsApp\')')
        ->not->toContain('requiresConfirmation()')
        ->not->toContain('LogController::log');
});

it('registra auditoría en envío de enlace de registro de agentes en general', function (): void {
    $listPath = dirname(__DIR__, 2).'/app/Filament/General/Resources/Agents/Pages/ListAgents.php';
    $contents = file_get_contents($listPath);

    expect($contents)
        ->toContain('AUDIT_GENERAL_AGENT_REGISTER_LINK_EMAIL_SENT')
        ->and($contents)->toContain('AUDIT_GENERAL_AGENT_REGISTER_LINK_EMAIL_FAILED')
        ->and($contents)->toContain('AUDIT_GENERAL_AGENT_REGISTER_LINK_WHATSAPP_SENT')
        ->and($contents)->toContain('AUDIT_GENERAL_AGENT_REGISTER_LINK_WHATSAPP_FAILED')
        ->and($contents)->toContain('AUDIT_GENERAL_AGENT_REGISTER_LINK_SEND_FAILED')
        ->and($contents)->toContain('general.agents.send-register-link')
        ->and($contents)->toContain("/agent/c/'.Crypt::encryptString(\$agencyCode)");
});
