<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;

it('define el configurador de tabla de afiliaciones corporativas', function (): void {
    expect(method_exists(AffiliationCorporatesTable::class, 'configure'))->toBeTrue();
});
