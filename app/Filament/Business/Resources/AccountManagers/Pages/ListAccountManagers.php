<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;
use App\Filament\Business\Resources\AccountManagers\Widgets\StatsOverviewTotalAccountManager;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccountManagers extends ListRecords
{
    protected static string $resource = AccountManagerResource::class;

    protected function getHeaderWidgets(): array
    {

        return [
            StatsOverviewTotalAccountManager::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
