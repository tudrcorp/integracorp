<?php

declare(strict_types=1);

function pgUpgradeSource(string $relativePath): string
{
    return file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
}

it('el formulario ofrece los upgrades del catálogo y el editor suma su costo al total grupal', function (): void {
    expect(pgUpgradeSource('app/Filament/Business/Resources/PlanGenerators/Schemas/PlanGeneratorForm.php'))
        ->toContain("Section::make('Upgrades opcionales')")
        ->toContain("Repeater::make('upgrades')")
        ->toContain('UpgradePricing::coverageOptions()')
        ->toContain("'upgradesAnnual' => self::upgradesQuote(\$get, \$record)['annual_total'],")
        ->and(pgUpgradeSource('resources/views/filament/business/plan-generators/partials/group-total-matrix.blade.php'))
        ->toContain('PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows, $upgradesAnnual)');
});

it('crear y editar validan los upgrades antes de guardar y los guardan con la matriz', function (): void {
    expect(pgUpgradeSource('app/Filament/Business/Resources/PlanGenerators/Pages/CreatePlanGenerator.php'))
        ->toContain('$this->assertPlanGeneratorUpgradesArePriceable($data);')
        ->and(pgUpgradeSource('app/Filament/Business/Resources/PlanGenerators/Pages/EditPlanGenerator.php'))
        ->toContain('$this->assertPlanGeneratorUpgradesArePriceable($data, $this->getRecord());')
        ->and(pgUpgradeSource('app/Support/PlanGenerators/PlanGeneratorPersistence.php'))
        ->toContain("PlanGeneratorUpgrades::sync(\$planGenerator, (array) \$formState['upgrades']);")
        ->toContain("'upgrades' => PlanGeneratorUpgrades::formState(\$planGenerator),");
});

it('la vista previa y el PDF muestran los upgrades y el PDF los incluye en su caché', function (): void {
    expect(pgUpgradeSource('resources/views/filament/business/plan-generators/stacked-matrices-preview.blade.php'))
        ->toContain("@include('filament.business.plan-generators.partials.upgrades-preview'")
        ->and(pgUpgradeSource('resources/views/documents/partials/plan-generator-plan-body.blade.php'))
        ->toContain("PlanGeneratorGroupTotalCalculator::totalsByColumn((array) \$columns, (array) \$rateRows, \$upgradeQuote['annual_total'])")
        ->and(pgUpgradeSource('app/Services/PlanGeneratorPdfService.php'))
        ->toContain("DB::table('plan_generator_upgrades')");
});

it('derivar copia los upgrades de la plantilla y bloquea si dejan de tener precio', function (): void {
    expect(pgUpgradeSource('app/Support/PlanGenerators/PlanGeneratorTemplateCloner.php'))
        ->toContain('PlanGeneratorUpgrades::copy($template, $derived);')
        ->and(pgUpgradeSource('app/Filament/Business/Resources/PlanGenerators/Tables/Actions/DeriveQuotationBulkAction.php'))
        ->toContain('self::assertTemplateUpgradesArePriceable($action, $template, $formState);');
});

it('aprobar la cotización suma los upgrades y los asigna a cada afiliado creado', function (): void {
    expect(pgUpgradeSource('app/Support/PlanGenerators/PlanGeneratorPreAffiliationOptions.php'))
        ->toContain("'upgrade_fee' => \$upgrades['total'],")
        ->and(pgUpgradeSource('app/Support/PlanGenerators/PlanGeneratorPreAffiliationSession.php'))
        ->toContain("\$payload['upgrade_lines'] = PlanGeneratorUpgrades::linesFromModel(\$plan);")
        ->and(pgUpgradeSource('app/Support/PlanGenerators/PlanGeneratorCoverageAssignment.php'))
        ->toContain("'fee' => round(\$placement['fee'] + (\$placement['upgrade_fee'] ?? 0), 2),")
        ->toContain('PlanGeneratorUpgrades::eligibilityForAge($upgradeLines, $age, $rowLabel)')
        ->and(substr_count(pgUpgradeSource('app/Filament/Business/Resources/Affiliations/Pages/CreateAffiliation.php'), 'PlanGeneratorUpgradeAssignment::assignIndividual($record, $generatorPayload);'))->toBe(2)
        ->and(pgUpgradeSource('app/Filament/Business/Resources/PlanGenerators/Pages/PreAffiliationPopulation.php'))
        ->toContain("TextColumn::make('upgrades')")
        ->and(pgUpgradeSource('app/Filament/Business/Resources/AffiliationCorporates/Pages/CreateAffiliationCorporate.php'))
        ->toContain('PlanGeneratorUpgradeAssignment::assignCorporate(');
});
