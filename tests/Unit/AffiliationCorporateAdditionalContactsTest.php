<?php

declare(strict_types=1);

it('permite varios contactos al editar la afiliación corporativa de negocios', function (): void {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents($root.'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php');
    $start = strpos($form, "Tab::make('Información de Contacto')");
    $end = strpos($form, "Tab::make('Acuerdo y condiciones')");

    expect($start)->not->toBeFalse();
    expect($end)->toBeGreaterThan($start);

    $contactTab = substr($form, $start, $end - $start);

    expect($contactTab)
        ->toContain("Repeater::make('affiliationCorporateContacts')")
        ->toContain('->relationship()')
        ->toContain("->addActionLabel('Agregar contacto')")
        ->toContain('->maxItems(8)')
        ->toContain('->hiddenOn(\'create\')')
        ->toContain("TextInput::make('full_name_contact')")
        ->toContain("TextInput::make('phone_contact')")
        ->toContain("TextInput::make('email_contact')")
        ->toContain('AffiliationContactPayload::discardIfEmpty');
});

it('muestra los contactos adicionales en la ficha corporativa', function (): void {
    $root = dirname(__DIR__, 2);
    $infolist = file_get_contents($root.'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php');
    $loader = file_get_contents($root.'/app/Filament/Business/Resources/AffiliationCorporates/Concerns/OptimizesAffiliationCorporateInfolistPerformance.php');
    $model = file_get_contents($root.'/app/Models/AffiliationCorporate.php');

    expect($infolist)
        ->toContain("RepeatableEntry::make('affiliationCorporateContacts')")
        ->toContain('Contactos adicionales')
        ->toContain("TextEntry::make('phone_contact')")
        ->toContain("TextEntry::make('email_contact')");

    expect($loader)->toContain("'affiliationCorporateContacts'");
    expect($model)->toContain('function affiliationCorporateContacts()');
});
