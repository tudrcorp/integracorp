<?php

declare(strict_types=1);

use App\Models\DoctorNurse;
use App\Services\DoctorNurseFichaPdfService;

it('genera un nombre de archivo seguro para la ficha PDF', function () {
    $doctorNurse = new DoctorNurse;
    $doctorNurse->id = 86;
    $doctorNurse->name = 'María Pérez / Prueba';

    expect(DoctorNurseFichaPdfService::downloadFilename($doctorNurse))
        ->toBe('ficha_tecnica_proveedor_natural_Mara_Prez_Prueba.pdf');
});
