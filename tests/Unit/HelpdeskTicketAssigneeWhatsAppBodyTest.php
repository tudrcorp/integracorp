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
