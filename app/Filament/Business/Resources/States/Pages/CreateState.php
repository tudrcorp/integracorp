<?php

namespace App\Filament\Business\Resources\States\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Business\Resources\States\StateResource;


class CreateState extends CreateRecord
{
    protected static string $resource = StateResource::class;

    protected static ?string $title = 'Formulario de Creación de Estados';

    protected static bool $canCreateAnother = false;

}