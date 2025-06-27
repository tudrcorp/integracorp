<?php

namespace App\Filament\Resources\Logs\Pages;

use App\Filament\Resources\Logs\LogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLogs extends ListRecords
{
    protected static string $resource = LogResource::class;

    protected static ?string $title = 'ACTIVIDAD DE SISTEMA';

}