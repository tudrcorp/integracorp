<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\AffiliateCorporates\Tables\AffiliateCorporatesTable;
use App\Filament\Operations\Resources\Affiliates\Tables\AffiliatesTable;

it('define el configurador de tabla de afiliados operations', function (): void {
    expect(method_exists(AffiliatesTable::class, 'configure'))->toBeTrue();
});

it('define el configurador de tabla de afiliados corporativos operations', function (): void {
    expect(method_exists(AffiliateCorporatesTable::class, 'configure'))->toBeTrue();
});
