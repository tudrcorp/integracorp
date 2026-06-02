<?php

namespace App\Filament\Administration\Resources\Helpdesks\Tables;

use App\Filament\Administration\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource;
use App\Support\HelpdeskTableConfigurator;
use Filament\Tables\Table;

class HelpdesksTable
{
    public static function getTabs(): array
    {
        return HelpdeskTableConfigurator::tabs();
    }

    public static function configure(Table $table): Table
    {
        return HelpdeskTableConfigurator::configure(
            $table,
            exportRouteName: 'administration.helpdesks.export-csv',
            modalActionsClass: HelpdeskTicketModalActions::class,
            helpdeskResourceClass: HelpdeskResource::class,
        );
    }
}
