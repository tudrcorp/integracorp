<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Sales\Tables\SalesTable;

uses(Tests\TestCase::class);

it('expone metodos publicos de regeneracion de recibo de pago', function (): void {
    expect(method_exists(SalesTable::class, 'runRegenerateReciboPago'))->toBeTrue()
        ->and(method_exists(SalesTable::class, 'buildReciboPagoRegenerationPayload'))->toBeTrue()
        ->and(method_exists(SalesTable::class, 'deleteReciboPagoPdfIfExists'))->toBeTrue();
});

it('elimina el pdf existente del recibo antes de regenerar', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');

    expect($source)
        ->toContain('deleteReciboPagoPdfIfExists($record)')
        ->toContain('AUDIT_ADMIN_SALES_PDF_EXISTING_REMOVED');
});

it('la accion regenerar pdf usa modal de vista previa', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');

    expect($source)
        ->toContain('recibo-pago-preview-modal')
        ->toContain('modalSubmitAction(false)')
        ->toContain('runRegenerateReciboPago');
});

it('la accion descargar pdf usa modal de vista previa del recibo existente', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');

    expect($source)
        ->toContain('recibo-pago-view-modal')
        ->toContain('download_recibo_pdf')
        ->toContain('reciboPagoPreviewUrl');
});

it('resuelve url de vista previa cuando el pdf existe', function (): void {
    $directory = public_path('storage/reciboDePago');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $sale = new \App\Models\Sale([
        'invoice_number' => 'TEST-PREVIEW-'.uniqid(),
    ]);

    $path = \App\Filament\Administration\Resources\Sales\Tables\SalesTable::reciboPagoPdfPath($sale);
    file_put_contents($path, '%PDF-1.4 test');

    expect(\App\Filament\Administration\Resources\Sales\Tables\SalesTable::reciboPagoPreviewUrl($sale))
        ->not->toBeNull();

    @unlink($path);
});

it('borra el archivo pdf del recibo cuando existe', function (): void {
    $directory = public_path('storage/reciboDePago');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $sale = new \App\Models\Sale([
        'invoice_number' => 'TEST-DELETE-'.uniqid(),
    ]);

    $path = SalesTable::reciboPagoPdfPath($sale);
    file_put_contents($path, '%PDF-1.4 test');

    expect(SalesTable::deleteReciboPagoPdfIfExists($sale))->toBeTrue()
        ->and(is_file($path))->toBeFalse()
        ->and(SalesTable::deleteReciboPagoPdfIfExists($sale))->toBeFalse();
});
