<?php

declare(strict_types=1);

it('theme admin define botón iOS danger y EditTelemedicineDoctor lo usa', function () {
    $root = dirname(__DIR__, 2);
    $theme = file_get_contents($root.'/resources/css/filament/admin/theme.css');
    expect($theme)->toContain('.aviso-btn-ios-danger');

    $page = file_get_contents(
        $root.'/app/Filament/Operations/Resources/TelemedicineDoctors/Pages/EditTelemedicineDoctor.php'
    );
    expect($page)->toContain('TICKET_BUTTON_DANGER_CLASS')
        ->toContain('aviso-btn-ios-danger');
});
