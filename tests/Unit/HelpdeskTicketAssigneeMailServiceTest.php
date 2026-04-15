<?php

declare(strict_types=1);

it('HelpdeskTicketAssigneeMailService envía un mailable por cada colaborador asignado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Services/HelpdeskTicketAssigneeMailService.php';
    $src = file_get_contents($path);
    expect($src)->toContain('function sendToEachAssignee')
        ->toContain('rrhhColaboradores')
        ->toContain('SendEmailCreateTicketAndAssigned::fromTicket($ticket, $colaborador)');
});
