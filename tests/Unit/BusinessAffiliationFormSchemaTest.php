<?php

declare(strict_types=1);

it('usa pestañas con contenedor estilizado en el formulario de afiliación individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('affiliationFormTabs')")
        ->toContain('Tab::make(')
        ->toContain('private const TABS_CONTAINER')
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain('private static function editCollapsibleCard')
        ->toContain('->collapsed(fn (string $operation): bool => $operation === \'edit\')')
        ->toContain("editCollapsibleCard('Datos del titular')")
        ->not->toContain('Wizard::make');
});

it('muestra los datos del pagador al editar la afiliación individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php';
    $source = file_get_contents($path);
    $start = strpos($source, "Tab::make('Pagador')");
    $end = strpos($source, "Tab::make('Acuerdo y condiciones')");

    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();
    expect($end)->toBeGreaterThan($start);

    $pagadorTab = substr($source, $start, $end - $start);

    expect($pagadorTab)
        ->toContain("Radio::make('feedback_dos')")
        ->toContain("TextInput::make('full_name_payer')")
        ->toContain("TextInput::make('nro_identificacion_payer')")
        ->toContain("TextInput::make('phone_payer')")
        ->toContain("TextInput::make('email_payer')")
        ->toContain("Select::make('relationship_payer')")
        ->toContain('En edición puede corregir el responsable de pago')
        ->toContain('hidden(function (Get $get, string $operation): bool {')
        ->toContain('if ($operation === \'edit\')')
        ->toContain('return false;')
        ->not->toContain('])->hiddenOn(\'edit\')');
});
