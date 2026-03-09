<?php

namespace App\Filament\Marketing\Resources\CollaboratorAnniversaries\Pages;

use App\Filament\Marketing\Resources\CollaboratorAnniversaries\CollaboratorAnniversaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditCollaboratorAnniversary extends EditRecord
{
    protected static string $resource = CollaboratorAnniversaryResource::class;

    protected static ?string $title = 'Editar Aniversario';

    //mutateFormDataBeforeSave
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()->name;
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
