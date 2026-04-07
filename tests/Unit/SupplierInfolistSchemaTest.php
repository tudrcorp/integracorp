<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Schemas\SupplierInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de proveedor operations sin error', function (): void {
    $schema = Schema::make();
    $configured = SupplierInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
