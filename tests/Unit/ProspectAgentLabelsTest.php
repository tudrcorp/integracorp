<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;

it('define catálogos de tipo, estatus y referido', function (): void {
    expect(ProspectAgentLabels::typeOptions())->not->toBeEmpty()
        ->and(ProspectAgentLabels::statusOptions())->not->toBeEmpty()
        ->and(ProspectAgentLabels::referenceOptions())->not->toBeEmpty();
});

it('define exactamente los cinco estatus del embudo en orden', function (): void {
    expect(array_keys(ProspectAgentLabels::statusOptions()))->toBe([
        ProspectAgentLabels::STATUS_CAPTACION_PROSPECCION,
        ProspectAgentLabels::STATUS_CONTACTO_INICIAL,
        ProspectAgentLabels::STATUS_EN_PROCESO_NEGOCIACION,
        ProspectAgentLabels::STATUS_ALIADO_ACTIVO,
        ProspectAgentLabels::STATUS_INACTIVO,
    ])->and(array_values(ProspectAgentLabels::statusOptions()))->toBe([
        'Captación/prospección',
        'Contacto inicial',
        'En proceso/En negociación',
        'Aliado activo',
        'Inactivo',
    ]);
});

it('resuelve etiquetas legibles para valores conocidos y legacy', function (): void {
    expect(ProspectAgentLabels::typeLabel('freelance'))->toBe('Freelance')
        ->and(ProspectAgentLabels::statusLabel(ProspectAgentLabels::STATUS_CAPTACION_PROSPECCION))->toBe('Captación/prospección')
        ->and(ProspectAgentLabels::statusLabel('captación'))->toBe('Captación/prospección')
        ->and(ProspectAgentLabels::statusLabel('prospecto'))->toBe('Captación/prospección')
        ->and(ProspectAgentLabels::statusLabel('en-proceso'))->toBe('En proceso/En negociación')
        ->and(ProspectAgentLabels::statusLabel('en-negociación'))->toBe('En proceso/En negociación')
        ->and(ProspectAgentLabels::statusLabel(ProspectAgentLabels::STATUS_ALIADO_ACTIVO))->toBe('Aliado activo')
        ->and(ProspectAgentLabels::referenceLabel('directiva-TDG'))->toBe('Directiva TDG')
        ->and(ProspectAgentLabels::defaultStatus())->toBe(ProspectAgentLabels::STATUS_CAPTACION_PROSPECCION);
});

it('asigna color de badge según el estatus del embudo', function (): void {
    expect(ProspectAgentLabels::statusColor(ProspectAgentLabels::STATUS_ALIADO_ACTIVO))->toBe('success')
        ->and(ProspectAgentLabels::statusColor(ProspectAgentLabels::STATUS_INACTIVO))->toBe('danger')
        ->and(ProspectAgentLabels::statusColor('en-proceso'))->toBe('primary');
});
