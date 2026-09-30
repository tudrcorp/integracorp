<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineDoctors\Pages\ViewTelemedicineDoctor;
use App\Models\TelemedicineDoctor;

uses(Tests\TestCase::class);

it('muestra el perfil del médico con sus datos profesionales', function (): void {
    $html = view('filament.telemedicina.doctors.doctor-header', [
        'fullName' => 'JENNIFER CAROLINA CASTRO PEREIRA',
        'specialty' => 'MEDICINA GENERAL',
        'status' => 'ACTIVO',
        'consultationsCount' => 1250,
        'details' => [
            ['label' => 'CM', 'value' => '12345'],
            ['label' => 'MPPS', 'value' => '67890'],
        ],
    ])->render();

    expect($html)
        ->toContain('Perfil del médico')
        ->toContain('Dr(a). JENNIFER CAROLINA CASTRO PEREIRA')
        ->toContain('MEDICINA GENERAL')
        ->toContain('ACTIVO')
        ->toContain('1.250 consultas')
        ->toContain('MPPS:')
        ->toMatch('/>\s*JP\s*</')
        ->toContain('dark:')
        ->not->toContain('<img');
});

it('usa la foto cuando existe y omite lo vacío', function (): void {
    $html = view('filament.telemedicina.doctors.doctor-header', [
        'fullName' => 'ANA',
        'photoUrl' => 'https://example.test/foto.png',
        'consultationsCount' => 1,
    ])->render();

    expect($html)
        ->toContain('src="https://example.test/foto.png"')
        ->toContain('1 consulta')
        ->not->toContain('<dl');
});

it('arma los datos del médico solo con los campos que tienen valor', function (): void {
    $doctor = (new TelemedicineDoctor)->forceFill([
        'nro_identificacion' => 'V-123',
        'code_cm' => 'CM-1',
        'code_mpps' => null,
        'managed_by' => 'TDG',
    ]);
    $doctor->setRelation('supplier', null);

    expect(ViewTelemedicineDoctor::details($doctor))->toBe([
        ['label' => 'Cédula', 'value' => 'V-123'],
        ['label' => 'CM', 'value' => 'CM-1'],
        ['label' => 'Gestiona', 'value' => 'TDG'],
    ])
        ->and(ViewTelemedicineDoctor::photoUrl(''))->toBeNull()
        ->and(ViewTelemedicineDoctor::photoUrl(null))->toBeNull()
        ->and(ViewTelemedicineDoctor::photoUrl('doctors/foto.png'))->toContain('doctors/foto.png');
});
