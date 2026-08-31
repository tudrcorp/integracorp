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
                'medicines' => null,
                'covered_medicines' => 'Paracetamol 500 mg',
                'indications' => '1 tableta vía oral cada 8 horas por 5 días si hay fiebre o dolor.',
            ],
            [
                'medicines' => 'Loratadina 10 mg',
                'indications' => '1 tableta vía oral cada 24 horas por 5 días.',
            ],
            [
                'medicines' => null,
                'covered_medicines' => 'Omeprazol 20 mg',
                'indications' => '1 cápsula vía oral en ayunas por 7 días.',
            ],
        ],
        'labs' => [
            'Hematología completa',
            'Proteína C reactiva',
            'Glicemia en ayunas',
        ],
        'other_labs' => [
            'Perfil hepático',
        ],
        'studies' => [
            'Radiografía de tórax PA',
        ],
        'other_studies' => [
            'Ecografía abdominal',
        ],
        'consultSpecialistArr' => [
            'Otorrinolaringología',
        ],
        'other_specialist' => [
            'Medicina interna',
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
        ->and($job)->toContain("'documents.medicamentos'")
        ->and($job)->toContain("'landscape'");

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
        ->toContain('Indicaciones')
        ->toContain('1 tableta vía oral cada 8 horas por 5 días si hay fiebre o dolor.')
        ->toContain('items-original')
        ->toContain('items-copy')
        ->toContain('doctor-signature-original')
        ->toContain('doctor-signature-copy')
        ->toContain('top: 96mm')
        ->toContain('Dra. Carolina Josefina Pinillo Lameda')
        ->toContain('MPPS: MPPS-11209')
        ->toContain('Firma y sello del médico')
        ->toContain('departamento de telemedicina de Tu Doctor en Casa')
        ->and($html)->not->toContain('medicamentos.png')
        ->and($html)->not->toContain('Cobertura')
        ->and($html)->not->toContain('Cubierto')
        ->and($html)->not->toContain('No cubierto')
        ->and($html)->not->toContain('Colegio médico')
        ->and($html)->not->toContain('Sello digital')
        ->and($html)->not->toContain('telemedicine-doctor-stamp');

    preg_match('/class="items items-original".*?<\/thead>/s', $html, $originalHead);
    preg_match('/class="items items-copy".*?<\/thead>/s', $html, $copyHead);

    expect($originalHead[0] ?? '')
        ->toContain('Medicamento')
        ->and($originalHead[0] ?? '')->not->toContain('Indicaciones')
        ->and($originalHead[0] ?? '')->not->toContain('Cobertura');

    expect($copyHead[0] ?? '')
        ->toContain('Medicamento')
        ->toContain('Indicaciones')
        ->and($copyHead[0] ?? '')->not->toContain('Cobertura');

    expect($path)->toBeFile()
        ->and(filesize($path))->toBeGreaterThan(8_000);
});

it('el recipe muestra la etiqueta de cobertura cuando el job parte el documento', function (): void {
    $data = telemedicineOrdenRedesignSampleData();
    $data['coverage_group'] = 'Cubiertos';

    $html = view('documents.medicamentos', [
        'data' => $data,
    ])->render();

    expect($html)->toContain('Cubiertos');
});

it('la orden de laboratorios de producción usa el diseño homologado', function (): void {
    $production = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/laboratorios.blade.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfLaboratorio.php');

    expect($production)
        ->toContain('documents.partials.telemedicine-orden-homologada')
        ->toContain("'docType' => 'laboratorios'")
        ->and($production)->not->toContain('paraclinicos-laboratorios.png')
        ->and($job)->toContain("'documents.laboratorios'")
        ->and($job)->toContain("'portrait'");

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
        ->toContain('Cubierto')
        ->toContain('No cubierto')
        ->toContain('Cobertura')
        ->toContain('doctor-signature')
        ->toContain('top: 222mm')
        ->toContain('Firma y sello del médico')
        ->toContain('Dra. Carolina Josefina Pinillo Lameda')
        ->toContain('MPPS: MPPS-11209')
        ->toContain('A4 portrait')
        ->toContain('departamento de telemedicina de Tu Doctor en Casa')
        ->and($html)->not->toContain('paraclinicos-laboratorios.png')
        ->and($html)->not->toContain('Recipe de medicamentos')
        ->and($html)->not->toContain('>N°<')
        ->and($html)->not->toContain('Sello digital')
        ->and($html)->not->toContain('Colegio médico')
        ->and($html)->not->toContain('Médico tratante');

    expect($path)->toBeFile()
        ->and(filesize($path))->toBeGreaterThan(8_000);
});

it('la orden de laboratorios muestra la etiqueta de cobertura cuando el job parte el documento', function (): void {
    $data = telemedicineOrdenRedesignSampleData();
    $data['coverage_group'] = 'No cubiertos';

    $html = view('documents.laboratorios', [
        'data' => $data,
    ])->render();

    expect($html)->toContain('No cubiertos');
});

it('la orden de estudios e imagenología de producción usa el diseño homologado', function (): void {
    $production = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/imagenologia.blade.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfImagenologia.php');

    expect($production)
        ->toContain('documents.partials.telemedicine-orden-homologada')
        ->toContain("'docType' => 'imagenologia'")
        ->and($production)->not->toContain('paraclinicos-imagenologia.png')
        ->and($job)->toContain("'documents.imagenologia'")
        ->and($job)->toContain("'portrait'");

    $html = view('documents.imagenologia', [
        'data' => telemedicineOrdenRedesignSampleData(),
    ])->render();
    $path = telemedicineOrdenRedesignWritePdf($html, 'orden-imagenologia-redesign.pdf');

    expect($html)
        ->toContain('Orden de estudios / imagenología')
        ->toContain('header-rule-space')
        ->toContain('Radiografía de tórax PA')
        ->toContain('Ecografía abdominal')
        ->toContain('Cubierto')
        ->toContain('No cubierto')
        ->toContain('doctor-signature')
        ->toContain('top: 222mm')
        ->toContain('Firma y sello del médico')
        ->toContain('Dra. Carolina Josefina Pinillo Lameda')
        ->toContain('MPPS: MPPS-11209')
        ->toContain('#00ADEF')
        ->and($html)->not->toContain('paraclinicos-imagenologia.png')
        ->and($html)->not->toContain('Hematología completa')
        ->and($html)->not->toContain('>N°<')
        ->and($html)->not->toContain('Sello digital')
        ->and($html)->not->toContain('Colegio médico')
        ->and($html)->not->toContain('Médico tratante');

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
        ->and($job)->toContain("'documents.especialista'")
        ->and($job)->toContain("'portrait'");

    $html = view('documents.especialista', [
        'data' => telemedicineOrdenRedesignSampleData(),
    ])->render();
    $path = telemedicineOrdenRedesignWritePdf($html, 'referencia-especialistas-redesign.pdf');

    expect($html)
        ->toContain('Referencia a especialistas')
        ->toContain('header-rule-space')
        ->toContain('Otorrinolaringología')
        ->toContain('Medicina interna')
        ->toContain('Cubierto')
        ->toContain('No cubierto')
        ->toContain('doctor-signature')
        ->toContain('top: 222mm')
        ->toContain('Firma y sello del médico')
        ->toContain('Dra. Carolina Josefina Pinillo Lameda')
        ->toContain('MPPS: MPPS-11209')
        ->toContain('#00ADEF')
        ->and($html)->not->toContain('especialista.png')
        ->and($html)->not->toContain('Radiografía de tórax PA')
        ->and($html)->not->toContain('>N°<')
        ->and($html)->not->toContain('Sello digital')
        ->and($html)->not->toContain('Colegio médico')
        ->and($html)->not->toContain('Médico tratante');

    expect($path)->toBeFile()
        ->and(filesize($path))->toBeGreaterThan(8_000);
});

it('omite el recuadro del sello cuando el médico no tiene imagen', function (): void {
    $data = telemedicineOrdenRedesignSampleData();
    unset($data['signature']);

    $html = view('documents.laboratorios', [
        'data' => $data,
    ])->render();

    expect($html)
        ->toContain('Hematología completa')
        ->toContain('doctor-signature')
        ->toContain('Dra. Carolina Josefina Pinillo Lameda')
        ->toContain('MPPS: MPPS-11209')
        ->and($html)->not->toContain('Sello digital')
        ->and($html)->not->toContain('Colegio médico')
        ->and($html)->not->toContain('Médico tratante')
        ->and($html)->not->toContain('alt="Firma y sello del médico"');
});
