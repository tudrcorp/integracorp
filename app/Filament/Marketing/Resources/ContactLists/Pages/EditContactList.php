<?php

namespace App\Filament\Marketing\Resources\ContactLists\Pages;

use App\Filament\Marketing\Resources\ContactLists\ContactListResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditContactList extends EditRecord
{
    protected static string $resource = ContactListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()?->name ?? $data['updated_by'] ?? '';

        return $data;
    }
}
