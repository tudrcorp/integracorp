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
        ->toContain("'cobertura'")
        ->toContain("'frecuencia'");
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
