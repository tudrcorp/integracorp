<?php

namespace App\Filament\Administration\Resources\RrhhCargos\Pages;

use App\Filament\Administration\Resources\RrhhCargos\RrhhCargoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRrhhCargo extends CreateRecord
{
    protected static string $resource = RrhhCargoResource::class;

    protected static ?string $title = "Agregar Cargo";
}
