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
        ->and($definitionKeys)->toContain('en_analisis', 'planificado', 'cancelado');

    foreach (array_merge(['todos'], $definitionKeys) as $key) {
        expect($tabs[$key])->toBeInstanceOf(Tab::class);
    }
});
