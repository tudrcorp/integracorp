<?php

namespace App\Filament\Marketing\Resources\Helpdesks\Tables;

use App\Filament\Marketing\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Marketing\Resources\Helpdesks\HelpdeskResource;
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
            exportRouteName: 'marketing.helpdesks.export-csv',
            modalActionsClass: HelpdeskTicketModalActions::class,
            helpdeskResourceClass: HelpdeskResource::class,
        );
    }
}
