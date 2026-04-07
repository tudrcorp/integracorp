<?php

declare(strict_types=1);

it('estructura el formulario de afiliados corporativos en secciones sin eliminar campos clave', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Section::make('Datos personales')")
        ->toContain("Section::make('Contacto')")
        ->toContain("Section::make('Salud y empresa')")
        ->toContain("Section::make('Emergencia y dirección')")
        ->toContain("Section::make('Plan de afiliación')")
        ->toContain("TextInput::make('first_name')")
        ->toContain("TextInput::make('last_name')")
        ->toContain("TextInput::make('position_company')")
        ->toContain("->label('Cargo en la empresa')");
});
