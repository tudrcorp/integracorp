<?php

declare(strict_types=1);

it('mantiene badge de gestionado por sin estilos de fila en la tabla de pacientes', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TextColumn::make('managed_by')")
        ->toContain("'ATENMEDI' => 'success'")
        ->toContain("'TDG' => 'info'")
        ->not->toContain('->recordClasses(')
        ->not->toContain('bg-emerald-50/60 dark:bg-emerald-950/20')
        ->not->toContain('bg-sky-50/60 dark:bg-sky-950/20');
});
