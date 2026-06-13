<?php

declare(strict_types=1);

it('expone checkbox y campos manuales en la bulk action de aceptar renovaciones', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Renovations/RenovationsTable.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Renovations/AcceptRenovationActionForm.php');

    expect($table)
        ->toContain('->form(fn (Collection $records)')
        ->toContain('ManualRenovationAcceptanceOptions::fromFormData');

    expect($form)
        ->toContain('manual_commercial_config')
        ->toContain('calculated_cost_preview')
        ->toContain('Rango de edad (titular)')
        ->toContain('Section::make')
        ->toContain('Propuesta del sistema');
});

it('calcula montos manuales de renovación con tarifa del titular y familiares', function (): void {
    $pricing = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Renovations/RenovationManualAcceptancePricing.php');
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AcceptAffiliationRenovationsService.php');

    expect($pricing)
        ->toContain('previewFamilyTotals')
        ->toContain('amountsForTitularAgeRange');

    expect($service)
        ->toContain('applyManualCommercialConfig')
        ->toContain('ManualRenovationAcceptanceOptions');
});
