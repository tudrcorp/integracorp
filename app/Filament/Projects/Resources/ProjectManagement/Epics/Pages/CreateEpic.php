<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Epics\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Epics\EpicResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEpic extends CreateRecord
{
    protected static string $resource = EpicResource::class;

    protected static ?string $title = 'Crear épica';
}
