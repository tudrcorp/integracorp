<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineDoctorStamp;
use App\Support\Telemedicine\TelemedicineDocumentOrderItems;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;

it('separa cubiertos y no cubiertos cuando llegan las listas explícitas', function (): void {
    $labs = TelemedicineDocumentOrderItems::forDocument('laboratorios', [
        'labs' => ['Hematología completa', 'Glicemia en ayunas'],
        'other_labs' => ['Perfil hepático'],
    ]);

    expect($labs)->toHaveCount(3)
        ->and($labs[0])->toMatchArray(['label' => 'Hematología completa', 'coverage' => 'Cubierto'])
        ->and($labs[1])->toMatchArray(['label' => 'Glicemia en ayunas', 'coverage' => 'Cubierto'])
        ->and($labs[2])->toMatchArray(['label' => 'Perfil hepático', 'coverage' => 'No cubierto']);

    $studies = TelemedicineDocumentOrderItems::forDocument('imagenologia', [
        'studies' => ['Radiografía de tórax PA'],
        'other_studies' => ['Ecografía abdominal'],
    ]);

    expect($studies)->toHaveCount(2)
        ->and($studies[0]['coverage'])->toBe('Cubierto')
        ->and($studies[1]['coverage'])->toBe('No cubierto');

    $specialists = TelemedicineDocumentOrderItems::forDocument('especialista', [
        'consultSpecialistArr' => ['Otorrinolaringología'],
        'other_specialist' => ['Medicina interna'],
    ]);

    expect($specialists)->toHaveCount(2)
        ->and($specialists[0])->toMatchArray(['label' => 'Otorrinolaringología', 'coverage' => 'Cubierto'])
        ->and($specialists[1])->toMatchArray(['label' => 'Medicina interna', 'coverage' => 'No cubierto']);
});

it('marca todos cubiertos si other está vacío de forma explícita', function (): void {
    $items = TelemedicineDocumentOrderItems::forDocument('laboratorios', [
        'labs' => ['Hematología completa'],
        'other_labs' => [],
    ]);

    expect($items)->toHaveCount(1)
        ->and($items[0]['coverage'])->toBe('Cubierto');
});

it('acorta la etiqueta de cobertura del recipe para el pdf', function (): void {
    expect(TelemedicineMedicationCoverage::pdfCoverageLabelFromRow([
        'covered_medicines' => 'AMOXICILINA 500MG',
    ]))->toBe('Cubierto')
        ->and(TelemedicineMedicationCoverage::pdfCoverageLabelFromRow([
            'medicines' => 'IBUPROFENO 400MG',
        ]))->toBe('No cubierto')
        ->and(TelemedicineMedicationCoverage::pdfCoverageLabelFromRow([
            'coverage' => 'Cubierto (gestión Operaciones)',
        ]))->toBe('Cubierto')
        ->and(TelemedicineMedicationCoverage::pdfCoverageLabelFromRow([
            'is_covered' => true,
            'medicines' => 'PARACETAMOL 500MG',
        ]))->toBe('Cubierto');
});

it('el sello digital usa data uri y no inventa imagen vacía', function (): void {
    $dataUri = 'data:image/png;base64,abc';

    expect(TelemedicineDoctorStamp::dataUri(null))->toBe('')
        ->and(TelemedicineDoctorStamp::dataUri(''))->toBe('')
        ->and(TelemedicineDoctorStamp::dataUri($dataUri))->toBe($dataUri);
});

it('el sello digital escala sin recortar la firma', function (): void {
    $image = imagecreatetruecolor(200, 400);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, 199, 399, $white);
    ob_start();
    imagepng($image);
    $binary = (string) ob_get_clean();
    imagedestroy($image);

    $dataUri = 'data:image/png;base64,'.base64_encode($binary);
    $size = TelemedicineDoctorStamp::displaySize($dataUri, 128, 128);

    expect($size)->toBeArray()
        ->and($size['width'])->toBe(64)
        ->and($size['height'])->toBe(128)
        ->and(TelemedicineDoctorStamp::displaySize(null))->toBeNull()
        ->and(TelemedicineDoctorStamp::displaySize(''))->toBeNull();
});

it('create, edit, regeneración y plantillas publican cobertura y sello', function (): void {
    $create = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php'
    );
    $edit = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php'
    );
    $regeneration = file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseDocumentRegenerationService.php'
    );
    $recipe = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/documents/partials/telemedicine-recipe-homologado.blade.php'
    );
    $orden = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/documents/partials/telemedicine-orden-homologada.blade.php'
    );
    $stamp = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/documents/partials/telemedicine-doctor-stamp.blade.php'
    );

    expect($create)
        ->toContain("'other_labs' => \$otherLabsArr")
        ->toContain("'other_studies' => \$otherStudiesArr")
        ->toContain("'other_specialist' => \$otherSpecialistArr")
        ->toContain("'doctor_name' => \$doctor['full_name'] ?? null");

    expect($edit)
        ->toContain("'other_labs' => \$otherLabsArr")
        ->toContain("'other_studies' => \$otherStudiesArr")
        ->toContain("'other_specialist' => \$otherSpecialistArr")
        ->toContain("'doctor_name' => \$doctor['full_name'] ?? null");

    expect($regeneration)
        ->toContain('labsSplitForCase')
        ->toContain('other_labs')
        ->toContain('other_studies')
        ->toContain('other_specialist')
        ->toContain('TelemedicineMedicationCoverage::isCovered')
        ->toContain("'doctor_name' => \$doctor->full_name");

    expect($recipe)
        ->toContain("items-{{ \$isOriginal ? 'original' : 'copy' }}")
        ->toContain('doctor-signature')
        ->toContain('MPPS:')
        ->and($recipe)->not->toContain('Cobertura')
        ->and($recipe)->not->toContain('telemedicine-doctor-stamp')
        ->and($orden)->toContain('TelemedicineDocumentOrderItems::forDocument')
        ->and($orden)->toContain('Cobertura')
        ->and($orden)->toContain('doctor-signature')
        ->and($orden)->not->toContain('telemedicine-doctor-stamp')
        ->and($stamp)->toContain('Sello digital')
        ->and($stamp)->not->toContain("data['full_name']");
});
