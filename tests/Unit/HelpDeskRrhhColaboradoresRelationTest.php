<?php

declare(strict_types=1);

it('HelpDesk define relación rrhhColaboradores con pivot help_desk_rrhh_colaborador', function (): void {
    $path = dirname(__DIR__, 2).'/app/Models/HelpDesk.php';
    $src = file_get_contents($path);
    expect($src)->toContain('function rrhhColaboradores()')
        ->toContain('help_desk_rrhh_colaborador')
        ->not->toContain('rrhh_colaborador_id');
});
