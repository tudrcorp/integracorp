<?php

declare(strict_types=1);

use App\Support\Filament\FilamentIosButton;

it('incluye no-urgente en el mapeo de estilos iOS alineado a AppServiceProvider', function (): void {
    expect(FilamentIosButton::extraClassForFilamentColor('no-urgente'))
        ->toContain('aviso-btn-ios-primary')
        ->and(FilamentIosButton::extraClassForFilamentColor('urgencia'))
        ->toContain('aviso-btn-ios-warning')
        ->and(FilamentIosButton::extraClassForFilamentColor('emergencia'))
        ->toContain('aviso-btn-ios-warning')
        ->and(FilamentIosButton::extraClassForFilamentColor('critico'))
        ->toContain('aviso-btn-ios-danger');
});

it('el infolist de detalle de caso usa el badge de prioridad con no urgente', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php'
    );

    expect($contents)
        ->toContain('TelemedicinePriorityFilamentBadge::color(')
        ->toContain('TelemedicinePriorityFilamentBadge::icon(');
});
