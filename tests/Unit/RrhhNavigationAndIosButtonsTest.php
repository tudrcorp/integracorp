<?php

declare(strict_types=1);

it('mantiene estilo iOS en menu RRHH y acciones principales', function (): void {
    $resourceFiles = [
        'RrhhAsignacionResource' => dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhAsignacions/RrhhAsignacionResource.php',
        'RrhhCargoResource' => dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhCargos/RrhhCargoResource.php',
        'RrhhDepartamentoResource' => dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDepartamentos/RrhhDepartamentoResource.php',
        'RrhhDeduccionResource' => dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDeduccions/RrhhDeduccionResource.php',
        'RrhhColaboradorResource' => dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/RrhhColaboradorResource.php',
        'RrhhPrestamoResource' => dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhPrestamos/RrhhPrestamoResource.php',
    ];

    foreach ($resourceFiles as $file) {
        $contents = file_get_contents($file);

        expect($contents)
            ->toContain("navigationGroup = 'RRHH'")
            ->toContain('navigationIcon');
    }

    $listPages = [
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhAsignacions/Pages/ListRrhhAsignacions.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhCargos/Pages/ListRrhhCargos.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDepartamentos/Pages/ListRrhhDepartamentos.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDeduccions/Pages/ListRrhhDeduccions.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Pages/ListRrhhColaboradors.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhPrestamos/Pages/ListRrhhPrestamos.php',
    ];

    foreach ($listPages as $file) {
        $contents = file_get_contents($file);

        expect($contents)
            ->toContain('CreateAction::make()')
            ->toContain('ticket-btn-ios');
    }

    $tableFiles = [
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhAsignacions/Tables/RrhhAsignacionsTable.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhCargos/Tables/RrhhCargosTable.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDepartamentos/Tables/RrhhDepartamentosTable.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhDeduccions/Tables/RrhhDeduccionsTable.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Tables/RrhhColaboradorsTable.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhPrestamos/Tables/RrhhPrestamosTable.php',
    ];

    foreach ($tableFiles as $file) {
        $contents = file_get_contents($file);

        expect($contents)
            ->toContain('EditAction::make()')
            ->toContain('DeleteBulkAction::make()')
            ->toContain('ticket-btn-ios')
            ->toContain('aviso-btn-ios-danger');
    }
});
