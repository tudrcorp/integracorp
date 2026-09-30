<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\ViewTelemedicineCase;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;

uses(Tests\TestCase::class);

it('muestra el número del caso, su estado, paciente y médico', function (): void {
    $html = view('filament.telemedicina.cases.case-header', [
        'caseCode' => '66807-0253',
        'status' => 'EN SEGUIMIENTO',
        'priority' => 'Urgencia',
        'managedBy' => 'TDG',
        'patientName' => 'CINDY PAOLA BARRETO GUTIERREZ',
        'patientDetails' => ['V-26884430', '27 años'],
        'doctorName' => 'JENNIFER CAROLINA CASTRO PEREIRA',
        'reason' => 'Dolor abdominal',
        'openedAt' => '29/09/2026 07:48 PM',
        'openedAtHuman' => 'hace 1 hora',
        'consultationsCount' => 3,
    ])->render();

    expect($html)
        ->toContain('Caso N.º 66807-0253')
        ->toContain('EN SEGUIMIENTO')
        ->toContain('text-sky-700')
        ->toContain('Urgencia')
        ->toContain('CINDY PAOLA BARRETO GUTIERREZ')
        ->toContain('V-26884430 · 27 años')
        ->toContain('Dr(a). JENNIFER CAROLINA CASTRO PEREIRA')
        ->toContain('Dolor abdominal')
        ->toContain('hace 1 hora')
        ->toContain('dark:');
});

it('usa un color por estado y omite lo vacío', function (string $status, string $class): void {
    $html = view('filament.telemedicina.cases.case-header', ['caseCode' => 'X-1', 'status' => $status])->render();

    expect($html)->toContain($class)
        ->not->toContain('Médico:')
        ->not->toContain('Motivo:');
})->with([
    'alta' => ['ALTA MEDICA', 'text-emerald-700'],
    'asignado' => ['ASIGNADO', 'text-amber-700'],
    'otro' => ['RETAIL', 'text-gray-700'],
]);

it('arma los datos del paciente con respaldo en los datos del caso', function (): void {
    $case = (new TelemedicineCase)->forceFill(['patient_age' => 40, 'patient_sex' => 'MASCULINO']);
    $case->setRelation('telemedicinePatient', (new TelemedicinePatient)->forceFill(['nro_identificacion' => '123', 'age' => null, 'sex' => null]));

    expect(ViewTelemedicineCase::patientDetails($case))->toBe(['V-123', '40 años', 'MASCULINO']);
});
