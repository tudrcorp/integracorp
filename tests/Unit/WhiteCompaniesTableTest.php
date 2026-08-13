<?php

declare(strict_types=1);

use App\Filament\Business\Resources\WhiteCompanies\Tables\WhiteCompaniesTable;

it('expone el configurador de tabla de empresas aliadas', function () {
    expect(method_exists(WhiteCompaniesTable::class, 'configure'))->toBeTrue();
});

it('muestra el credito asignado en la tabla de empresas aliadas', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Tables/WhiteCompaniesTable.php');

    expect($table)
        ->toContain("TextColumn::make('assigned_credit')")
        ->toContain("label('Crédito asignado')");
});
