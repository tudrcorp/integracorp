<?php

declare(strict_types=1);

it('filtra casos por managed_by ATENMEDI en el dashboard de telemedicina para usuarios ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('is_array($departments)')
        ->toContain("in_array('ATENMEDI', \$departments, true)")
        ->toContain("->where('managed_by', 'ATENMEDI')")
        ->toContain('->where(\'telemedicine_doctor_id\', $user->doctor_id)');
});
