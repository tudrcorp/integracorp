<?php

declare(strict_types=1);

it('usa pestañas con estilos en el formulario de agencia del panel general', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/General/Resources/Agencies/Schemas/AgencyForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('generalAgencyFormTabs')")
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain("Tab::make('Información Principal')")
        ->toContain("Tab::make('Contacto Secuendario')")
        ->toContain("Tab::make('Comisiones')")
        ->toContain("Tab::make('Información Bancaria Local(VES)')")
        ->toContain("Tab::make('Datos Bancarios(US$)')")
        ->toContain("Tab::make('Acuerdo y condiciones')")
        ->not->toContain('Wizard::make');
});
