<?php

namespace App\Filament\Resources\MassNotifications\Pages;

use App\Filament\Resources\MassNotifications\MassNotificationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMassNotification extends EditRecord
{
    protected static string $resource = MassNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
