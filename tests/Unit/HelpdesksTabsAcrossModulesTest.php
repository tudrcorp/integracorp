<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Helpdesks\Tables\HelpdesksTable as AdministrationHelpdesksTable;
use App\Filament\Marketing\Resources\Helpdesks\Tables\HelpdesksTable as MarketingHelpdesksTable;
use App\Filament\Operations\Resources\Helpdesks\Tables\HelpdesksTable as OperationsHelpdesksTable;
use Filament\Schemas\Components\Tabs\Tab;

it('define tabs de estatus para helpdesks en operations, administracion y marketing', function (): void {
    $tables = [
        'operations' => OperationsHelpdesksTable::class,
        'administration' => AdministrationHelpdesksTable::class,
        'marketing' => MarketingHelpdesksTable::class,
    ];

    foreach ($tables as $panel => $tableClass) {
        expect(method_exists($tableClass, 'getTabs'))->toBeTrue("Falta getTabs() en {$panel}");

        $tabs = $tableClass::getTabs();

        expect($tabs)->toHaveKeys([
            'todos',
            'pendiente_por_iniciar',
            'en_proceso',
            'terminado',
        ]);

        foreach (['todos', 'pendiente_por_iniciar', 'en_proceso', 'terminado'] as $key) {
            expect($tabs[$key])->toBeInstanceOf(Tab::class);
        }
    }
});
