<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;

it('resuelve color e icono para nombre Estándar y variantes en mayúsculas', function (string $name, string $expectedColor): void {
    expect(TelemedicinePriorityFilamentBadge::color($name))->toBe($expectedColor)
        ->and(TelemedicinePriorityFilamentBadge::icon($name))->not->toBeEmpty();
})->with([
    'acentuado titulo' => ['Estándar', 'estandar'],
    'mayus sin tilde' => ['ESTANDAR', 'estandar'],
    'mayus con tilde' => ['ESTÁNDAR', 'estandar'],
    'no urgente mixto' => ['No Urgente', 'no-urgente'],
    'no urgente caps' => ['NO URGENTE', 'no-urgente'],
    'critico titulo' => ['Critico', 'critico'],
    'critico mayus' => ['CRITICO', 'critico'],
    'legacy alta' => ['ALTA', 'success'],
    'legacy media' => ['MEDIA', 'warning'],
    'legacy baja' => ['BAJA', 'primary'],
]);

it('usa valores por defecto para prioridad desconocida', function (): void {
    expect(TelemedicinePriorityFilamentBadge::color('OTRA'))->toBe('gray')
        ->and(TelemedicinePriorityFilamentBadge::icon('OTRA'))->toBe('healthicons-f-health');
});

it('asigna clases de fila para Estándar y sin prioridad', function (): void {
    expect(TelemedicinePriorityFilamentBadge::recordRowClasses('Estándar'))->toContain('#02976d')
        ->and(TelemedicinePriorityFilamentBadge::recordRowClasses(null))->toContain('border-gray-200');
});

it('asigna clases de fila para Critico en titulo', function (): void {
    expect(TelemedicinePriorityFilamentBadge::recordRowClasses('Critico'))->toContain('#e4003b');
});
