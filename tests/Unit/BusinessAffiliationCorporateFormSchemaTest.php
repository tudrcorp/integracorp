<?php

declare(strict_types=1);

it('usa pestañas con contenedor estilizado en el formulario de afiliación corporativa', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('affiliationCorporateFormTabs')")
        ->toContain('Tab::make(')
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain('private const INNER_CARD')
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("->extraAttributes(['class' => self::SECTION_CARD])")
        ->toContain("->extraAttributes(['class' => self::INNER_CARD])")
        ->not->toContain('Wizard::make');
});

it('ofrece frecuencia de pago mensual como en afiliaciones individuales', function (): void {
    $corporateForm = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php');
    $corporateTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');
    $individualTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($corporateForm)
        ->toContain("'MENSUAL' => 'MENSUAL'")
        ->toContain("if (\$get('payment_frequency') == 'MENSUAL')")
        ->toContain('$subtotal_anual / 12')
        ->and($corporateTable)
        ->toContain("Action::make('edit_frequency')")
        ->toContain("'MENSUAL' => 'MENSUAL'")
        ->toContain('fee_anual / 12')
        ->toContain("payment_frequency == 'MENSUAL' && \$record->paid_membership_corporates()->count() == 12")
        ->and($individualTable)
        ->toContain("Action::make('edit_frequency')");
});
