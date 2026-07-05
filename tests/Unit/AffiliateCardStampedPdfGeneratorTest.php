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
        ->toContain("'right_px' => 458")
        ->toContain("'cobertura'")
        ->toContain("'frecuencia'");
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

it('CompanyAssociateCarnetGenerator incluye template_key inclusion', function (): void {
    $carnet = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateCarnetGenerator.php');

    expect($carnet)
        ->toContain("'template_key' => 'inclusion'")
        ->toContain('generateTarjetaAfiliacion');
});
