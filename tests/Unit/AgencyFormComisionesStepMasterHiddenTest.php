<?php

declare(strict_types=1);

it('oculta la pestaña Comisiones cuando agency_type_id es 1 (agencia master)', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agencies/Schemas/AgencyForm.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tabs::make('masterAgencyFormTabs')")
        ->toContain("Tab::make('Comisiones')")
        ->toContain("->hidden(fn (Get \$get): bool => (int) \$get('agency_type_id') === 1)");
});
