<?php

declare(strict_types=1);

use Barryvdh\DomPDF\Facade\Pdf;

uses(Tests\TestCase::class);

/**
 * @return array<string, mixed>
 */
function telemedicineOrdenRedesignSampleData(): array
{
    return [
        'fecha' => '25/08/2026',
        'code_reference' => 'TLM-2026-00418',
        'name_patiente' => 'María Fernanda Rivas González',
        'ci_patiente' => 'V-18.093.303',
        'age_patiente' => '41',
        'medicationsArr' => [
            [
                'medicines' => 'Paracetamol 500 mg',
                'indications' => '1 tableta vía oral cada 8 horas por 5 días si hay fiebre o dolor.',
            ],
            [
                'medicines' => 'Loratadina 10 mg',
                'indications' => '1 tableta vía oral cada 24 horas por 5 días.',
            ],
            [
                'medicines' => 'Omeprazol 20 mg',
                'indications' => '1 cápsula vía oral en ayunas por 7 días.',
            ],
        ],
        'labs' => [
            'Hematología completa',
            'Proteína C reactiva',
            'Glicemia en ayunas',
            'Perfil hepático',
        ],
        'studies' => [
            'Radiografía de tórax PA',
            'Ecografía abdominal',
        ],
        'consultSpecialistArr' => [
            'Otorrinolaringología',
            'Medicina interna',
        ],
        'code_cm' => 'CM-24581',
        'code_mpps' => 'MPPS-11209',
        'telemedicine_case_id' => 418,
        'telemedicine_consultation_id' => 902,
        'telemedicine_patient_id' => 331,
    ];
}

function telemedicineOrdenRedesignPreviewDirectory(): string
{
    $directory = storage_path('app/public/telemedicine-ordenes-redesign-previews');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    return $directory;
}

/**
 * @param  array<string, mixed>  $data
 */
function telemedicineOrdenRedesignWritePdf(string $html, string $filename, string $orientation = 'portrait'): string
{
    $path = telemedicineOrdenRedesignPreviewDirectory().'/'.$filename;

    Pdf::loadHTML($html)
        ->setPaper('a4', $orientation)
        ->save($path);

    return $path;
}

it('el recipe de medicamentos de producción usa el diseño homologado en horizontal', function (): void {
    $production = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/medicamentos.blade.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfMedicamentos.php');

    expect($production)
        ->toContain('documents.partials.telemedicine-recipe-homologado')
        ->and($production)->not->toContain('medicamentos.png')
        ->and($job)->toContain("loadView('documents.medicamentos'")
        ->and($job)->toContain("setPaper('a4', 'landscape')");

    $html = view('documents.medicamentos', [
        'data' => telemedicineOrdenRedesignSampleData(),
    ])->render();
    $path = telemedicineOrdenRedesignWritePdf($html, 'recipe-medicamentos-redesign.pdf', 'landscape');

    expect($html)
        ->toContain('Recipe de medicamentos')
        ->toContain('A4 landscape')
        ->toContain('#00ADEF')
        ->toContain('DejaVu Sans')
        ->toContain('header-rule-space')
        ->toContain('Original')
        ->toContain('Copia')
        ->toContain('Paracetamol 500 mg')
        ->toContain('Loratadina 10 mg')
        ->toContain('Omeprazol 20 mg')
        ->toContain('CM-24581')
        ->toContain('departamento de telemedicina de Tu Doctor en Casa')
        ->and($html)->not->toContain('medicamentos.png');

    expect($path)->toBeFile()
        ->and(filesize($path))->toBeGreaterThan(8_000);
});

it('la orden de laboratorios de producción usa el diseño homologado', function (): void {
    $production = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/laboratorios.blade.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfLaboratorio.php');

    expect($production)
        ->toContain('documents.partials.telemedicine-orden-homologada')
        ->toContain("'docType' => 'laboratorios'")
        ->and($production)->not->toContain('paraclinicos-laboratorios.png')
        ->and($job)->toContain("loadView('documents.laboratorios'")
        ->and($job)->toContain("setPaper('a4', 'portrait')");

    $html = view('documents.laboratorios', [
        'data' => telemedicineOrdenRedesignSampleData(),
    ])->render();
    $path = telemedicineOrdenRedesignWritePdf($html, 'orden-laboratorios-redesign.pdf');

    expect($html)
        ->toContain('Orden de laboratorios')
        ->toContain('#00ADEF')
        ->toContain('header-rule-space')
        ->toContain('Hematología completa')
        ->toContain('Perfil hepático')
        ->toContain('A4 portrait')
        ->toContain('departamento de telemedicina de Tu Doctor en Casa')
        ->and($html)->not->toContain('paraclinicos-laboratorios.png')
        ->and($html)->not->toContain('Recipe de medicamentos')
        ->and($html)->not->toContain('>N°<');

    expect($path)->toBeFile()
        ->and(filesize($path))->toBeGreaterThan(8_000);
});

it('la orden de estudios e imagenología de producción usa el diseño homologado', function (): void {
    $production = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/imagenologia.blade.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfImagenologia.php');

    expect($production)
        ->toContain('documents.partials.telemedicine-orden-homologada')
        ->toContain("'docType' => 'imagenologia'")
        ->and($production)->not->toContain('paraclinicos-imagenologia.png')
        ->and($job)->toContain("loadView('documents.imagenologia'")
        ->and($job)->toContain("setPaper('a4', 'portrait')");

    $html = view('documents.imagenologia', [
        'data' => telemedicineOrdenRedesignSampleData(),
    ])->render();
    $path = telemedicineOrdenRedesignWritePdf($html, 'orden-imagenologia-redesign.pdf');

    expect($html)
        ->toContain('Orden de estudios / imagenología')
        ->toContain('header-rule-space')
        ->toContain('Radiografía de tórax PA')
        ->toContain('Ecografía abdominal')
        ->toContain('#00ADEF')
        ->and($html)->not->toContain('paraclinicos-imagenologia.png')
        ->and($html)->not->toContain('Hematología completa')
        ->and($html)->not->toContain('>N°<');

    expect($path)->toBeFile()
        ->and(filesize($path))->toBeGreaterThan(8_000);
});

it('la referencia a especialistas de producción usa el diseño homologado', function (): void {
    $production = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/especialista.blade.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfEspecialista.php');

    expect($production)
        ->toContain('documents.partials.telemedicine-orden-homologada')
        ->toContain("'docType' => 'especialista'")
        ->and($production)->not->toContain('especialista.png')
        ->and($job)->toContain("loadView('documents.especialista'")
        ->and($job)->toContain("setPaper('a4', 'portrait')");

    $html = view('documents.especialista', [
        'data' => telemedicineOrdenRedesignSampleData(),
    ])->render();
    $path = telemedicineOrdenRedesignWritePdf($html, 'referencia-especialistas-redesign.pdf');

    expect($html)
        ->toContain('Referencia a especialistas')
        ->toContain('header-rule-space')
        ->toContain('Otorrinolaringología')
        ->toContain('Medicina interna')
        ->toContain('#00ADEF')
        ->and($html)->not->toContain('especialista.png')
        ->and($html)->not->toContain('Radiografía de tórax PA')
        ->and($html)->not->toContain('>N°<');

    expect($path)->toBeFile()
        ->and(filesize($path))->toBeGreaterThan(8_000);
});
