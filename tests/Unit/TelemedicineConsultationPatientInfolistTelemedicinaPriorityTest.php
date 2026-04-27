<?php

declare(strict_types=1);

it('usa TelemedicinePriorityFilamentBadge para color e icono de prioridad en infolist telemedicina', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;')
        ->toContain('TelemedicinePriorityFilamentBadge::color($state)')
        ->toContain('TelemedicinePriorityFilamentBadge::icon($state)');
});
