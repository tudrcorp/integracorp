<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager;

function corporateAffiliatesRelationManagerSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php'
    );
}

it('expone la accion de sincronizar por fila y masiva', function (): void {
    $source = corporateAffiliatesRelationManagerSource();

    expect($source)
        ->toContain("Action::make('sync_with_affiliation')")
        ->toContain("BulkAction::make('sync_with_affiliation_bulk')")
        ->toContain("->label('Sincronizar con la afiliación')")
        ->toContain('$this->runAffiliateSync(')
        ->toContain('CorporateAffiliatePlanSynchronizer::sync($owner, $affiliates)');
});

it('la columna Sync refleja plan, cobertura y tarifa ademas de unidad y linea', function (): void {
    $source = corporateAffiliatesRelationManagerSource();

    expect($source)
        ->toContain('$this->affiliateIsFullySynced($record)')
        ->toContain('$this->syncPendingSummary($record)')
        ->toContain('CorporateAffiliatePlanSynchronizer::isSynced($owner, $record)')
        ->and($source)->not->toContain('->getStateUsing(fn (AffiliateCorporate $record): bool => $this->affiliateBusinessContextIsSynced($record))');
});

it('reasignar plan ya no muere en un dd ni revienta sin fila de plan', function (): void {
    $source = corporateAffiliatesRelationManagerSource();

    expect($source)
        ->not->toContain('dd($plans)')
        ->toContain('if (! $plans instanceof AfilliationCorporatePlan) {')
        ->toContain('La afiliación no tiene contratado ese plan para el rango de edad seleccionado.')
        ->toContain('Fuera del rango de edad seleccionado: ');
});

it('el relation manager sigue siendo el de la afiliacion corporativa', function (): void {
    expect(method_exists(CorporateAffiliatesRelationManager::class, 'table'))->toBeTrue();
});
