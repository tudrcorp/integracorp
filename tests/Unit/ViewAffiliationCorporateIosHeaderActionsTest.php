<?php

declare(strict_types=1);

it('aplica estilos iOS a las acciones del encabezado en ViewAffiliationCorporate', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php');

    expect($source)
        ->toContain('FilamentIosButton::extraClassForFilamentColor')
        ->toContain("Action::make('back')")
        ->toContain("Action::make('attachDocuments')")
        ->toContain('EditAction::make()')
        ->toContain('modalSubmitAction(')
        ->toContain('modalCancelAction(');
});
