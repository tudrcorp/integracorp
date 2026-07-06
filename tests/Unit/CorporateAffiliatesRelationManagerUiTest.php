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
        ->toContain("->label('Cargo en la empresa')")
        ->toContain("TextColumn::make('business_unit_id')")
        ->toContain("TextColumn::make('business_line_id')")
        ->toContain("IconColumn::make('sync_status')")
        ->toContain('businessLine:id,definition')
        ->toContain('businessUnit:id,definition')
        ->toContain('affiliateBusinessContextIsSynced')
        ->toContain('ColumnGroup::make(\'Voucher ILS\'')
        ->toContain('ils_status')
        ->toContain('has_voucher_ils')
        ->toContain('AffiliateVaucherIlsRemainingDays');
});
