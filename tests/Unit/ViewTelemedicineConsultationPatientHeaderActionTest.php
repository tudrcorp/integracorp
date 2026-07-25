<?php

declare(strict_types=1);

it('el detalle de seguimiento usa estilos iOS en el header action Regresar', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/ViewTelemedicineConsultationPatient.php'
    );

    expect($contents)
        ->toContain("Action::make('back')")
        ->toContain("->label('Regresar')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('primary')");
});
