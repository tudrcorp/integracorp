<?php

declare(strict_types=1);

it('replica el ordenamiento de tickets por status en todos los módulos', function () {
    $expected = "CASE status\n                            WHEN 'PENDIENTE POR INICIAR' THEN 1\n                            WHEN 'EN PROCESO' THEN 2\n                            WHEN 'TERMINADO' THEN 3\n                            ELSE 4\n                        END";

    $root = dirname(__DIR__, 2);

    $paths = [
        $root.'/app/Filament/Administration/Resources/Helpdesks/Tables/HelpdesksTable.php',
        $root.'/app/Filament/Marketing/Resources/Helpdesks/Tables/HelpdesksTable.php',
        $root.'/app/Filament/Operations/Resources/Helpdesks/Tables/HelpdesksTable.php',
    ];

    foreach ($paths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->not->toBeFalse()
            ->toContain($expected);
    }
});
