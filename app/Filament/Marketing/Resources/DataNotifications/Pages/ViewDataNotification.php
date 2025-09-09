<?php

namespace App\Filament\Marketing\Resources\DataNotifications\Pages;

use App\Filament\Marketing\Resources\DataNotifications\DataNotificationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDataNotification extends ViewRecord
{
    protected static string $resource = DataNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
