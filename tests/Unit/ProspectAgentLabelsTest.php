<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;

it('define catálogos de tipo, estatus y referido', function (): void {
    expect(ProspectAgentLabels::typeOptions())->not->toBeEmpty()
        ->and(ProspectAgentLabels::statusOptions())->not->toBeEmpty()
        ->and(ProspectAgentLabels::referenceOptions())->not->toBeEmpty();
});

it('resuelve etiquetas legibles para valores conocidos', function (): void {
    expect(ProspectAgentLabels::typeLabel('freelance'))->toBe('Freelance')
        ->and(ProspectAgentLabels::statusLabel('captación'))->toBe('Captación')
        ->and(ProspectAgentLabels::referenceLabel('directiva-TDG'))->toBe('Directiva TDG');
});

it('asigna color de badge según el estatus del embudo', function (): void {
    expect(ProspectAgentLabels::statusColor('aliado-activo'))->toBe('success')
        ->and(ProspectAgentLabels::statusColor('inactivo'))->toBe('danger');
});
