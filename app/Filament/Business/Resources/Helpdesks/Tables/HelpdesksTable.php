<?php

namespace App\Filament\Business\Resources\Helpdesks\Tables;

use App\Filament\Business\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
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
            exportRouteName: 'business.helpdesks.export-csv',
            modalActionsClass: HelpdeskTicketModalActions::class,
            helpdeskResourceClass: HelpdeskResource::class,
        );
    }
}
