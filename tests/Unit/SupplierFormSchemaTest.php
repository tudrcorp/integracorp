<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Schemas\SupplierForm;
use Filament\Schemas\Schema;

uses(Tests\TestCase::class);

it('configura el formulario de proveedor operations sin error', function (): void {
    $schema = Schema::make();
    $configured = SupplierForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
