<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Support\PdfCertifiedCheckBadge;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('incluye la seccion de otros servicios en la ficha pdf del proveedor', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/documents/supplier-ficha.blade.php';
    $contents = file_get_contents($viewPath);

    expect($contents)
        ->toContain('section-title">Otros servicios')
        ->toContain('$supplier->otros_servicios')
        ->toContain('nl2br(e((string) $supplier->otros_servicios))');
});

it('certificacion de infraestructura muestra solo infraestructura y dispone en tres columnas', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/documents/supplier-ficha.blade.php';
    $contents = file_get_contents($viewPath);

    expect($contents)
        ->toContain('infra-cert-columns')
        ->toContain('<th>Infraestructura</th>')
        ->toContain('<th>¿Dispone?</th>')
        ->not->toContain('<th>Descripción</th>');

    $supplier = new Supplier([
        'name' => 'Proveedor prueba',
        'urgen_care' => true,
        'descripcion_urgen_care' => 'Urgencias 24 h',
        'otras_unidades_especiales' => true,
        'descripcion_otras_unidades_especiales' => 'Unidad de dolor',
    ]);

    $html = View::make('documents.supplier-ficha', [
        'supplier' => $supplier,
        'infraCheckBadgeDataUri' => PdfCertifiedCheckBadge::dataUri(),
    ])->render();

    expect($html)
        ->toContain('Certificación de Infraestructura')
        ->toContain('Urgencias')
        ->toContain('Otras unidades especializadas')
        ->toContain('infra-cert-badge-img')
        ->toContain('infra-cert-columns')
        ->not->toContain('Urgencias 24 h')
        ->not->toContain('Unidad de dolor');

    expect(substr_count($html, 'class="infra-cert-col"'))->toBe(3);
});
