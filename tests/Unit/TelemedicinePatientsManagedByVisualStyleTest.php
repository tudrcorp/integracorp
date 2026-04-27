<?php

declare(strict_types=1);

it('aplica estilos visuales para diferenciar pacientes ATENMEDI y TDG en la tabla', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TextColumn::make('managed_by')")
        ->toContain("'ATENMEDI' => 'success'")
        ->toContain("'TDG' => 'info'")
        ->toContain('->recordClasses(function (TelemedicinePatient $record): array')
        ->toContain('bg-emerald-50/60 dark:bg-emerald-950/20')
        ->toContain('bg-sky-50/60 dark:bg-sky-950/20');
});
