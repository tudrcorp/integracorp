<?php

namespace App\Filament\Operations\Resources\CorporateAllies\Pages;

use App\Filament\Operations\Resources\CorporateAllies\CorporateAllyResource;
use App\Models\Country;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCorporateAlly extends CreateRecord
{
    protected static string $resource = CorporateAllyResource::class;

    protected static ?string $title = 'Crear Aliado Corporativo';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($data['country_id'] ?? null) && blank($data['country_code'] ?? null)) {
            $data['country_code'] = Country::query()->whereKey($data['country_id'])->value('code');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Aliado corporativo creado')
            ->body('El aliado corporativo ha sido creado exitosamente.');
    }
}
