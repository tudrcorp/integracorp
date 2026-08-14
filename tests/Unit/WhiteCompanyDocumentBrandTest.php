<?php

declare(strict_types=1);

use App\Http\Controllers\AffiliationController;
use App\Models\WhiteCompany;
use App\Models\WhiteCompanyPlanDocument;
use App\Models\WhiteCompanyPlanLabel;
use App\Services\AffiliationBusinessDocumentsService;
use App\Support\AffiliateCard\AffiliateCardStampedPdfGenerator;
use App\Support\AffiliateCard\AffiliateCardTemplateBuilder;
use App\Support\WhiteCompanies\WhiteCompanyBrandColor;
use App\Support\WhiteCompanies\WhiteCompanyCarnetTemplateCompiler;
use App\Support\WhiteCompanies\WhiteCompanyDocumentBrand;

uses(Tests\TestCase::class);

it('normaliza el color de marca de la empresa aliada al cian TDEC por defecto', function (): void {
    expect(WhiteCompanyBrandColor::resolve(null))->toBe('#26b2ca')
        ->and(WhiteCompanyBrandColor::resolve(''))->toBe('#26b2ca')
        ->and(WhiteCompanyBrandColor::resolve('#AA1122'))->toBe('#aa1122')
        ->and(WhiteCompanyBrandColor::resolve('aa1122'))->toBe('#aa1122')
        ->and(WhiteCompanyBrandColor::resolve('invalid'))->toBe('#26b2ca');
});

it('devuelve el kit TDEC cuando no hay empresa aliada', function (): void {
    $brand = WhiteCompanyDocumentBrand::fromCompany(null);

    expect($brand->company)->toBeNull()
        ->and($brand->primaryColor)->toBe('#26b2ca')
        ->and($brand->logoAbsolutePath)->toBeNull()
        ->and($brand->certificateSignatureAbsolutePath)->toBeNull()
        ->and($brand->carnetCompiledPdfAbsolutePath)->toBeNull()
        ->and($brand->logoDataUri())->toBe('')
        ->and($brand->signatureDataUri())->toBe('');
});

it('usa el color de la empresa y cae al condicionado TDEC si no hay PDF propio', function (): void {
    $company = new WhiteCompany([
        'brand_primary_color' => '#ff5500',
    ]);
    $company->setRelation('planDocuments', collect());

    $brand = WhiteCompanyDocumentBrand::fromCompany($company);

    expect($brand->primaryColor)->toBe('#ff5500')
        ->and($brand->condicionadoAbsolutePath(1))
        ->toBe(AffiliationBusinessDocumentsService::condicionadoAbsolutePathForPlanId(1));
});

it('prefiere el condicionado de la empresa cuando el archivo existe', function (): void {
    $relative = 'white-companies/condicionados/test-condicionado-'.uniqid('', true).'.pdf';
    $absolute = storage_path('app/public/'.$relative);
    $directory = dirname($absolute);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($absolute, '%PDF-1.4 test');

    $company = new WhiteCompany;
    $company->setRelation('planDocuments', collect([
        new WhiteCompanyPlanDocument([
            'plan_id' => 2,
            'kind' => WhiteCompanyPlanDocument::KIND_CONDICIONADO,
            'path' => $relative,
        ]),
    ]));

    $brand = WhiteCompanyDocumentBrand::fromCompany($company);

    expect($brand->condicionadoAbsolutePath(2))->toBe($absolute)
        ->and($brand->condicionadoAbsolutePath(1))
        ->toBe(AffiliationBusinessDocumentsService::condicionadoAbsolutePathForPlanId(1));

    @unlink($absolute);
});

it('no compila plantilla de carnet si la empresa no tiene imagen', function (): void {
    $company = new WhiteCompany;
    $company->id = 999001;

    expect(WhiteCompanyCarnetTemplateCompiler::compile($company))->toBeNull()
        ->and(WhiteCompanyCarnetTemplateCompiler::compiledPathFor($company))
        ->toContain('white-company-999001');
});

it('compila plantilla individual-affiliation con una imagen personalizada', function (): void {
    $image = public_path('storage/certificados/tarjeta-afiliado-individual-cropped.png');

    if (! is_file($image)) {
        $this->markTestSkipped('No existe la imagen base del carnet individual.');
    }

    $output = sys_get_temp_dir().'/wc-carnet-template-'.uniqid('', true).'.pdf';

    AffiliateCardTemplateBuilder::buildForTemplateKey('individual-affiliation', $output, $image);

    expect(is_file($output))->toBeTrue()
        ->and(filesize($output))->toBeGreaterThan(1_000);

    @unlink($output);
});

it('el generador FPDI acepta template_path personalizado', function (): void {
    $defaultTemplate = AffiliateCardTemplateBuilder::templatePathForKey('individual-affiliation');

    if (! is_file($defaultTemplate)) {
        AffiliateCardTemplateBuilder::buildForTemplateKey('individual-affiliation');
    }

    $payload = [
        'name' => 'Ana Perez',
        'ci' => '123',
        'code' => 'TDEC-IND-BRAND',
        'plan' => 'INICIAL',
        'template_key' => 'individual-affiliation',
        'card_layout' => 'individual-affiliation',
        'template_path' => $defaultTemplate,
        'frecuencia' => 'ANUAL',
        'cobertura' => '1000',
        'desde' => '01/01/2026',
        'hasta' => '01/01/2027',
    ];

    expect(AffiliateCardStampedPdfGenerator::canGenerate($payload))->toBeTrue();
});

it('el certificado recibe color y logo de marca', function (): void {
    $data = AffiliationController::dataForCertificatePdfView(
        [
            'name' => 'Pagador',
            'code' => 'TDEC-1',
            'tarifa_anual' => 100,
            'plan' => 'INICIAL',
            'plan_id' => 1,
            'frecuencia_pago' => 'ANUAL',
            'cobertura' => 1000,
            'fecha_afiliacion' => '',
            'tarifa_periodo' => 100,
            'fecha_vigencia' => '01/01/2026',
            'agente_agencia' => 'Agente',
        ],
        ['BENEFICIO'],
        [],
        WhiteCompanyDocumentBrand::tdec(),
    );

    expect($data['brandColor'])->toBe('#26b2ca')
        ->and($data['logoDataUri'])->toBe('')
        ->and($data['signatureDataUri'])->toBe('')
        ->and($data['isAlliedCertificate'])->toBeFalse()
        ->and($data['companyName'])->toBe('')
        ->and($data['pagador']['plan'])->toBe('INICIAL');
});

it('el certificado de empresa aliada usa el nombre comercial del plan', function (): void {
    $company = new WhiteCompany(['name' => 'VivePlus', 'brand_primary_color' => '#b768c1']);
    $company->setRelation('planDocuments', collect());
    $company->setRelation('planLabels', collect([
        new WhiteCompanyPlanLabel([
            'plan_id' => 3,
            'display_name' => 'Plan Bienestar',
            'short_label' => 'BIENESTAR',
        ]),
    ]));
    $brand = WhiteCompanyDocumentBrand::fromCompany($company);

    $data = AffiliationController::dataForCertificatePdfView(
        [
            'name' => 'Pagador',
            'code' => 'TDEC-1',
            'tarifa_anual' => 100,
            'plan' => 'PLAN ESPECIAL',
            'plan_id' => 3,
            'frecuencia_pago' => 'ANUAL',
            'cobertura' => 1000,
            'fecha_afiliacion' => '',
            'tarifa_periodo' => 100,
            'fecha_vigencia' => '01/01/2026',
            'agente_agencia' => 'VivePlus',
        ],
        ['BENEFICIO'],
        [],
        $brand,
    );

    expect($data['isAlliedCertificate'])->toBeTrue()
        ->and($data['pagador']['plan'])->toBe('Plan Bienestar')
        ->and($data['pagador']['plan_id'])->toBe(3);
});

it('el certificado de empresa aliada no usa el fondo TDEC y lleva el nombre de la marca', function (): void {
    $company = new WhiteCompany(['name' => 'VivePlus', 'brand_primary_color' => '#b768c1']);
    $company->setRelation('planDocuments', collect());
    $brand = WhiteCompanyDocumentBrand::fromCompany($company);

    $data = AffiliationController::dataForCertificatePdfView(
        [
            'name' => 'Pagador',
            'code' => 'TDEC-1',
            'tarifa_anual' => 100,
            'plan' => 'INICIAL',
            'plan_id' => 1,
            'frecuencia_pago' => 'ANUAL',
            'cobertura' => 1000,
            'fecha_afiliacion' => '',
            'tarifa_periodo' => 100,
            'fecha_vigencia' => '01/01/2026',
            'agente_agencia' => 'VivePlus',
        ],
        ['BENEFICIO'],
        [],
        $brand,
    );

    expect($brand->isAllied())->toBeTrue()
        ->and($data['isAlliedCertificate'])->toBeTrue()
        ->and($data['companyName'])->toBe('VivePlus')
        ->and($data['brandColor'])->toBe('#b768c1')
        ->and($data['signatureDataUri'])->toBe('');
});

it('la vista del certificado usa color de marca y logo dinamico', function (): void {
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/certificate.blade.php');

    expect($blade)
        ->toContain('$brandColor')
        ->toContain('$logoDataUri')
        ->toContain('$signatureDataUri')
        ->toContain('$isAlliedCertificate')
        ->toContain('fondo-certificado.png')
        ->toContain('background-color: #ffffff;')
        ->not->toContain('color: #26b2ca;');
});

it('el servicio de documentos pasa la plantilla de la empresa aliada al carnet', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationBusinessDocumentsService.php');

    expect($service)
        ->toContain('WhiteCompanyDocumentBrand::forAffiliation')
        ->toContain("\$payload['template_path']")
        ->toContain('TEMPLATE_INDIVIDUAL_AFFILIATION_ALLIED')
        ->toContain('condicionadoAbsolutePathForPlanId')
        ->toContain('planDisplayName')
        ->toContain('plan_tarjeta_etiqueta');
});
