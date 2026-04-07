<?php

declare(strict_types=1);

use App\Filament\Business\Resources\WhiteCompanies\Tables\WhiteCompaniesTable;

it('expone el configurador de tabla de empresas aliadas', function () {
    expect(method_exists(WhiteCompaniesTable::class, 'configure'))->toBeTrue();
});
