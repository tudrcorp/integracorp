<?php

declare(strict_types=1);

/**
 * Filament indexa las columnas por nombre: si dos columnas comparten nombre, la última pisa
 * a la primera. Así desapareció «Población» de Gestión de Cobranza (la vieja, oculta por
 * defecto, reemplazaba a la nueva visible).
 */
it('no repite nombres de columna en la tabla de Gestión de Cobranza', function (string $path): void {
    $source = file_get_contents(__DIR__.'/../../'.$path);

    preg_match_all("/(?:TextColumn|IconColumn|ViewColumn)::make\\('([^']+)'\\)/", $source, $matches);

    $duplicated = array_keys(array_filter(array_count_values($matches[1]), fn (int $count): bool => $count > 1));

    expect($duplicated)->toBe([]);
})->with([
    'administración' => 'app/Filament/Administration/Resources/Collections/Tables/CollectionsTable.php',
    'admin' => 'app/Filament/Resources/Collections/Tables/CollectionsTable.php',
]);

it('muestra la población visible por defecto con el cálculo de la afiliación', function (): void {
    $source = file_get_contents(__DIR__.'/../../app/Filament/Administration/Resources/Collections/Tables/CollectionsTable.php');

    expect($source)
        ->toContain("TextColumn::make('persons')\n                    ->label('Población')\n                    ->state(fn (Collection \$record): ?int => CollectionReceivableReport::affiliationPopulation(\$record))")
        ->and(substr_count($source, "TextColumn::make('persons')"))->toBe(1);
});
