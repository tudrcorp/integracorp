<?php

declare(strict_types=1);

use App\Exceptions\HelpdeskTicketMailException;
use App\Mail\SendEmailCreateTicketAndAssigned;
use App\Models\HelpDesk;

it('fromTicket rechaza un modelo no persistido', function (): void {
    $desk = new HelpDesk([
        'description' => 'Solo en memoria',
        'priority' => 'MEDIA',
        'status' => 'PENDIENTE POR INICIAR',
    ]);

    expect(fn () => SendEmailCreateTicketAndAssigned::fromTicket($desk))
        ->toThrow(HelpdeskTicketMailException::class);
});

it('ticketNotFound adjunta contexto con el id', function (): void {
    $e = HelpdeskTicketMailException::ticketNotFound(77);

    expect($e->getMessage())->toContain('77')
        ->and($e->context)->toMatchArray(['help_desk_id' => 77]);
});
