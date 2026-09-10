<?php

declare(strict_types=1);

namespace App\Filament\Master\Resources\TelemedicineCases\Pages;

use App\Filament\Master\Resources\TelemedicineCases\TelemedicineCaseResource;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineCases extends ListRecords
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Gestión de casos';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
