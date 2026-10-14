<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineFollowUpReportDocument;
use App\Support\Telemedicine\TelemedicineInformeClinicalOrders;
use App\Support\Telemedicine\TelemedicineInformePdfRenderer;

uses(Tests\TestCase::class);

/**
 * @return array<string, mixed>
 */
function telemedicineParaclinicosBaseData(): array
{
    return [
        'fecha' => '12/10/2026',
        'code_reference' => 'TLM-2026-00418',
        'name_patient' => 'María Fernanda Rivas González',
        'ci_patient' => '18456721',
        'age_patient' => 34,
        'diagnostic_impression' => 'Faringoamigdalitis aguda.',
        'current_illness_history' => 'Persiste odinofagia leve.',
        'patient_evolution' => 'Afebril, mejoría clínica.',
        'doctor_name' => 'Dra. Carolina Pinillo',
        'code_cm' => 'CM-24581',
        'code_mpps' => 'MPPS-11209',
        'signature' => null,
    ];
}

/**
 * @param  array<string, mixed>  $data
 */
function telemedicineParaclinicosHtml(string $view, array $data): string
{
    return view($view, ['data' => $data])->render();
}

it('junta lo cubierto y lo no cubierto, sin vacíos ni repetidos', function (): void {
    $orders = TelemedicineInformeClinicalOrders::forReport([
        'medicationsArr' => [
            ['medicines' => 'AMOXICILINA 500 MG', 'indications' => '1 CADA 8 HORAS'],
            ['medicines' => '', 'indications' => ''],
            'not-an-array-row',
        ],
        'labsArr' => ['HEMATOLOGÍA COMPLETA', 'CREATININA', ''],
        'otherLabsArr' => ['creatinina', 'PCR'],
        'studiesArr' => [['study' => 'RX DE TÓRAX']],
        'otherStudiesArr' => ['ECO ABDOMINAL', null],
        'consultSpecialistArr' => ['CARDIOLOGÍA'],
        'otherSpecialistArr' => [['specialty' => 'OTORRINOLARINGOLOGÍA'], ['nada' => 'x']],
    ]);

    expect($orders['medications'])->toBe([
        ['medicines' => 'AMOXICILINA 500 MG', 'indications' => '1 CADA 8 HORAS'],
        ['medicines' => 'not-an-array-row', 'indications' => ''],
    ])
        ->and($orders['labs'])->toBe(['HEMATOLOGÍA COMPLETA', 'CREATININA', 'PCR'])
        ->and($orders['studies'])->toBe(['RX DE TÓRAX', 'ECO ABDOMINAL'])
        ->and($orders['specialists'])->toBe(['CARDIOLOGÍA', 'OTORRINOLARINGOLOGÍA']);
});

it('un payload sin indicaciones da listas vacías, no errores', function (): void {
    expect(TelemedicineInformeClinicalOrders::forReport(['labsArr' => 'texto suelto']))->toBe([
        'medications' => [],
        'labs' => [],
        'studies' => [],
        'specialists' => [],
    ]);
});

it('el payload del seguimiento lleva lo que el médico indicó al crear la consulta', function (): void {
    $payload = TelemedicineFollowUpReportDocument::payloadFromCreateData(
        ['id' => 30, 'telemedicine_case_id' => 9, 'telemedicine_patient_id' => 7, 'code_reference' => 'REF-30'],
        ['diagnostic_impression' => 'FARINGITIS', 'nro_identificacion' => '123'],
        ['full_name' => 'ANGEL VALERIO', 'code_cm' => '1', 'code_mpps' => '2', 'signature' => null],
        'PACIENTE',
        [
            'medicationsArr' => [['medicines' => 'IBUPROFENO', 'indications' => 'CADA 8H']],
            'labsArr' => ['PCR'],
            'otherSpecialistArr' => ['NEUMONOLOGÍA'],
            'clave_ajena' => 'no viaja',
        ],
    );

    expect($payload)
        ->toHaveKeys(TelemedicineInformeClinicalOrders::PAYLOAD_KEYS)
        ->not->toHaveKey('clave_ajena')
        ->and($payload['medicationsArr'])->toBe([['medicines' => 'IBUPROFENO', 'indications' => 'CADA 8H']])
        ->and($payload['labsArr'])->toBe(['PCR'])
        ->and($payload['otherLabsArr'])->toBe([])
        ->and($payload['otherSpecialistArr'])->toBe(['NEUMONOLOGÍA']);
});

it('el informe de seguimiento imprime plan terapéutico y paraclínicos con especialistas', function (): void {
    $html = telemedicineParaclinicosHtml(TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO, [
        ...telemedicineParaclinicosBaseData(),
        'medicationsArr' => [['medicines' => 'IBUPROFENO 400 MG', 'indications' => '1 TABLETA CADA 8 HORAS']],
        'labsArr' => ['PROTEÍNA C REACTIVA'],
        'otherLabsArr' => ['VSG'],
        'studiesArr' => [],
        'otherStudiesArr' => ['RX DE CAVUM'],
        'consultSpecialistArr' => ['OTORRINOLARINGOLOGÍA'],
        'otherSpecialistArr' => [],
    ]);

    expect($html)
        ->toContain('Evolución del paciente')
        ->toContain('Plan terapéutico')
        ->toContain('IBUPROFENO 400 MG')
        ->toContain('1 TABLETA CADA 8 HORAS')
        ->toContain('Paraclínicos')
        ->toContain('PROTEÍNA C REACTIVA')
        ->toContain('VSG')
        ->toContain('RX DE CAVUM')
        ->toContain('Especialistas')
        ->toContain('OTORRINOLARINGOLOGÍA')
        // Sigue siendo el informe de seguimiento: no arrastra secciones de la consulta inicial.
        ->not->toContain('Motivo de consulta')
        ->not->toContain('Impresión diagnóstica');

    // Las indicaciones van después de la evolución.
    expect(strpos($html, 'Plan terapéutico'))->toBeGreaterThan(strpos($html, 'Evolución del paciente'));
});

it('un seguimiento sin indicaciones lo dice en cada bloque', function (): void {
    $html = telemedicineParaclinicosHtml(
        TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO,
        telemedicineParaclinicosBaseData(),
    );

    expect($html)
        ->toContain('Sin medicamentos indicados.')
        ->toContain('Sin laboratorios indicados.')
        ->toContain('Sin estudios indicados.')
        ->toContain('Sin consulta con especialistas.');
});

it('el informe médico también imprime especialistas y lo no cubierto', function (): void {
    $html = telemedicineParaclinicosHtml(TelemedicineInformePdfRenderer::VIEW_LARGO, [
        ...telemedicineParaclinicosBaseData(),
        'reason' => 'FIEBRE',
        'labsArr' => ['HEMATOLOGÍA COMPLETA'],
        'otherLabsArr' => ['FERRITINA'],
        'otherStudiesArr' => ['ECO TIROIDEO'],
        'consultSpecialistArr' => ['CARDIOLOGÍA'],
        'otherSpecialistArr' => ['ENDOCRINOLOGÍA'],
    ]);

    expect($html)
        ->toContain('Impresión diagnóstica')
        ->toContain('FERRITINA')
        ->toContain('ECO TIROIDEO')
        ->toContain('CARDIOLOGÍA')
        ->toContain('ENDOCRINOLOGÍA');
});
