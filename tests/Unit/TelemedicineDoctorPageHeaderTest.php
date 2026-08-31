<?php

declare(strict_types=1);

use App\Models\TelemedicineDoctor;
use App\Support\Filament\TelemedicineDoctorPageHeader;

it('arma el encabezado de edición con nombre, especialidad y contacto', function (): void {
    $doctor = new TelemedicineDoctor([
        'full_name' => 'CAROLINA JOSEFINA PINILLO LAMEDA',
        'status' => 'ACTIVO',
        'specialty' => 'MÉDICO GENERAL',
        'managed_by' => 'TDG',
        'nro_identificacion' => '19750446',
        'email' => 'carol750446@gmail.com',
        'phone' => '+584120987654',
        'code' => 'DOC-101',
        'code_mpps' => '12345',
    ]);

    $html = (string) TelemedicineDoctorPageHeader::forDoctor($doctor, context: 'edit');

    expect($html)
        ->toContain('Editar médico · DOC-101')
        ->toContain('CAROLINA JOSEFINA PINILLO LAMEDA')
        ->toContain('ACTIVO')
        ->toContain('MÉDICO GENERAL')
        ->toContain('TDG')
        ->toContain('C.I.: 19750446')
        ->toContain('carol750446@gmail.com')
        ->toContain('+584120987654')
        ->toContain('MPPS: 12345')
        ->not->toContain('Editar Telemedicine Doctor');
});

it('usa el contexto de perfil y omite datos vacíos', function (): void {
    $doctor = new TelemedicineDoctor([
        'full_name' => 'ANA PEREZ',
        'status' => 'INACTIVO',
        'specialty' => null,
        'email' => null,
        'phone' => '',
        'code_mpps' => null,
    ]);

    $html = (string) TelemedicineDoctorPageHeader::forDoctor($doctor, context: 'profile');

    expect($html)
        ->toContain('Mi perfil médico')
        ->toContain('ANA PEREZ')
        ->toContain('INACTIVO')
        ->toContain('Sin especialidad')
        ->not->toContain('C.I.:')
        ->not->toContain('MPPS:')
        ->not->toContain('Editar médico');
});

it('la pagina de operaciones usa el encabezado y deja de titulos en ingles', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineDoctors/Pages/EditTelemedicineDoctor.php');
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineDoctors/TelemedicineDoctorResource.php');

    expect($page)
        ->toContain('TelemedicineDoctorPageHeader::forDoctor')
        ->toContain('Volver al directorio')
        ->toContain('Deshabilitar médico')
        ->toContain('Habilitar médico')
        ->toContain('TICKET_BUTTON_DANGER_CLASS')
        ->and($resource)
        ->toContain("protected static ?string \$modelLabel = 'médico'")
        ->toContain("protected static ?string \$recordTitleAttribute = 'full_name'");
});
