<?php

declare(strict_types=1);

it('define flujo de invitacion whatsapp con vista mobile para agenda corporativa', function (): void {
    $servicePath = dirname(__DIR__, 2).'/app/Services/CorporateAgendaInvitationWhatsAppService.php';
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/Business/CorporateAgendaInvitationResponseController.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/corporate-agenda/invitation-mobile.blade.php';
    $routesPath = dirname(__DIR__, 2).'/routes/web.php';
    $agendaPagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/AgendaCorporativa.php';

    $serviceContents = file_get_contents($servicePath);
    $controllerContents = file_get_contents($controllerPath);
    $viewContents = file_get_contents($viewPath);
    $routesContents = file_get_contents($routesPath);
    $agendaPageContents = file_get_contents($agendaPagePath);

    expect($serviceContents)
        ->toContain('dispatchInvitationToParticipant')
        ->toContain('buildInvitationBody')
        ->toContain('Invitacion - Tu Dr. en Casa, C.A.')
        ->toContain('Enlace de la videollamada')
        ->toContain('Confirma tu participacion aqui')
        ->toContain('👥 Participantes:')
        ->toContain('notifyCreatorAboutInvitationResponse');

    expect($controllerContents)
        ->toContain('CorporateAgendaInvitationResponseController')
        ->toContain('temporarySignedRoute')
        ->toContain('agenda.invitation.respond')
        ->toContain('invitation_response_state')
        ->toContain('Debes indicar el motivo del rechazo.');

    expect($viewContents)
        ->toContain('Volver a WhatsApp')
        ->toContain('Aceptar invitacion')
        ->toContain('Rechazar invitacion')
        ->toContain('slideIn')
        ->toContain('pulseGlow');

    expect($routesContents)
        ->toContain("->name('agenda.invitation.show')")
        ->toContain("->name('agenda.invitation.respond')")
        ->toContain("CorporateAgendaInvitationResponseController::class, 'show'")
        ->toContain("CorporateAgendaInvitationResponseController::class, 'respond'");

    expect($agendaPageContents)
        ->toContain('dispatchAgendaInvitationsToParticipants')
        ->toContain('CorporateAgendaInvitationWhatsAppService::dispatchInvitationToParticipant');
});
