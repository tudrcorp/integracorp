<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;
use App\Filament\Business\Resources\AccountManagers\Widgets\StatsOverviewCountAgentAgency;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class EditAccountManager extends EditRecord
{
    protected static string $resource = AccountManagerResource::class;

    protected static ?string $title = 'Pagina de Gestion y Dashboard de Productividad del Account Manager';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()->name;

        return $data;
    }


    protected function getHeaderWidgets(): array
    {
        
        return [
            StatsOverviewCountAgentAgency::class,
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;

        return $data;
    }
}