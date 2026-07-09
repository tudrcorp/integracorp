<?php

declare(strict_types=1);

it('agrupa plan cobertura y frecuencia en una sola columna', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain("->label('Plan / cobertura / frecuencia')")
        ->toContain('planCoverageFrequencyDescription')
        ->not->toContain("TextColumn::make('coverage.price')")
        ->not->toContain("TextColumn::make('payment_frequency')");
});

it('mejora la tabla de afiliaciones individuales con grupos y pestañas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain('getTabs')
        ->toContain('ColumnGroup::make')
        ->toContain('recordRowClasses')
        ->toContain('emptyStateIcon')
        ->toContain('deferFilters(false)')
        ->toContain('Illuminate\Database\Eloquent\Builder');
});

it('muestra planes asociados en la tabla de afiliaciones corporativas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain("->label('Planes asociados')")
        ->toContain('associatedPlanDescriptions')
        ->toContain('affiliationCorporatePlans.plan');
});

it('mejora la tabla de afiliaciones corporativas con grupos y pestañas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain('getTabs')
        ->toContain('ColumnGroup::make')
        ->toContain('recordRowClasses')
        ->toContain('emptyStateIcon')
        ->toContain('deferFilters(false)')
        ->toContain('Illuminate\Database\Eloquent\Builder')
        ->not->toContain('Illuminate\Database\Query\Builder');
});

it('conecta pestañas en listados de afiliaciones', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Pages/ListAffiliations.php'))
        ->toContain('AffiliationsTable::getTabs')
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Pages/ListAffiliationCorporates.php'))
        ->toContain('AffiliationCorporatesTable::getTabs');
});
