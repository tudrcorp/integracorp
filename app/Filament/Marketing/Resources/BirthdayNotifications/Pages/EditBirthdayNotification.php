<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Pages;

use App\Filament\Marketing\Resources\BirthdayNotifications\BirthdayNotificationResource;
use App\Filament\Marketing\Resources\BirthdayNotifications\Schemas\BirthdayNotificationForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditBirthdayNotification extends EditRecord
{
    protected static string $resource = BirthdayNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return BirthdayNotificationForm::configure($schema);
    }
}
