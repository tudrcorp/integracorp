<?php

declare(strict_types=1);

it('oculta el ticker de helpdesk en la pagina kanban del panel projects', function (): void {
    $wrapperPath = dirname(__DIR__, 2).'/resources/views/filament/hooks/projects-helpdesk-tickets-ticker-wrapper.blade.php';
    $supportPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectsPanelHelpdeskTicketsTicker.php';

    expect(file_get_contents($wrapperPath))
        ->toContain('ProjectsPanelHelpdeskTicketsTicker::shouldDisplay()');

    expect(file_get_contents($supportPath))
        ->toContain('instanceof Kanban')
        ->toContain('Livewire::current()');
});

it('no muestra encabezado duplicado de filament en kanban', function (): void {
    $kanbanPath = dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php';

    expect(file_get_contents($kanbanPath))
        ->toContain('public function getHeading(): string|Htmlable|null')
        ->toContain('public function getSubheading(): string|Htmlable|null')
        ->toMatch('/function getHeading\(\)[^{]+\{[^}]*return null;/s')
        ->toMatch('/function getSubheading\(\)[^{]+\{[^}]*return null;/s');
});
