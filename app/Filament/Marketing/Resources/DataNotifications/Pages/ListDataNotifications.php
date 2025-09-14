<?php

namespace App\Filament\Marketing\Resources\DataNotifications\Pages;

use App\Filament\Marketing\Resources\DataNotifications\DataNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDataNotifications extends ListRecords
{
    protected static string $resource = DataNotificationResource::class;

    protected static ?string $title = 'Gestión de Destinatarios';
}