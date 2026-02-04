<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditProspectAgent extends EditRecord
{
    protected static string $resource = ProspectAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected static ?string $title = 'Formularios de edición de prospectos';

    protected function mutateFormDataBeforeSave(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;

        return $data;
    }
}
