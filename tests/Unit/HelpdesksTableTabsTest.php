<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Helpdesks\Tables\HelpdesksTable;
use Filament\Schemas\Components\Tabs\Tab;

it('define tabs de estatus para helpdesks (business)', function (): void {
    $tabs = HelpdesksTable::getTabs();

    expect($tabs)->toHaveKeys([
        'todos',
        'pendiente_por_iniciar',
        'en_proceso',
        'terminado',
    ]);

    foreach (['todos', 'pendiente_por_iniciar', 'en_proceso', 'terminado'] as $key) {
        expect($tabs[$key])->toBeInstanceOf(Tab::class);
    }
});
