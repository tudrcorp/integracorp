<?php

declare(strict_types=1);

it('HelpDesk define relación rrhhColaboradores con pivot help_desk_rrhh_colaborador', function (): void {
    $path = dirname(__DIR__, 2).'/app/Models/HelpDesk.php';
    $src = file_get_contents($path);
    expect($src)->toContain('function rrhhColaboradores()')
        ->toContain('help_desk_rrhh_colaborador')
        ->not->toContain('rrhh_colaborador_id')
        ->not->toContain('help_desk_category')
        ->not->toContain('HelpDeskCategory');
});

it('elimina el modelo zombie HelpDeskCategory', function (): void {
    expect(file_exists(dirname(__DIR__, 2).'/app/Models/HelpDeskCategory.php'))->toBeFalse()
        ->and(file_exists(dirname(__DIR__, 2).'/app/Http/Controllers/HelpDeskCategoryController.php'))->toBeFalse();
});
