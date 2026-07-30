<?php

declare(strict_types=1);

it('define servicio de aceptación corporativa con transacción historial y eliminación', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AcceptAffiliationCorporateRenovationsService.php');

    expect($source)
        ->toContain('class AcceptAffiliationCorporateRenovationsService')
        ->toContain('DB::transaction')
        ->toContain('AffiliationCorporateRenovationHistory::query()->create')
        ->toContain('$renovation->delete()')
        ->toContain('calculateAmountsForPlanCoverageAndAge')
        ->toContain('effective_date')
        ->toContain('STATUS_RENOVATION_PERIOD')
        ->toContain('historyAttributesFromAppliedState')
        ->toContain('applyManualCommercialConfig')
        ->toContain('createPendingCollectionsForCorporateRenewal')
        ->toContain('AffiliationRenewalCollectionGenerator');
});

it('expone bulk y acción individual de aceptar en tabla compartida corporativa', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/RenovationCorporates/RenovationsCorporateTable.php');

    expect($source)
        ->toContain('acceptRenovationsBulkAction')
        ->toContain('acceptRenovationAction')
        ->toContain('AcceptAffiliationCorporateRenovationsService')
        ->toContain('PERIODO DE RENOVACION');
});

it('define tabla affiliation_corporate_renovation_histories', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_30_083715_create_affiliation_corporate_renovation_histories_table.php');

    expect($source)
        ->toContain('affiliation_corporate_renovation_histories')
        ->toContain('affiliation_corporate_id')
        ->toContain('affiliate_corporate_id')
        ->toContain('accepted_at')
        ->toContain('accepted_by');
});

it('relaciona historial de renovaciones en el modelo de afiliación corporativa', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Models/AffiliationCorporate.php');

    expect($source)->toContain('function renovationHistories()');
});

it('genera cobranzas AFILIACION CORPORATIVA al aceptar renovación', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationRenewalCollectionGenerator.php');

    expect($source)
        ->toContain('createPendingCollectionsForCorporateRenewal')
        ->toContain('AFILIACION CORPORATIVA');
});
