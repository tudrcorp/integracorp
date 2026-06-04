<?php

declare(strict_types=1);

it('define servicio de aceptación con transacción historial y eliminación de renovación', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AcceptAffiliationRenovationsService.php');

    expect($source)
        ->toContain('class AcceptAffiliationRenovationsService')
        ->toContain('DB::transaction')
        ->toContain('AffiliationRenovationHistory::query()->create')
        ->toContain('$renovation->delete()')
        ->toContain('applyIdealToSpecialPlanTransition')
        ->toContain('calculateAffiliateAmountsForRenewal')
        ->toContain('recalculateAffiliationTotalsFromAffiliates')
        ->toContain('effective_date')
        ->toContain('STATUS_RENOVATION_PERIOD')
        ->toContain('historyAttributesFromAppliedState')
        ->toContain('applyManualCommercialConfig');
});

it('expone bulk y acción individual de aceptar en tabla compartida de renovaciones', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Renovations/RenovationsTable.php');

    expect($source)
        ->toContain('acceptRenovationsBulkAction')
        ->toContain('acceptRenovationAction')
        ->toContain('AcceptAffiliationRenovationsService')
        ->toContain('PERIODO DE RENOVACION');
});

it('muestra historial de renovaciones en infolist de afiliación', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');
    $shared = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Affiliations/AffiliationRenovationHistoryInfolist.php');

    expect($infolist)->toContain('AffiliationRenovationHistoryInfolist::tab');
    expect($shared)
        ->toContain('renovationHistories')
        ->toContain('Historial de renovaciones');
});

it('define tabla affiliation_renovation_histories con afiliación y afiliado', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_04_113745_create_affiliation_renovation_histories_table.php');

    expect($source)
        ->toContain('affiliation_renovation_histories')
        ->toContain('affiliation_id')
        ->toContain('affiliate_id')
        ->toContain('accepted_at')
        ->toContain('accepted_by');
});

it('relaciona historial de renovaciones en el modelo de afiliación', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Models/Affiliation.php');

    expect($source)->toContain('function renovationHistories()');
});
