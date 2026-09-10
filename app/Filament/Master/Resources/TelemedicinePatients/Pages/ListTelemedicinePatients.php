<?php

declare(strict_types=1);

namespace App\Filament\Master\Resources\TelemedicinePatients\Pages;

use App\Filament\Master\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicinePatients extends ListRecords
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Pacientes de sus afiliados';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
