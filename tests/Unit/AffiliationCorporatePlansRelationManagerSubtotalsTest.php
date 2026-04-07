<?php

declare(strict_types=1);

it('expone edición de subtotales mediante modal con repeater', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/AffiliationCorporatePlansRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('Action::make(\'edit_subtotals_modal\')')
        ->toContain('Repeater::make(\'plan_rows\')')
        ->toContain('->fillForm(fn (): array =>')
        ->toContain('subtotal_anual')
        ->toContain('AfilliationCorporatePlan::query()->find');
});
