<?php

declare(strict_types=1);

use App\Models\AnotherAddress;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

function nuevaUbicacionDePrueba(array $overrides = []): AnotherAddress
{
    $address = new AnotherAddress;
    $address->fill(array_merge([
        'telemedicine_patient_id' => 999_999_999,
        'address' => 'AV. PRUEBA, EDIFICIO PEST',
        'city_id' => 4,
        'state_id' => 2,
        'country_id' => 189,
        'phone_1' => '04161234567',
        'phone_2' => '04141234567',
        'ambulanceParking' => true,
        'relationship' => 'HIJO(A)',
    ], $overrides));
    $address->save();

    return $address->fresh();
}

it('guarda una nueva ubicación con estacionamiento y parentesco', function (): void {
    $address = nuevaUbicacionDePrueba();

    expect($address->ambulanceParking)->toBeTrue()
        ->and($address->relationship)->toBe('HIJO(A)');
});

it('guarda una nueva ubicación sin teléfono alternativo ni parentesco', function (): void {
    $address = nuevaUbicacionDePrueba([
        'phone_2' => null,
        'relationship' => null,
        'ambulanceParking' => false,
    ]);

    expect($address->phone_2)->toBeNull()
        ->and($address->relationship)->toBeNull()
        ->and($address->ambulanceParking)->toBeFalse();
});

it('la asignación con nueva ubicación guarda ubicación y caso en una sola transacción', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Actions/AssignDoctorAction.php');

    expect($contents)
        ->toContain('[$address, $case] = DB::transaction(function () use ($record, $data, $doctor): array {')
        ->toContain("filled(\$data['phone_2'] ?? null) ? \$data['phone_2'] : null")
        ->not->toContain('->first()->ambulanceParking');
});

it('la ficha del paciente agrupa sus acciones en el menú «Acciones»', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php');

    expect($contents)
        ->toContain('FilamentIosActionsMenu::make([')
        ->toContain('AssignDoctorAction::make(),')
        ->toContain('RegisterTpaRetailServicesAction::make(),')
        ->toContain('ReportSiniestralidadAction::make()')
        ->toContain("->label('Editar Paciente')");
});
