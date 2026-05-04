<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Pages;

use App\Filament\Operations\Resources\OperationOnCallUsers\OperationOnCallUserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditOperationOnCallUser extends EditRecord
{
    protected static string $resource = OperationOnCallUserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()?->name ?? '—';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
