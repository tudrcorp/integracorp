<?php

declare(strict_types=1);

use App\Support\Affiliations\AffiliationContactPayload;

it('descarta un contacto vacío y normaliza nombre, teléfono y correo', function (): void {
    expect(AffiliationContactPayload::discardIfEmpty([
        'name' => '   ',
        'relationship' => '',
        'phone' => '   ',
        'email' => '',
    ]))->toBeNull();

    $contact = AffiliationContactPayload::discardIfEmpty([
        'name' => '  maría pérez ',
        'relationship' => ' esposa ',
        'phone' => '+58 412-123.45.67',
        'email' => ' Maria@Ejemplo.COM ',
    ]);

    expect($contact)->toMatchArray([
        'name' => 'MARÍA PÉREZ',
        'relationship' => 'ESPOSA',
        'phone' => '+584121234567',
        'email' => 'maria@ejemplo.com',
    ]);
});

it('conserva un contacto que solo trae teléfono', function (): void {
    $contact = AffiliationContactPayload::discardIfEmpty([
        'name' => 'carlos',
        'phone' => '04121234567',
    ]);

    expect($contact)->toMatchArray([
        'name' => 'CARLOS',
        'relationship' => null,
        'phone' => '04121234567',
        'email' => null,
    ]);
});

it('permite varios contactos al editar la afiliación individual de negocios', function (): void {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents($root.'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php');
    $start = strpos($form, "Tab::make('Titular')");
    $end = strpos($form, "Tab::make('Afiliados')");

    expect($start)->not->toBeFalse();
    expect($end)->toBeGreaterThan($start);

    $titularTab = substr($form, $start, $end - $start);

    expect($titularTab)
        ->toContain("Repeater::make('affiliationContacts')")
        ->toContain('->relationship()')
        ->toContain("->addActionLabel('Agregar contacto')")
        ->toContain('->maxItems(8)')
        ->toContain('->hiddenOn(\'create\')')
        ->toContain("TextInput::make('phone_ti')")
        ->toContain("TextInput::make('email_ti')")
        ->toContain('AffiliationContactPayload::discardIfEmpty');
});

it('muestra los contactos adicionales en la ficha del titular', function (): void {
    $root = dirname(__DIR__, 2);
    $infolist = file_get_contents($root.'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php');
    $loader = file_get_contents($root.'/app/Filament/Business/Resources/Affiliations/Concerns/OptimizesAffiliationInfolistPerformance.php');
    $model = file_get_contents($root.'/app/Models/Affiliation.php');

    expect($infolist)
        ->toContain("RepeatableEntry::make('affiliationContacts')")
        ->toContain('Contactos adicionales')
        ->toContain("TextEntry::make('phone_ti')")
        ->toContain("TextEntry::make('email_ti')");

    expect($loader)->toContain("'affiliationContacts'");
    expect($model)->toContain('function affiliationContacts()');
});
