<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineGeneralServices\Pages;

use App\Filament\Operations\Resources\TelemedicineGeneralServices\TelemedicineGeneralServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTelemedicineGeneralService extends CreateRecord
{
    protected static string $resource = TelemedicineGeneralServiceResource::class;

    protected static ?string $title = 'Crear Servicio de Consulta General';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['name'] = mb_strtoupper(trim((string) ($data['name'] ?? '')));
        $data['created_by'] = (string) Auth::id();
        $data['updated_by'] = (string) Auth::id();

        return $data;
    }
}
