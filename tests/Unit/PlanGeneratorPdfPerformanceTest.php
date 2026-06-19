<?php

declare(strict_types=1);

use App\Services\PlanGeneratorPdfService;
use App\Support\PlanGenerators\PlanGeneratorPdfImageUri;

uses(Tests\TestCase::class);

it('servicio pdf del generador de planes expone cache de binario e imagenes', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/PlanGeneratorPdfService.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/BusinessPlanGeneratorPdfController.php');
    $imageUri = file_get_contents(dirname(__DIR__, 2).'/app/Support/PlanGenerators/PlanGeneratorPdfImageUri.php');
    $config = file_get_contents(dirname(__DIR__, 2).'/config/plan-generator.php');

    expect($service)
        ->toContain('outputBinaryCached')
        ->toContain('pdfCacheVersion')
        ->toContain('cachedLogoDataUri')
        ->toContain('PlanGeneratorPdfImageUri::forPublicPath')
        ->toContain('isRemoteEnabled')
        ->toContain('false');

    expect($controller)
        ->toContain('outputBinaryCached')
        ->toContain('Cache-Control')
        ->toContain('ETag');

    expect($imageUri)
        ->toContain('optimizeBinary')
        ->toContain('pdf_image_max_width');

    expect($config)->toContain('pdf_cache_ttl_seconds');
});

it('version de cache del pdf incorpora datos del plan', function (): void {
    $plan = new App\Models\PlanGenerator([
        'quotation_page_count' => 2,
        'plan_page_number' => 2,
        'control_number' => 'ABC',
        'client_data' => 'Cliente',
        'updated_at' => now()->parse('2026-06-01 10:00:00'),
    ]);
    $plan->id = 99;

    $versionA = PlanGeneratorPdfService::pdfCacheVersion($plan);

    $plan->client_data = 'Otro cliente';
    $versionB = PlanGeneratorPdfService::pdfCacheVersion($plan);

    expect($versionA)->not->toBe($versionB)
        ->and($versionA)->toHaveLength(64);
});

it('uri de imagen pdf retorna vacio cuando no hay ruta', function (): void {
    expect(PlanGeneratorPdfImageUri::forPublicPath(null))->toBe('')
        ->and(PlanGeneratorPdfImageUri::forPublicPath(''))->toBe('')
        ->and(PlanGeneratorPdfImageUri::forPublicPath('plan-generator-quotation/inexistente.jpg'))->toBe('');
});
