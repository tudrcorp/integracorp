<?php

declare(strict_types=1);

use App\Support\Filament\CorporateAffiliatesTableDisplay;
use Filament\Tables\Columns\TextColumn;

it('expone las columnas de afiliados corporativos para consulta comercial', function (): void {
    $columns = CorporateAffiliatesTableDisplay::columns();

    expect($columns)->toHaveCount(9)
        ->and($columns[0])->toBeInstanceOf(TextColumn::class);
});

it('incluye los datos clave solicitados para afiliados corporativos', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/CorporateAffiliatesTableDisplay.php');

    expect($source)
        ->toContain("TextColumn::make('first_name')")
        ->toContain("TextColumn::make('last_name')")
        ->toContain("TextColumn::make('nro_identificacion')")
        ->toContain("TextColumn::make('birth_date')")
        ->toContain("TextColumn::make('email')")
        ->toContain("TextColumn::make('phone')")
        ->toContain("TextColumn::make('affiliationCorporate.state.definition')")
        ->toContain("TextColumn::make('affiliationCorporate.city.definition')")
        ->toContain("TextColumn::make('address')");
});

it('registra el relation manager por panel en master general y agentes', function (): void {
    $expectations = [
        'app/Filament/Master/Resources/AffiliationCorporates/AffiliationCorporateResource.php' => 'App\Filament\Master\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager',
        'app/Filament/General/Resources/AffiliationCorporates/AffiliationCorporateResource.php' => 'App\Filament\General\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager',
        'app/Filament/Agents/Resources/AffiliationCorporates/AffiliationCorporateResource.php' => 'App\Filament\Agents\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager',
    ];

    foreach ($expectations as $path => $namespace) {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        expect($source)
            ->toContain('CorporateAffiliatesRelationManager::class')
            ->toContain($namespace);
    }
});

it('usa la tabla compartida en los relation managers por panel', function (): void {
    $paths = [
        'app/Filament/Master/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php',
        'app/Filament/General/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php',
        'app/Filament/Agents/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php',
    ];

    foreach ($paths as $path) {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        expect($source)
            ->toContain('CorporateAffiliatesTableDisplay::configureReadOnlyTable($table)');
    }
});
