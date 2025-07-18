<?php

namespace App\Filament\Resources\MassNotifications\Pages;

use App\Filament\Resources\MassNotifications\MassNotificationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMassNotification extends ViewRecord
{
    protected static string $resource = MassNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
