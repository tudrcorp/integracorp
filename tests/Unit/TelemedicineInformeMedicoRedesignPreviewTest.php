<?php

declare(strict_types=1);

use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;

uses(Tests\TestCase::class);

/**
 * @return array<string, mixed>
 */
function telemedicineInformeRedesignSampleData(): array
{
    return [
        'fecha' => '25/08/2026',
        'code_reference' => 'TLM-2026-00418',
        'name_patient' => 'María Fernanda Rivas González',
        'ci_patient' => 'V-18.093.303',
        'age_patient' => '41 años',
        'reason' => 'Fiebre de 38,5 °C de 3 días de evolución, dolor de garganta, congestión nasal y malestar general. Niega dificultad respiratoria.',
        'actual_phatology' => 'Inicio súbito de odinofagia, rinorrea hialina y cefalea frontal. Automedicación con paracetamol 500 mg cada 8 horas, con alivio parcial. No ha presentado vómitos ni diarrea. Niega contacto con personas COVID positivas en los últimos 14 días.',
        'background' => 'Hipertensión arterial controlada con enalapril 10 mg/día. Alergia a penicilina (rash). Niega diabetes, asma, cirugías ni hospitalizaciones recientes. Esquema de vacunación incompleto para influenza 2026.',
        'diagnostic_impression' => 'Faringoamigdalitis aguda viral. Hipertensión arterial esencial en tratamiento. Descartar sobreinfección bacteriana si persiste fiebre > 72 horas.',
        'peso' => '68',
        'estatura' => '1.62',
        'imc' => '25.9',
        'pa' => '128/82 mmHg',
        'fc' => '86 lpm',
        'fr' => '18 rpm',
        'temp' => '38.2 °C',
        'saturacion' => '97 %',
        'medicationsArr' => [
            [
                'medicines' => 'Paracetamol 500 mg',
                'indications' => '1 tableta vía oral cada 8 horas por 5 días si hay fiebre o dolor.',
            ],
            [
                'medicines' => 'Loratadina 10 mg',
                'indications' => '1 tableta vía oral cada 24 horas por 5 días.',
            ],
        ],
        'labsArr' => [
            'Hematología completa',
            'Proteína C reactiva',
            'Glicemia en ayunas',
        ],
        'studiesArr' => [
            'Radiografía de tórax PA',
        ],
        'doctor_name' => 'Dra. Carolina Josefina Pinillo Lameda',
        'code_cm' => 'CM-24581',
        'code_mpps' => 'MPPS-11209',
        'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'telemedicine_case_id' => 418,
        'telemedicine_consultation_id' => 902,
        'telemedicine_patient_id' => 331,
    ];
}

function telemedicineInformePreviewDirectory(): string
{
    $directory = storage_path('app/public/telemedicine-informe-redesign-previews');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    return $directory;
}

/**
 * @param  array<string, mixed>  $data
 */
function telemedicineInformeRender(string $variant, array $data): string
{
    $view = $variant === 'largo'
        ? 'documents.informe-medico-largo'
        : 'documents.informe-medico-corto';

    return view($view, ['data' => $data])->render();
}

/**
 * @param  array<string, mixed>  $data
 */
function telemedicineInformeWritePdf(string $variant, array $data, string $filename): string
{
    $html = telemedicineInformeRender($variant, $data);
    $path = telemedicineInformePreviewDirectory().'/'.$filename;

    Pdf::loadHTML($html)
        ->setPaper('a4', 'portrait')
        ->save($path);

    return $path;
}

function telemedicineInformePdfPageCount(string $path): int
{
    $pdf = new Fpdi;
    $count = $pdf->setSourceFile($path);

    return (int) $count;
}

it('el informe médico corto de producción usa el diseño homologado', function (): void {
    $corto = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/informe-medico-corto.blade.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfInformeMedicoCorto.php');

    expect($corto)
        ->toContain('documents.partials.informe-medico-homologado')
        ->toContain("'variant' => 'corto'")
        ->and($corto)->not->toContain('informeMedicoTLM.png')
        ->and($job)->toContain('TelemedicineInformePdfRenderer::VIEW_CORTO');

    $html = telemedicineInformeRender('corto', telemedicineInformeRedesignSampleData());
    $path = telemedicineInformeWritePdf('corto', telemedicineInformeRedesignSampleData(), 'informe-medico-corto-redesign.pdf');

    expect($html)
        ->toContain('Informe Médico')
        ->toContain('#00ADEF')
        ->toContain('DejaVu Sans')
        ->toContain('Datos del paciente')
        ->toContain('María Fernanda Rivas González')
        ->toContain('TLM-2026-00418')
        ->toContain('Motivo de consulta')
        ->toContain('Enfermedad actual')
        ->toContain('Antecedentes')
        ->toContain('Medidas antropométricas')
        ->toContain('Impresión diagnóstica')
        ->toContain('Plan terapéutico')
        ->toContain('Paracetamol 500 mg')
        ->toContain('Laboratorios')
        ->toContain('TU DOCTOR EN CASA, C. A. RIF.: J-50358368-1')
        ->toContain('header-bar')
        ->toContain('header-rule-space')
        ->toContain('footer-fixed')
        ->not->toContain('doctor-signature')
        ->not->toContain('top: 222mm')
        // El nombre del médico ya no va en el HTML: lo estampa
        // TelemedicineInformeSignatureStamp (cubierto en su propio test).
        ->not->toContain('Dra. Carolina Josefina Pinillo Lameda')
        ->not->toContain('MPPS: MPPS-11209')
        ->not->toContain('Firma y sello del médico')
        ->and($html)->not->toContain('max-height: 58px')
        ->and($html)->not->toContain('Colegio médico')
        ->and($html)->not->toContain('informeMedicoTLM.png')
        ->and($html)->not->toContain('Signos vitales')
        ->and($html)->not->toContain('128/82 mmHg')
        ->and($html)->not->toContain('Informe médico largo (consulta inicial)')
        ->and($html)->not->toContain('Informe médico (consulta inicial)')
        ->and($html)->not->toContain('Tarjeta de Afiliado');

    expect($path)->toBeFile()
        ->and(filesize($path))->toBeGreaterThan(8_000)
        ->and(telemedicineInformePdfPageCount($path))->toBe(1);
});

it('el informe médico largo de producción usa el diseño homologado', function (): void {
    $largo = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/informe-medico-largo.blade.php');
    $generator = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineInformeLargoPdfGenerator.php');

    expect($largo)
        ->toContain('documents.partials.informe-medico-homologado')
        ->toContain("'variant' => 'largo'")
        ->and($largo)->not->toContain('informeMedicoTLM.png')
        ->and($generator)->toContain('TelemedicineInformePdfRenderer::VIEW_LARGO');

    $html = telemedicineInformeRender('largo', telemedicineInformeRedesignSampleData());
    $path = telemedicineInformeWritePdf('largo', telemedicineInformeRedesignSampleData(), 'informe-medico-largo-redesign.pdf');

    expect($html)
        ->toContain('Informe Médico')
        ->toContain('#00ADEF')
        ->toContain('DejaVu Sans')
        ->toContain('Signos vitales')
        ->toContain('Presión arterial')
        ->toContain('128/82 mmHg')
        ->toContain('38.2 °C')
        ->toContain('97 %')
        ->toContain('TU DOCTOR EN CASA, C. A. RIF.: J-50358368-1')
        ->toContain('header-rule-space')
        ->not->toContain('doctor-signature')
        ->not->toContain('top: 222mm')
        // El nombre del médico ya no va en el HTML: lo estampa
        // TelemedicineInformeSignatureStamp (cubierto en su propio test).
        ->not->toContain('Dra. Carolina Josefina Pinillo Lameda')
        ->not->toContain('MPPS: MPPS-11209')
        ->not->toContain('Firma y sello del médico')
        ->and($html)->not->toContain('max-height: 58px')
        ->and($html)->not->toContain('Colegio médico')
        ->and($html)->not->toContain('informeMedicoTLM.png')
        ->and($html)->not->toContain('Informe médico largo (consulta inicial)')
        ->and($html)->not->toContain('Informe médico (consulta inicial)')
        ->and($html)->not->toContain('Tarjeta de Afiliado');

    // Este informe estaba a ~4 mm de llenar la hoja. Al reservar la banda de la
    // firma (ver TelemedicineInformeSignatureStamp) pasa a dos páginas: es el
    // precio de que la firma no pueda quedarse sola en una hoja.
    expect($path)->toBeFile()
        ->and(filesize($path))->toBeGreaterThan(8_000)
        ->and(telemedicineInformePdfPageCount($path))->toBe(2);
});
