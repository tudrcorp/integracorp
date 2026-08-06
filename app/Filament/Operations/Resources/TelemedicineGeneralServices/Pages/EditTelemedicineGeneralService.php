<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineGeneralServices\Pages;

use App\Filament\Operations\Resources\TelemedicineGeneralServices\Actions\DeleteTelemedicineGeneralServiceAction;
use App\Filament\Operations\Resources\TelemedicineGeneralServices\TelemedicineGeneralServiceResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditTelemedicineGeneralService extends EditRecord
{
    protected static string $resource = TelemedicineGeneralServiceResource::class;

    protected static ?string $title = 'Editar Servicio de Consulta General';

    protected function getHeaderActions(): array
    {
        return [
            DeleteTelemedicineGeneralServiceAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['name'] = mb_strtoupper(trim((string) ($data['name'] ?? '')));
        $data['updated_by'] = (string) Auth::id();

        return $data;
    }
}
