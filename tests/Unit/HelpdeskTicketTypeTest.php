<?php

declare(strict_types=1);

use App\Support\HelpdeskTicketType;

it('expone las cinco categorias de ticket itil simplificado', function (): void {
    expect(HelpdeskTicketType::options())->toHaveCount(5)
        ->and(HelpdeskTicketType::radioDescriptions())->toHaveCount(5)
        ->and(HelpdeskTicketType::options())->toHaveKeys([
            HelpdeskTicketType::INCIDENT,
            HelpdeskTicketType::SERVICE_REQUEST,
            HelpdeskTicketType::PROBLEM,
            HelpdeskTicketType::CHANGE_REQUEST,
            HelpdeskTicketType::FEATURE_REQUEST,
        ]);
});

it('asigna color de badge filament por tipo de ticket', function (): void {
    expect(HelpdeskTicketType::filamentColor(HelpdeskTicketType::INCIDENT))->toBe('danger')
        ->and(HelpdeskTicketType::filamentColor(HelpdeskTicketType::SERVICE_REQUEST))->toBe('info')
        ->and(HelpdeskTicketType::filamentColor(HelpdeskTicketType::PROBLEM))->toBe('warning')
        ->and(HelpdeskTicketType::filamentColor(HelpdeskTicketType::CHANGE_REQUEST))->toBe('primary')
        ->and(HelpdeskTicketType::filamentColor(HelpdeskTicketType::FEATURE_REQUEST))->toBe('success')
        ->and(HelpdeskTicketType::label(null))->toBe('Sin tipo')
        ->and(HelpdeskTicketType::label(HelpdeskTicketType::INCIDENT))->toBe('Incidencia');
});

it('resume la definicion en una linea por tipo', function (): void {
    $incident = HelpdeskTicketType::catalog()[HelpdeskTicketType::INCIDENT];

    expect(strlen($incident['definition']))->toBeLessThan(120)
        ->and($incident)->toHaveKeys(['title', 'subtitle', 'definition', 'example', 'not_this', 'tone']);
});

it('muestra guia compacta al seleccionar incidencia', function (): void {
    $html = HelpdeskTicketType::selectedTypeGuide(HelpdeskTicketType::INCIDENT)->toHtml();

    expect($html)
        ->toContain('fi-helpdesk-ticket-type-hero')
        ->toContain('Incidencia')
        ->toContain('error 500')
        ->toContain('No usar si:');
});

it('integra pestana tipo de ticket con radio en el schema', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php';
    $src = file_get_contents($path);

    expect($src)
        ->toContain("Tab::make('Tipo de ticket')")
        ->toContain("Radio::make('ticket_type')")
        ->toContain('fi-helpdesk-ticket-type-panel')
        ->not->toContain('ticket_type_matrix');
});
