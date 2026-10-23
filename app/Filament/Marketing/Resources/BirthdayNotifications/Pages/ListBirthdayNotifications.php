<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Pages;

use App\Filament\Marketing\Resources\BirthdayNotifications\BirthdayNotificationResource;
use App\Filament\Marketing\Resources\BirthdayNotifications\Tables\BirthdayNotificationsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListBirthdayNotifications extends ListRecords
{
    protected static string $resource = BirthdayNotificationResource::class;

    protected static ?string $title = 'Notificaciones de Cumpleaños';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Notificación')
                ->icon('heroicon-s-plus'),
        ];
    }

    public function table(Table $table): Table
    {
        return BirthdayNotificationsTable::configure($table)
            ->heading('Listado de notificaciones de cumpleaños')
            ->description('Plantillas por grupo de destinatarios y la tarjeta animada. Aprueba cada una para que entre en el envío diario.');
    }
}
