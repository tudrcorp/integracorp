<?php

declare(strict_types=1);

use App\Support\HelpdeskStatusChangeNote;

it('rechaza explicacion vacia del analista asignado', function (): void {
    $error = HelpdeskStatusChangeNote::validateAssigneeExplanation('<p></p>');

    expect($error)->not->toBeNull()
        ->and($error['title'])->toBe('Explicación requerida');
});

it('combina cambio de estado con nota del analista', function (): void {
    $html = HelpdeskStatusChangeNote::buildObservationHtml(
        'PENDIENTE POR INICIAR',
        'EN PROCESO',
        '<p>Se contactó al usuario y se inició la revisión.</p>',
        true,
    );

    expect($html)
        ->toContain('PENDIENTE POR INICIAR')
        ->toContain('EN PROCESO')
        ->toContain('Motivo del cambio (analista asignado)')
        ->toContain('Se contactó al usuario');
});

it('modal actualizar estado incluye nota obligatoria para asignados', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php';

    expect(file_get_contents($path))
        ->toContain('HelpdeskStatusChangeNote::assigneeExplanationEditor')
        ->toContain('Explicación del cambio')
        ->toContain('validateAssigneeExplanation')
        ->toContain('buildObservationHtml');
});
