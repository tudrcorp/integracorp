<?php

declare(strict_types=1);

it('usa pestañas en el formulario de agencia master', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agencies/Schemas/AgencyForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('masterAgencyFormTabs')")
        ->toContain("Tab::make('Información Principal')")
        ->toContain("Tab::make('Contacto Secuendario')")
        ->toContain("Tab::make('Acuerdo y condiciones')")
        ->not->toContain('Wizard::make');
});
