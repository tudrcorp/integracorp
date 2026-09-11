<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;

uses(Tests\TestCase::class);

it('resalta la nota en el mensaje whatsapp con titulo de actualizacion', function (): void {
    $ticket = new HelpDesk;
    $ticket->id = 9876;
    $ticket->status = 'EN PROCESO';

    $message = HelpdeskTicketAssigneeWhatsAppService::buildNoteAddedBody(
        ticket: $ticket,
        addedBy: 'Operador QA',
        noteHtml: '<p>Se validó el caso y se actualizó el estatus.</p>',
    );

    expect($message)
        ->toContain('*Actualizacion:*')
        ->toContain('*Se validó el caso y se actualizó el estatus.*');
});

it('el mensaje whatsapp de reasignacion incluye responsable y motivo', function (): void {
    $ticket = new HelpDesk;
    $ticket->id = 4321;
    $ticket->status = 'EN PROCESO';
    $ticket->priority = 'ALTA';

    $message = HelpdeskTicketAssigneeWhatsAppService::buildReassignedToNewAssigneeBody(
        ticket: $ticket,
        reassignedBy: 'Ana Pérez',
        explanationHtml: '<p>Corresponde al equipo de sistemas.</p>',
    );

    expect($message)
        ->toContain('Le reasignaron un ticket')
        ->toContain('Ticket N.º 4321')
        ->toContain('Reasignado por: Ana Pérez')
        ->toContain('Motivo: Corresponde al equipo de sistemas.');
});

it('el mensaje whatsapp de desasignacion avisa que ya no es responsable', function (): void {
    $ticket = new HelpDesk;
    $ticket->id = 55;
    $ticket->status = 'EN PROCESO';

    $message = HelpdeskTicketAssigneeWhatsAppService::buildUnassignedBody($ticket, 'Luis Mora');

    expect($message)
        ->toContain('Ya no figura como responsable')
        ->toContain('Ticket N.º 55')
        ->toContain('Reasignado por: Luis Mora');
});
