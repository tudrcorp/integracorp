<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Pages;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentsResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditBusinessAppointments extends EditRecord
{
    protected static string $resource = BusinessAppointmentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;

        return $data;
    }
}
