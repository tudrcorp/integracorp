<?php

declare(strict_types=1);

use App\Support\AffiliateCard\AffiliateCardStampedPdfGenerator;
use App\Support\AffiliateCard\AffiliateCardTemplateBuilder;

uses(Tests\TestCase::class);

it('genera carnet por estampado cuando existe plantilla de inclusion', function (): void {
    $outputPath = sys_get_temp_dir().'/carnet-stamped-test-'.uniqid('', true).'.pdf';

    $payload = [
        'name' => 'María López Pérez',
        'ci' => 'V-12345678',
        'code' => 'NB-1-'.random_int(1000, 9999),
        'plan' => 'INCLUSIÓN',
        'template_key' => 'inclusion',
        'plan_qr_filename' => 'qr-plan-inclusion.png',
        'plan_tarjeta_etiqueta' => 'INCLUSIÓN',
        'frecuencia' => 'CONTADO',
        'cobertura' => 'LOCAL',
        'cobertura_display' => 'LOCAL',
        'desde' => '01/01/2026',
        'hasta' => '31/12/2026',
    ];

    expect(AffiliateCardStampedPdfGenerator::canGenerate($payload))->toBeTrue();

    AffiliateCardStampedPdfGenerator::generate($payload, $outputPath);

    expect(is_file($outputPath))->toBeTrue()
        ->and(filesize($outputPath))->toBeGreaterThan(10_000);

    @unlink($outputPath);
});

it('TarjetaAfiliacionController usa estampado antes que dompdf para inclusion', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/TarjetaAfiliacionController.php');

    expect($controller)
        ->toContain('AffiliateCardStampedPdfGenerator::canGenerate')
        ->toContain('AffiliateCardStampedPdfGenerator::generate')
        ->toContain('generada por estampado');
});

it('expone comando artisan para reconstruir plantillas de carnet', function (): void {
    expect(class_exists(App\Console\Commands\BuildAffiliateCardTemplatesCommand::class))->toBeTrue()
        ->and(AffiliateCardTemplateBuilder::templateExists('inclusion'))->toBeTrue();

    $builder = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliateCard/AffiliateCardTemplateBuilder.php');

    expect($builder)->toContain('withoutStampedFields')
        ->toContain('prepareDataForTarjetaPdfView');
});

it('layout usa posiciones calibradas contra tarjeta-afiliado blade', function (): void {
    $layout = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliateCard/AffiliateCardPageLayout.php');

    expect($layout)
        ->toContain('CANVAS_WIDTH_PX = 793.7')
        ->toContain("'left_px' => 392")
        ->toContain("'left_px' => 267")
        ->toContain('TEMPLATE_INDIVIDUAL')
        ->toContain('TEMPLATE_INDIVIDUAL_AFFILIATION')
        ->toContain('INDIVIDUAL_AFFILIATION_SHEET_UNIT_WIDTH_MM')
        ->toContain('sheetCardOrigin')
        ->toContain("'cobertura'")
        ->toContain("'frecuencia'");
});

it('coloca carnet individual-affiliation centrado en hoja vertical con un afiliado', function (): void {
    $origin = App\Support\AffiliateCard\AffiliateCardPageLayout::sheetCardOrigin(0, true);

    expect($origin['x_mm'])->toBe(22.5)
        ->and($origin['y_mm'])->toBe(119.0);
});

it('usa coordenadas recalibradas para campos individual-affiliation', function (): void {
    $layout = App\Support\AffiliateCard\AffiliateCardPageLayout::class;

    $code = $layout::fieldPosition('code', 'individual-affiliation');
    $ci = $layout::fieldPosition('ci', 'individual-affiliation');
    $desde = $layout::fieldPosition('desde', 'individual-affiliation');
    $hasta = $layout::fieldPosition('hasta', 'individual-affiliation');

    $plan = $layout::fieldPosition('plan', 'individual-affiliation');
    $frecuencia = $layout::fieldPosition('frecuencia', 'individual-affiliation');
    $cobertura = $layout::fieldPosition('cobertura', 'individual-affiliation');

    $nameFirst = $layout::fieldPosition('name_first_part', 'individual-affiliation');
    $nameSecond = $layout::fieldPosition('name_second_part', 'individual-affiliation');

    expect($code['x'])->toBe(26.46)
        ->and($code['y'])->toBe(9.53)
        ->and($layout::fieldPosition('code', 'individual-affiliation', singleAffiliate: false)['x'])->toBe(22.49)
        ->and($nameFirst['x'])->toBe(6.61)
        ->and($nameFirst['y'])->toBe(12.96)
        ->and($nameSecond['x'])->toBe(6.61)
        ->and($nameSecond['y'])->toBe(15.08)
        ->and($layout::fieldPosition('name_first_part', 'individual-affiliation', singleAffiliate: false)['x'])->toBe(6.88)
        ->and($layout::fieldPosition('name_first_part', 'individual-affiliation', singleAffiliate: false)['y'])->toBe(12.96)
        ->and($layout::fieldPosition('name_second_part', 'individual-affiliation', singleAffiliate: false)['x'])->toBe(6.88)
        ->and($layout::fieldPosition('name_second_part', 'individual-affiliation', singleAffiliate: false)['y'])->toBe(15.08)
        ->and($ci['x'])->toBe(10.58)
        ->and($ci['y'])->toBe(18.92)
        ->and($layout::fieldPosition('ci', 'individual-affiliation', singleAffiliate: false)['x'])->toBe(10.05)
        ->and($layout::fieldPosition('ci', 'individual-affiliation', singleAffiliate: false)['y'])->toBe(19.05)
        ->and($plan['x'])->toBe(12.17)
        ->and($plan['y'])->toBe(22.89)
        ->and($layout::fieldPosition('plan', 'individual-affiliation', singleAffiliate: false)['x'])->toBe(11.91)
        ->and($layout::fieldPosition('plan', 'individual-affiliation', singleAffiliate: false)['y'])->toBe(22.75)
        ->and($frecuencia['x'])->toBe(24.61)
        ->and($frecuencia['y'])->toBe(25.8)
        ->and($layout::fieldPosition('frecuencia', 'individual-affiliation', singleAffiliate: false)['x'])->toBe(24.61)
        ->and($layout::fieldPosition('frecuencia', 'individual-affiliation', singleAffiliate: false)['y'])->toBe(25.93)
        ->and($cobertura['x'])->toBe(16.93)
        ->and($cobertura['y'])->toBe(29.1)
        ->and($layout::fieldPosition('cobertura', 'individual-affiliation', singleAffiliate: false)['x'])->toBe(16.4)
        ->and($layout::fieldPosition('cobertura', 'individual-affiliation', singleAffiliate: false)['y'])->toBe(29.1)
        ->and($desde['x'])->toBe(56.36)
        ->and($desde['y'])->toBe(22.36)
        ->and($hasta['x'])->toBe(56.36)
        ->and($hasta['y'])->toBe(25.4)
        ->and($layout::fieldPosition('desde', 'individual-affiliation', singleAffiliate: false)['x'])->toBe(55.83)
        ->and($layout::fieldPosition('desde', 'individual-affiliation', singleAffiliate: false)['y'])->toBe(22.49)
        ->and($layout::fieldPosition('hasta', 'individual-affiliation', singleAffiliate: false)['y'])->toBe(25.4);
});

it('usa fuente reducida solo para codigo en carnet individual-affiliation unico', function (): void {
    $layout = App\Support\AffiliateCard\AffiliateCardPageLayout::class;

    expect($layout::fontSizePtForField('individual-affiliation', 'code', true))->toBe(9.0)
        ->and($layout::fontSizePtForField('individual-affiliation', 'ci', true))->toBe(10.5)
        ->and($layout::fontSizePtForField('individual-affiliation', 'code', false))->toBe(6.5);
});

it('coloca carnet individual-affiliation en cuadrícula 2x4 con varios afiliados', function (): void {
    $layout = App\Support\AffiliateCard\AffiliateCardPageLayout::class;

    expect($layout::sheetCardOrigin(0, false))->toBe(['x_mm' => 10.0, 'y_mm' => 76.0])
        ->and($layout::sheetCardOrigin(1, false))->toBe(['x_mm' => 107.0, 'y_mm' => 76.0])
        ->and($layout::sheetCardOrigin(2, false))->toBe(['x_mm' => 10.0, 'y_mm' => 113.25])
        ->and($layout::sheetCardOrigin(3, false))->toBe(['x_mm' => 107.0, 'y_mm' => 113.25])
        ->and($layout::sheetCardOrigin(4, false))->toBe(['x_mm' => 10.0, 'y_mm' => 150.5])
        ->and($layout::sheetCardOrigin(5, false))->toBe(['x_mm' => 107.0, 'y_mm' => 150.5])
        ->and($layout::sheetCardOrigin(6, false))->toBe(['x_mm' => 10.0, 'y_mm' => 187.75])
        ->and($layout::sheetCardOrigin(7, false))->toBe(['x_mm' => 107.0, 'y_mm' => 187.75]);
});

it('genera lote de carnets individual-affiliation con 8 por hoja', function (): void {
    if (! AffiliateCardTemplateBuilder::templateExists('individual-affiliation')) {
        AffiliateCardTemplateBuilder::buildForTemplateKey('individual-affiliation');
    }

    $outputPath = sys_get_temp_dir().'/carnet-batch-'.uniqid('', true).'.pdf';
    $qrPath = public_path('storage/tarjeta-afiliacion/planes/qr-plan-inicial.png');

    $card = [
        'name' => 'MARIA LOPEZ',
        'ci' => 'V-12345678',
        'code' => 'AFI-2026-001',
        'plan' => 'INICIAL',
        'plan_id' => 1,
        'template_key' => 'individual-affiliation',
        'card_layout' => 'individual-affiliation',
        'plan_qr_filename' => 'qr-plan-inicial.png',
        'plan_qr_absolute_path' => is_file($qrPath) ? $qrPath : null,
        'frecuencia' => 'MENSUAL',
        'cobertura' => 'LOCAL',
        'desde' => '01/01/2026',
        'hasta' => '31/12/2026',
    ];

    $prepared = array_map(
        fn (int $index): array => array_merge($card, ['ci' => 'V-1234567'.$index]),
        range(0, 7),
    );

    expect(AffiliateCardStampedPdfGenerator::canGenerateBatch($prepared))->toBeTrue();

    AffiliateCardStampedPdfGenerator::generateIndividualAffiliationBatch($prepared, $outputPath);

    expect(is_file($outputPath))->toBeTrue()
        ->and(filesize($outputPath))->toBeGreaterThan(10_000);

    @unlink($outputPath);
});

it('genera carnet individual-affiliation por estampado FPDI con plantilla cropped', function (): void {
    if (! AffiliateCardTemplateBuilder::templateExists('individual-affiliation')) {
        AffiliateCardTemplateBuilder::buildForTemplateKey('individual-affiliation');
    }

    $outputPath = sys_get_temp_dir().'/carnet-individual-affiliation-stamped-'.uniqid('', true).'.pdf';
    $qrPath = public_path('storage/tarjeta-afiliacion/planes/qr-plan-inicial.png');

    $payload = [
        'name' => 'Maria Lopez Perez',
        'ci' => '11128694',
        'code' => 'TDEC-IND-000367',
        'plan' => 'ESPECIAL',
        'plan_id' => 1,
        'template_key' => 'individual-affiliation',
        'card_layout' => 'individual-affiliation',
        'plan_qr_filename' => 'qr-plan-inicial.png',
        'plan_qr_absolute_path' => is_file($qrPath) ? $qrPath : null,
        'frecuencia' => 'ANUAL',
        'cobertura' => '10.000,00 US$',
        'desde' => '07/07/2026',
        'hasta' => '07/07/2027',
    ];

    expect(AffiliateCardTemplateBuilder::templateExists('individual-affiliation'))->toBeTrue()
        ->and(AffiliateCardStampedPdfGenerator::canGenerate($payload))->toBeTrue();

    AffiliateCardStampedPdfGenerator::generate($payload, $outputPath);

    expect(is_file($outputPath))->toBeTrue()
        ->and(filesize($outputPath))->toBeGreaterThan(10_000);

    @unlink($outputPath);
});

it('genera carnet individual por estampado FPDI', function (): void {
    if (! AffiliateCardTemplateBuilder::templateExists('individual')) {
        AffiliateCardTemplateBuilder::buildForTemplateKey('individual');
    }

    $outputPath = sys_get_temp_dir().'/carnet-individual-stamped-'.uniqid('', true).'.pdf';
    $qrPath = public_path('storage/tarjeta-afiliacion/planes/qr-plan-inclusion.png');

    $payload = [
        'name' => 'HUMBERTO SEGUNDO SANCHEZ RODRIGUEZ',
        'ci' => '6787979',
        'code' => 'NB-1-6',
        'plan' => 'INCLUSIÓN',
        'template_key' => 'individual',
        'plan_qr_filename' => 'qr-plan-inclusion.png',
        'plan_qr_absolute_path' => is_file($qrPath) ? $qrPath : null,
        'frecuencia' => 'CONTADO',
        'cobertura' => 'LOCAL',
        'desde' => '15/07/2026',
        'hasta' => '15/07/2026',
    ];

    expect(AffiliateCardStampedPdfGenerator::canGenerate($payload))->toBeTrue();

    AffiliateCardStampedPdfGenerator::generate($payload, $outputPath);

    expect(is_file($outputPath))->toBeTrue()
        ->and(filesize($outputPath))->toBeGreaterThan(10_000);

    @unlink($outputPath);
});

it('la plantilla base no incluye campos que luego se estampan', function (): void {
    $data = AffiliateCardTemplateBuilder::withoutStampedFields([
        'name_first_part' => 'X',
        'plan_tarjeta_etiqueta' => 'INCLUSIÓN',
        'desde' => '01/01/2026',
    ]);

    expect($data['plan_tarjeta_etiqueta'])->toBe('')
        ->and($data['name_first_part'])->toBe('')
        ->and($data['desde'])->toBe('');
});

it('el job de documentos del asociado usa cola documents', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GenerateCompanyAssociateDocumentsAfterVoucherJob.php');

    expect($job)
        ->toContain("->onQueue((string) config('affiliate-card.documents_queue'")
        ->toContain('public int $timeout = 120');
});

it('CompanyAssociateCarnetGenerator usa plantilla individual por estampado FPDI', function (): void {
    $carnet = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateCarnetGenerator.php');

    expect($carnet)
        ->toContain("'template_key' => 'individual'")
        ->toContain('generateTarjetaAfiliacion');
});
