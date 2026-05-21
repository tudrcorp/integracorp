<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Services\HelpdeskTeamMembersWhatsAppService;

uses(Tests\TestCase::class);

it('incluye nombre del equipo e integrantes en el mensaje whatsapp del equipo', function (): void {
    $ticket = new HelpDesk;
    $ticket->id = 4321;
    $ticket->team = 'Soporte Nivel 2';
    $ticket->team_members = [
        ['id' => 10, 'name' => 'Ana Pérez', 'telefono_corporativo' => '04141234567'],
        ['id' => 11, 'name' => 'Luis Gómez', 'telefono_corporativo' => '04147654321'],
    ];
    $ticket->description = 'Falla en el módulo de reportes.';
    $ticket->priority = 'ALTA';
    $ticket->created_by = 'Coordinador RRHH';
    $ticket->created_at = now();

    $message = HelpdeskTeamMembersWhatsAppService::buildWhatsAppBodyForTeamTicket($ticket);

    expect($message)
        ->toContain('debe ser resuelto por su equipo de ejecución')
        ->toContain('Ticket N.º 4321')
        ->toContain('*Equipo:* Soporte Nivel 2')
        ->toContain('• Ana Pérez')
        ->toContain('• Luis Gómez')
        ->toContain('Falla en el módulo de reportes.');
});

it('create helpdesk despacha whatsapp al equipo via trait HelpdeskTeamMembersWhatsAppBodyTest', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/CreateHelpdesk.php";
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('dispatchHelpdeskCreateNotifications')
        ->toContain('DispatchesHelpdeskCreateNotifications');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);

it('trait HelpdeskTeamMembersWhatsAppBodyTest notificaciones incluye whatsapp del equipo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Concerns/DispatchesHelpdeskCreateNotifications.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('HelpdeskTeamMembersWhatsAppService::dispatchToEachTeamMemberWithReport')
        ->toContain('team_whatsapp_report');
});
