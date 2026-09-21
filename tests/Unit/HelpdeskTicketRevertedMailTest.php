<?php

declare(strict_types=1);

use App\Exceptions\HelpdeskTicketMailException;
use App\Mail\SendEmailHelpdeskTicketReverted;
use App\Models\HelpDesk;

uses(Tests\TestCase::class);

it('fromTicket rechaza un modelo no persistido', function (): void {
    $desk = new HelpDesk([
        'description' => 'Solo en memoria',
        'priority' => 'MEDIA',
        'status' => 'PENDIENTE POR INICIAR',
    ]);

    expect(fn () => SendEmailHelpdeskTicketReverted::fromTicket(
        $desk,
        'Becky Acosta',
        'Faltan capturas del error.',
    ))->toThrow(HelpdeskTicketMailException::class);
});

it('arma el correo de reversión para el creador del ticket', function (): void {
    $ticket = new HelpDesk([
        'description' => 'El listado no filtra por agencia.',
        'priority' => 'ALTA',
        'status' => 'REVERTIDO',
        'created_by' => 'Ana Pérez',
    ]);
    $ticket->id = 441;
    $ticket->exists = true;

    $mailable = SendEmailHelpdeskTicketReverted::fromTicket(
        $ticket,
        'Becky Acosta',
        'Faltan capturas del error en producción.',
        'Ana Pérez',
    );

    $data = $mailable->buildViewData();

    expect($mailable->envelope()->subject)
        ->toContain('Ticket de soporte revertido')
        ->toContain('TKT-000441')
        ->and($data['creatorName'])->toBe('Ana Pérez')
        ->and($data['revertedBy'])->toBe('Becky Acosta')
        ->and($data['reason'])->toBe('Faltan capturas del error en producción.')
        ->and($data['ticketReference'])->toBe('TKT-000441')
        ->and($data['status'])->toBe('REVERTIDO')
        ->and($data['priority'])->toBe('ALTA');
});
