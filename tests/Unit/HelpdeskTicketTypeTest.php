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

it('catalogo de tipos incluye campos de guia sin ejemplo', function (): void {
    $incident = HelpdeskTicketType::catalog()[HelpdeskTicketType::INCIDENT];

    expect($incident)->toHaveKeys(['title', 'subtitle', 'definition', 'not_this', 'tone'])
        ->and($incident)->not->toHaveKey('example');
});

it('integra pestana tipo de ticket con radio en el schema', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php';
    $src = file_get_contents($path);

    expect($src)
        ->toContain("Tab::make('Tipo de ticket')")
        ->toContain("Radio::make('ticket_type')")
        ->toContain('fi-helpdesk-ticket-type-panel')
        ->not->toContain('ticket_type_matrix')
        ->not->toContain('ticket_type_selected_guide')
        ->not->toContain('selectedTypeGuide');
});
