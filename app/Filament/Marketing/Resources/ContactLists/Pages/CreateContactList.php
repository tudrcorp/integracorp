<?php

namespace App\Filament\Marketing\Resources\ContactLists\Pages;

use App\Filament\Marketing\Resources\ContactLists\ContactListResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateContactList extends CreateRecord
{
    protected static string $resource = ContactListResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $name = Auth::user()?->name ?? '';
        $data['created_by'] = $data['created_by'] ?? $name;
        $data['updated_by'] = $data['updated_by'] ?? $name;

        return $data;
    }
}
