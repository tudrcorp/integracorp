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
