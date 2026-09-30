<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineDoctors\Pages\ListTelemedicineDoctors;
use App\Models\TelemedicineDoctor;
use App\Support\Filament\TelemedicineDoctorPageHeader;

uses(Tests\TestCase::class);

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

it('el encabezado de «Mi Perfil» marca como pendientes la firma y las credenciales faltantes', function (): void {
    $doctor = new TelemedicineDoctor([
        'full_name' => 'ANA PEREZ',
        'status' => 'ACTIVO',
        'specialty' => 'MÉDICO GENERAL',
        'signature' => 'doctors/firma.png',
        'code_cm' => '4567',
        'code_mpps' => null,
        'image' => '',
    ]);

    expect(ListTelemedicineDoctors::profileChecklist($doctor))->toBe([
        ['label' => 'Firma digital', 'done' => true],
        ['label' => 'CM', 'done' => true],
        ['label' => 'MPPS', 'done' => false],
        ['label' => 'Foto de perfil', 'done' => false],
    ]);
});

it('el encabezado de «Mi Perfil» muestra al médico y avisa cuando el usuario no tiene médico vinculado', function (): void {
    $doctor = new TelemedicineDoctor(['full_name' => 'ANA PEREZ', 'status' => 'ACTIVO', 'specialty' => 'MÉDICO GENERAL']);

    $linked = view('filament.telemedicina.doctors.profile-list-header', [
        'fullName' => $doctor->full_name,
        'specialty' => $doctor->specialty,
        'status' => $doctor->status,
        'checklist' => ListTelemedicineDoctors::profileChecklist($doctor),
    ])->render();

    $unlinked = view('filament.telemedicina.doctors.profile-list-header', [
        'fullName' => null,
        'checklist' => [],
    ])->render();

    expect($linked)
        ->toContain('Mi perfil médico')
        ->toContain('Dr(a). ANA PEREZ')
        ->toContain('MÉDICO GENERAL')
        ->toContain('ACTIVO')
        ->toContain('Firma digital')
        ->toContain('pendiente')
        ->and($unlinked)
        ->toContain('no tiene un médico asociado')
        ->not->toContain('Dr(a).')
        ->not->toContain('Firma digital');
});
