<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;

uses(Tests\TestCase::class);

it('muestra el número del caso junto a los datos del paciente', function (): void {
    $html = view('filament.telemedicina.consultations.patient-header', [
        'patientName' => 'CINDY PAOLA BARRETO GUTIERREZ',
        'document' => 'V-26884430',
        'age' => 27,
        'sex' => 'FEMENINO',
        'caseCode' => '66807-0253',
        'caseStatus' => 'EN SEGUIMIENTO',
        'managedBy' => 'TDG',
    ])->render();

    expect($html)
        ->toContain('Caso N.º 66807-0253')
        ->toContain('CINDY PAOLA BARRETO GUTIERREZ')
        ->toContain('V-26884430')
        ->toContain('27 años')
        ->toContain('FEMENINO')
        ->toContain('EN SEGUIMIENTO')
        ->toContain('dark:')
        ->toMatch('/>\s*CG\s*</')
        ->not->toContain('Nombra y Apellido');
});

it('omite los datos vacíos sin romper el encabezado', function (): void {
    $html = view('filament.telemedicina.consultations.patient-header', [
        'patientName' => 'PACIENTE',
        'age' => 1,
    ])->render();

    expect($html)
        ->toContain('1 año')
        ->not->toContain('Caso N.º')
        ->not->toContain('Cédula:')
        ->not->toContain('Sexo:');
});

it('no duplica el prefijo de la cédula', function (mixed $input, ?string $expected): void {
    expect(CreateTelemedicineConsultationPatient::formatPatientDocument($input))->toBe($expected);
})->with([
    'solo número' => ['26884430', 'V-26884430'],
    'con V-' => ['V-26884430', 'V-26884430'],
    'extranjero' => ['e-81234567', 'E-81234567'],
    'sin guion' => ['V26884430', 'V26884430'],
    'vacío' => ['', null],
    'nulo' => [null, null],
]);

it('la página separa el título de la pestaña del encabezado visual y no lee el paciente de otra pestaña', function (): void {
    $source = file_get_contents((new ReflectionClass(CreateTelemedicineConsultationPatient::class))->getFileName());

    expect($source)
        ->toContain('public function getHeading(): string|Htmlable')
        ->toContain("'caseCode' => \$this->case?->code")
        ->toContain('private function headerPatient(): ?TelemedicinePatient')
        ->not->toContain('Nombra y Apellido');
});
