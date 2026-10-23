<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Pages;

use App\Filament\Marketing\Resources\BirthdayNotifications\BirthdayNotificationResource;
use App\Filament\Marketing\Resources\BirthdayNotifications\Schemas\BirthdayNotificationForm;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreateBirthdayNotification extends CreateRecord
{
    protected static string $resource = BirthdayNotificationResource::class;

    public function form(Schema $schema): Schema
    {
        return BirthdayNotificationForm::configure($schema);
    }
}
