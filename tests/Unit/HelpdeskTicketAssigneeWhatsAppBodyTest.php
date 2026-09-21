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

it('el mensaje de reversión Scrum va dirigido al creador del ticket', function (): void {
    $ticket = new HelpDesk;
    $ticket->id = 441;
    $ticket->created_by = 'Ana Pérez';

    $message = HelpdeskTicketAssigneeWhatsAppService::buildRevertedToCreatorBody(
        $ticket,
        'Becky Acosta',
        'Faltan capturas del error en producción.'
    );

    expect($message)
        ->toContain('revirtió el ticket N.º 441 que usted creó')
        ->toContain('*Motivo:* Faltan capturas del error en producción.')
        ->toContain('vuelva a enviarlo para su evaluación');
});

it('el mensaje de reenvío Scrum avisa al Product Owner', function (): void {
    $ticket = new HelpDesk;
    $ticket->id = 443;
    $ticket->created_by = 'Ana Pérez';

    $message = HelpdeskTicketAssigneeWhatsAppService::buildResubmittedToProductOwnerBody(
        $ticket,
        'Ana Pérez',
    );

    expect($message)
        ->toContain('corrigió y reenvió el ticket N.º 443 a su backlog')
        ->toContain('evaluarlo de nuevo o asignarlo al sprint');
});

it('el mensaje de reasignación al sprint identifica al equipo', function (): void {
    $ticket = new HelpDesk;
    $ticket->id = 442;
    $ticket->created_by = 'Ana Pérez';

    $message = HelpdeskTicketAssigneeWhatsAppService::buildSprintReassignedBody(
        $ticket,
        'Becky Acosta',
        'ANTHONY JESUS AULAR GUZMAN, GUSTAVO CAMACHO'
    );

    expect($message)
        ->toContain('Le reasignaron un ticket de soporte')
        ->toContain('Ticket N.º 442')
        ->toContain('Reasignado por: Becky Acosta')
        ->toContain('Equipo: ANTHONY JESUS AULAR GUZMAN, GUSTAVO CAMACHO');
});

it('resuelve el teléfono del creador del ticket por created_by_user_id', function (): void {
    $path = dirname(__DIR__, 2).'/app/Services/HelpdeskTicketAssigneeWhatsAppService.php';

    expect(file_get_contents($path))
        ->toContain('$ticket->created_by_user_id')
        ->toContain('function resolveTicketCreatorPhoneData')
        ->toContain('function buildRevertedToCreatorBody');
});
