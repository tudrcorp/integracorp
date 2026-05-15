<?php

declare(strict_types=1);

it('formatea cliente agencia y agente en mayusculas en la tabla de afiliaciones corporativas de administracion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php';
    $contents = file_get_contents($path);

    expect(is_string($contents))->toBeTrue();

    $needle = '->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)';

    expect(substr_count($contents, $needle))->toBe(3);

    expect($contents)
        ->toContain("TextColumn::make('name_corporate')")
        ->and($contents)->toContain("TextColumn::make('agency.name_corporative')")
        ->and($contents)->toContain("TextColumn::make('agent.name')");
});
