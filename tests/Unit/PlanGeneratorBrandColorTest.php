<?php

declare(strict_types=1);

use App\Support\PlanGenerators\PlanGeneratorBrandColor;

it('normaliza el color de marca del generador de planes', function (): void {
    expect(PlanGeneratorBrandColor::resolve(null))->toBe('#1d4ed8')
        ->and(PlanGeneratorBrandColor::resolve(''))->toBe('#1d4ed8')
        ->and(PlanGeneratorBrandColor::resolve('#FF5500'))->toBe('#ff5500')
        ->and(PlanGeneratorBrandColor::resolve('ff5500'))->toBe('#ff5500')
        ->and(PlanGeneratorBrandColor::resolve('invalid'))->toBe('#1d4ed8');
});

it('calcula un borde mas oscuro para encabezados del pdf', function (): void {
    $border = PlanGeneratorBrandColor::headerBorderColor('#1d4ed8');

    expect($border)->toStartWith('#')
        ->and($border)->not->toBe('#1d4ed8');
});

it('servicio pdf incluye color de marca en la version de cache', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/PlanGeneratorPdfService.php');

    expect($service)
        ->toContain('PlanGeneratorBrandColor::resolve')
        ->toContain('brandColor')
        ->toContain('brandColorBorder');
});
