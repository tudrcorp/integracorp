<?php

declare(strict_types=1);

it('el formulario de guardias usa secciones, relación y estado por fecha', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationOnCallUsers/Schemas/OperationOnCallUserForm.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain("Section::make('Colaborador en guardia')")
        ->and($contents)->toContain("->relationship('rrhh_colaborador', 'fullName'")
        ->and($contents)->toContain('syncStatusFromDate')
        ->and($contents)->toContain('Heroicon::CalendarDays')
        ->and($contents)->toContain("->mask('99:99')");
});

it('el infolist de guardias agrupa en secciones y muestra estado con badge', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationOnCallUsers/Schemas/OperationOnCallUserInfolist.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain("Section::make('Colaborador y contacto')")
        ->and($contents)->toContain("Section::make('Turno de guardia')")
        ->and($contents)->toContain("Section::make('Auditoría')")
        ->and($contents)->toContain("TextEntry::make('status')")
        ->and($contents)->toContain('->badge()')
        ->and($contents)->toContain('DE GUARDIA');
});
