<?php

namespace App\Filament\Administration\Resources\RrhhDepartamentos\Pages;

use App\Filament\Administration\Resources\RrhhDepartamentos\RrhhDepartamentoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRrhhDepartamento extends CreateRecord
{
    protected static string $resource = RrhhDepartamentoResource::class;

    protected static ?string $title = "Agregar Departamento";
}
