<?php

namespace App\Filament\Marketing\Resources\DataNotifications\Pages;

use App\Filament\Marketing\Resources\DataNotifications\DataNotificationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDataNotification extends EditRecord
{
    protected static string $resource = DataNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
