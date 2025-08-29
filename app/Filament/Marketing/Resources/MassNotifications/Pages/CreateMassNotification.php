<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Pages;

use App\Filament\Marketing\Resources\MassNotifications\MassNotificationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMassNotification extends CreateRecord
{
    protected static string $resource = MassNotificationResource::class;

    protected static ?string $title = 'Notificaciones Individuales y Masivas';

    protected function getFormActions(): array
    {
        return [];
    }
}