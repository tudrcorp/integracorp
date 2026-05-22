<?php

declare(strict_types=1);

use App\Models\Sale;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

it('renderiza la vista modal de vista previa de recibo de pago', function (): void {
    $sale = new Sale([
        'invoice_number' => 'RDP-TEST-1',
    ]);
    $sale->id = 1;

    $html = view('filament.administration.sales.recibo-pago-preview-modal', [
        'sale' => $sale,
    ])->render();

    expect($html)
        ->toContain('reciboPagoPanel')
        ->toContain('Generar vista previa')
        ->toContain('regenerateUrl');
});

it('registra la ruta async de regeneracion de recibo de pago en ventas', function (): void {
    expect(Route::has('administration.sales.recibo-pago.regenerate-async'))->toBeTrue();
});
