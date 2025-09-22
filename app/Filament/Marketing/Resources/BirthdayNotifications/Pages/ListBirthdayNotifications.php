<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Pages;

use App\Filament\Marketing\Resources\BirthdayNotifications\BirthdayNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBirthdayNotifications extends ListRecords
{
    protected static string $resource = BirthdayNotificationResource::class;

    protected static ?string $title = 'Notificaciones de Cumpleaños';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Notificación')
                ->icon('heroicon-s-plus')
        ];
    }
}