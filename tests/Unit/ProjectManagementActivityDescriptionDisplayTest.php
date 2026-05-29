<?php

declare(strict_types=1);

use App\Support\Filament\ProjectManagement\ProjectManagementActivityInfolistDisplay;

it('normaliza la descripcion eliminando sangria al inicio de cada linea', function (): void {
    expect(ProjectManagementActivityInfolistDisplay::normalizeDescriptionText("  Primera\n    Segunda\n"))->toBe("Primera\nSegunda");
    expect(ProjectManagementActivityInfolistDisplay::normalizeDescriptionText(''))->toBe('');
});
