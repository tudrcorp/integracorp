<?php

declare(strict_types=1);

use App\Support\Filament\CorporateQuotePlanAffiliatesDisplay;
use Filament\Tables\Columns\TextColumn;

it('expone la columna de plan reutilizable para detalle de cotizacion corporativa', function (): void {
    $column = CorporateQuotePlanAffiliatesDisplay::planColumn();

    expect($column)->toBeInstanceOf(TextColumn::class);
});

it('formatea la descripcion de afiliados en singular y plural', function (): void {
    expect(CorporateQuotePlanAffiliatesDisplay::affiliatesDescription(1))->toBe('1 afiliado')
        ->and(CorporateQuotePlanAffiliatesDisplay::affiliatesDescription(5))->toBe('5 afiliados');
});

it('usa la clase compartida en relation managers de cotizacion corporativa', function (): void {
    $paths = [
        'app/Filament/Master/Resources/CorporateQuotes/RelationManagers/DetailCoporateQuotesRelationManager.php',
        'app/Filament/Agents/Resources/CorporateQuotes/RelationManagers/DetailCoporateQuotesRelationManager.php',
        'app/Filament/Business/Resources/CorporateQuotes/RelationManagers/DetailCoporateQuotesRelationManager.php',
    ];

    foreach ($paths as $path) {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        expect($source)
            ->toContain('CorporateQuotePlanAffiliatesDisplay::planColumn()');
    }
});
