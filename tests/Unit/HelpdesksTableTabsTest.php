<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Helpdesks\Tables\HelpdesksTable;
use App\Support\HelpdeskTableConfigurator;
use App\Support\HelpdeskTaskStatusOptions;
use Filament\Schemas\Components\Tabs\Tab;

it('define tabs de estatus para helpdesks (business)', function (): void {
    $tabs = HelpdesksTable::getTabs();
    $definitionKeys = array_keys(HelpdeskTableConfigurator::statusTabDefinitions());

    expect($tabs)->toHaveKeys(['todos', ...$definitionKeys])
        ->and($definitionKeys)->toHaveCount(count(HelpdeskTaskStatusOptions::all()))
        ->and($definitionKeys)->toContain('en_analisis', 'planificado', 'revertido', 'cancelado');

    foreach (array_merge(['todos'], $definitionKeys) as $key) {
        expect($tabs[$key])->toBeInstanceOf(Tab::class);
    }
});

it('mapea el estatus revertido sin romper las pestañas', function (): void {
    $definitions = HelpdeskTableConfigurator::statusTabDefinitions();

    expect($definitions)->toHaveKey('revertido')
        ->and($definitions['revertido'][0])->toBe(HelpdeskTaskStatusOptions::STATUS_REVERTED)
        ->and(array_keys($definitions))->toHaveCount(count(HelpdeskTaskStatusOptions::all()));
});

it('HelpdeskTableConfigurator expone tabs de cola global cuando el usuario puede verla', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php';

    expect(file_get_contents($path))
        ->toContain("\$tabs['mios'] = Tab::make('Míos')")
        ->toContain("\$tabs['sin_asignar'] = Tab::make('Sin asignar')")
        ->toContain('$keys[$status] ??');
});
