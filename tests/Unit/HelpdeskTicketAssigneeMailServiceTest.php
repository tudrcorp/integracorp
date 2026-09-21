<?php

declare(strict_types=1);

it('HelpdeskTicketAssigneeMailService envía un mailable por cada colaborador asignado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Services/HelpdeskTicketAssigneeMailService.php';
    $src = file_get_contents($path);
    expect($src)->toContain('function sendToEachAssignee')
        ->toContain('rrhhColaboradores')
        ->toContain('SendEmailCreateTicketAndAssigned::fromTicket($ticket, $colaborador, $isReassignment)');
});

it('HelpdeskTicketAssigneeMailService envía el correo de reversión al creador', function (): void {
    $path = dirname(__DIR__, 2).'/app/Services/HelpdeskTicketAssigneeMailService.php';
    $src = file_get_contents($path);

    expect($src)
        ->toContain('function sendRevertedToCreatorWithReport')
        ->toContain('function resolveTicketCreatorEmailData')
        ->toContain('$ticket->created_by_user_id')
        ->toContain('SendEmailHelpdeskTicketReverted::fromTicket');
});
