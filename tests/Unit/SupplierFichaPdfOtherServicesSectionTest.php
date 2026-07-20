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

it('certificacion de infraestructura agrupa por categorias y muestra descripcion', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/documents/supplier-ficha.blade.php';
    $contents = file_get_contents($viewPath);

    expect($contents)
        ->toContain('infra-cert-columns')
        ->toContain('<th>Infraestructura</th>')
        ->toContain('¿Dispone?')
        ->toContain('infra-group')
        ->toContain('SupplierInfrastructureCatalog::groups()')
        ->not->toContain('<th>Descripción</th>');

    $supplier = new Supplier([
        'name' => 'Proveedor prueba',
        'cirugia_general' => true,
        'descripcion_cirugia_general' => 'Quirófanos 24 h',
        'otras_unidades_especiales' => true,
        'descripcion_otras_unidades_especiales' => 'Unidad de dolor',
    ]);

    $html = View::make('documents.supplier-ficha', [
        'supplier' => $supplier,
        'infraCheckBadgeDataUri' => PdfCertifiedCheckBadge::dataUri(),
    ])->render();

    expect($html)
        ->toContain('Certificación de Infraestructura')
        ->toContain('Especialidades Básicas y Hospitalización')
        ->toContain('Cirugía General')
        ->toContain('OTRAS Unidades Médicas Especializadas Tipo AA')
        ->toContain('Otras unidades especiales')
        ->toContain('infra-cert-badge-img')
        ->toContain('infra-cert-columns')
        ->toContain('Quirófanos 24 h')
        ->toContain('Unidad de dolor');

    expect(substr_count($html, 'class="infra-group"'))->toBe(2);
});
