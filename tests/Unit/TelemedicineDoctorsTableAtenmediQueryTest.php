<?php

declare(strict_types=1);

it('restringe doctores a managed_by ATENMEDI cuando el usuario pertenece a ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineDoctors/Tables/TelemedicineDoctorsTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('modifyQueryUsing')
        ->toContain("in_array('ATENMEDI', Auth::user()?->departament ?? [], true)")
        ->toContain("->where('managed_by', 'ATENMEDI')");
});

it('muestra el sello digital de forma visible en el directorio de médicos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineDoctors/Tables/TelemedicineDoctorsTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("ImageColumn::make('signature')")
        ->toContain("->label('Sello')")
        ->toContain('->imageHeight(96)')
        ->toContain('->imageWidth(220)')
        ->toContain("->disk('public')")
        ->toContain("->visibility('public')")
        ->toContain('isToggledHiddenByDefault: false');
});
