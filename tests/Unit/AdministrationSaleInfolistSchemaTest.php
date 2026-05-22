<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Sales\Schemas\SaleInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de venta en administracion sin error', function (): void {
    $schema = Schema::make();
    $configured = SaleInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('incluye tabs de venta afiliacion y recibo de pago en el infolist', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Schemas/SaleInfolist.php');

    expect($source)
        ->toContain('saleInfolistTabs')
        ->toContain('paidReceiptTableName')
        ->toContain('resolvePaidReceipt')
        ->toContain('paidReceiptTableName')
        ->toContain('SALE_HERO_SECTION')
        ->toContain('dark:ring-0');
});
