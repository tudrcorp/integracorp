<?php

declare(strict_types=1);

it('oculta el paso Comisiones del wizard cuando agency_type_id es 1 (agencia master)', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agencies/Schemas/AgencyForm.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Step::make('Comisiones')")
        ->toContain("->hidden(fn (Get \$get): bool => (int) \$get('agency_type_id') === 1)");
});
