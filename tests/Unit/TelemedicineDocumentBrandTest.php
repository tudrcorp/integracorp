<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\TelemedicinePatient;
use App\Models\WhiteCompany;
use App\Support\Telemedicine\TelemedicineDocumentBrand;
use App\Support\WhiteCompanies\WhiteCompanyOwnership;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/** PNG cuadrado (el caso que estiraría la cabecera si el logo se dimensionara solo por ancho). */
function telemedicineBrandSquareLogoPng(): string
{
    $image = imagecreatetruecolor(300, 300);
    imagefill($image, 0, 0, imagecolorallocate($image, 203, 97, 250));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function telemedicineBrandAlliedCompany(?string $color = '#cb61fa', bool $withLogo = true): WhiteCompany
{
    Storage::fake('public');

    if ($withLogo) {
        Storage::disk('public')->put('logo-empresa/aliada-test.png', telemedicineBrandSquareLogoPng());
    }

    return new WhiteCompany([
        'name' => 'Aliada de Prueba',
        'brand_primary_color' => $color,
        'logo' => $withLogo ? 'logo-empresa/aliada-test.png' : 'logo-empresa/no-existe.png',
    ]);
}

/**
 * @return array<string, mixed>
 */
function telemedicineBrandSampleData(): array
{
    return [
        'fecha' => '25/10/2026',
        'code_reference' => 'TLM-2026-00418',
        'name_patient' => 'María Fernanda Rivas',
        'name_patiente' => 'María Fernanda Rivas',
        'ci_patient' => 'V-18.093.303',
        'ci_patiente' => 'V-18.093.303',
        'age_patient' => '41',
        'age_patiente' => '41',
        'labs' => ['Hematología completa'],
        'studies' => ['Radiografía de tórax PA'],
        'consultSpecialistArr' => ['Otorrinolaringología'],
        'medicationsArr' => [['medicines' => 'Loratadina 10 mg', 'indications' => '1 tableta cada 24 horas.']],
        'doctor_name' => 'Dra. Carolina Pinillo',
        'code_mpps' => 'MPPS-11209',
    ];
}

it('conserva exactamente la paleta y el logo de TDEC cuando el paciente no es de una aliada', function (): void {
    $brand = TelemedicineDocumentBrand::default();

    expect($brand->isAllied())->toBeFalse()
        ->and($brand->primary)->toBe('#00ADEF')
        ->and($brand->border)->toBe('#0090c7')
        ->and($brand->deep)->toBe('#0c4a6e')
        ->and($brand->tint)->toBe('#f0fdff')
        ->and($brand->name())->toBe('Tu Doctor en Casa')
        ->and($brand->printsAlliedLogo())->toBeFalse()
        ->and($brand->logoDataUri())->toStartWith('data:image/png;base64,');
});

it('deriva toda la paleta del color principal de la aliada', function (): void {
    $brand = TelemedicineDocumentBrand::fromCompany(telemedicineBrandAlliedCompany('#CB61FA'));

    expect($brand->isAllied())->toBeTrue()
        ->and($brand->primary)->toBe('#cb61fa')
        ->and($brand->border)->toBe('#a851d0')
        ->and($brand->deep)->toBe('#592b6e')
        ->and($brand->tint)->toBe('#fcf6ff')
        ->and($brand->name())->toBe('Aliada de Prueba');
});

it('oscurece un color demasiado claro para que el texto blanco de las cabeceras se lea', function (): void {
    $brand = TelemedicineDocumentBrand::fromCompany(telemedicineBrandAlliedCompany('#ffee00'));

    expect($brand->primary)->not->toBe('#ffee00')
        ->and(TelemedicineDocumentBrand::contrastOnWhite($brand->primary))
        ->toBeGreaterThanOrEqual(TelemedicineDocumentBrand::MIN_CONTRAST_ON_WHITE);
});

it('usa la paleta de TDEC si la aliada no tiene un color válido, sin dejar de ser aliada', function (?string $color): void {
    $brand = TelemedicineDocumentBrand::fromCompany(telemedicineBrandAlliedCompany($color));

    expect($brand->isAllied())->toBeTrue()
        ->and($brand->primary)->toBe('#00ADEF')
        ->and($brand->tint)->toBe('#f0fdff');
})->with([
    'nulo' => [null],
    'vacío' => [''],
    'basura' => ['morado'],
]);

it('imprime el logo de la aliada y cae al de TDEC si su archivo no existe', function (): void {
    $withoutLogo = TelemedicineDocumentBrand::fromCompany(telemedicineBrandAlliedCompany(withLogo: false));
    $withLogo = TelemedicineDocumentBrand::fromCompany(telemedicineBrandAlliedCompany());

    expect($withLogo->printsAlliedLogo())->toBeTrue()
        ->and($withLogo->logoDataUri())->not->toBe(TelemedicineDocumentBrand::default()->logoDataUri())
        ->and($withoutLogo->printsAlliedLogo())->toBeFalse()
        ->and($withoutLogo->logoDataUri())->toBe(TelemedicineDocumentBrand::default()->logoDataUri());
});

it('resuelve la marca de TDEC cuando el payload no identifica a un paciente', function (array $data): void {
    expect(TelemedicineDocumentBrand::forData($data)->isAllied())->toBeFalse();
})->with([
    'sin paciente' => [[]],
    'paciente inexistente' => [['telemedicine_patient_id' => 999_999_999]],
    'id basura' => [['telemedicine_patient_id' => 'abc']],
]);

it('resuelve la aliada de un paciente real por su afiliación', function (): void {
    $patient = TelemedicinePatient::query()
        ->select(['id', 'afilliation_id', 'afilliation_corporate_id'])
        ->whereIn('afilliation_id', Affiliation::query()->whereNotNull('white_company_id')->select('id'))
        ->first();

    if ($patient === null) {
        $this->markTestSkipped('No hay pacientes de telemedicina vinculados a una afiliación de aliada.');
    }

    $expected = WhiteCompanyOwnership::forAffiliation(Affiliation::query()->findOrFail($patient->afilliation_id));
    $brand = TelemedicineDocumentBrand::forData(['telemedicine_patient_id' => $patient->id]);

    expect($expected)->not->toBeNull()
        ->and($brand->isAllied())->toBeTrue()
        ->and($brand->company?->getKey())->toBe($expected->getKey());
});

it('pinta cada documento clínico con la marca de la aliada', function (string $view): void {
    $brand = TelemedicineDocumentBrand::fromCompany(telemedicineBrandAlliedCompany());

    $html = view($view, ['data' => telemedicineBrandSampleData(), 'brand' => $brand])->render();

    expect($html)->toContain('#cb61fa')
        ->and($html)->toContain('#fcf6ff')
        ->and($html)->toContain('alt="Aliada de Prueba"')
        ->and($html)->toContain('Servicio médico de TU DOCTOR EN CASA')
        ->and($html)->not->toContain('#00ADEF')
        ->and($html)->not->toContain('#f0fdff');
})->with([
    'informe médico' => ['documents.informe-medico-largo'],
    'informe de seguimiento' => ['documents.informe-seguimiento'],
    'récipe' => ['documents.medicamentos'],
    'laboratorio' => ['documents.laboratorios'],
    'imagenología' => ['documents.imagenologia'],
    'especialista' => ['documents.especialista'],
]);

it('deja cada documento clínico igual que antes para pacientes de TDEC', function (string $view): void {
    $html = view($view, ['data' => telemedicineBrandSampleData(), 'brand' => TelemedicineDocumentBrand::default()])->render();

    expect($html)->toContain('#00ADEF')
        ->and($html)->toContain('alt="Tu Doctor en Casa"')
        ->and($html)->toContain('width="110" height="28"')
        ->and($html)->not->toContain('Servicio médico de');
})->with([
    'informe médico' => ['documents.informe-medico-largo'],
    'informe de seguimiento' => ['documents.informe-seguimiento'],
    'laboratorio' => ['documents.laboratorios'],
    'imagenología' => ['documents.imagenologia'],
    'especialista' => ['documents.especialista'],
]);

it('limita por alto un logo cuadrado para que no estire la cabecera', function (): void {
    $brand = TelemedicineDocumentBrand::fromCompany(telemedicineBrandAlliedCompany());

    $orden = view('documents.laboratorios', ['data' => telemedicineBrandSampleData(), 'brand' => $brand])->render();
    $recipe = view('documents.medicamentos', ['data' => telemedicineBrandSampleData(), 'brand' => $brand])->render();

    expect($orden)->toContain('width="56" height="56"')
        ->and($recipe)->toContain('width="46" height="46"');
});

it('resuelve la marca sola cuando el llamador no la pasa a la plantilla', function (): void {
    $html = view('documents.laboratorios', ['data' => ['telemedicine_patient_id' => 999_999_999]])->render();

    expect($html)->toContain('#00ADEF')
        ->and($html)->not->toContain('Servicio médico de');
});
