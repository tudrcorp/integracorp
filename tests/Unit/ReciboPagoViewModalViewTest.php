<?php

declare(strict_types=1);

use App\Models\Sale;

uses(Tests\TestCase::class);

it('renderiza modal de vista previa del recibo existente', function (): void {
    $sale = new Sale([
        'invoice_number' => 'INV-VIEW-001',
    ]);

    $html = view('filament.administration.sales.recibo-pago-view-modal', [
        'sale' => $sale,
    ])->render();

    expect($html)
        ->toContain('reciboPagoPanel')
        ->toContain('INV-VIEW-001')
        ->toContain('mode')
        ->toContain('view');
});
