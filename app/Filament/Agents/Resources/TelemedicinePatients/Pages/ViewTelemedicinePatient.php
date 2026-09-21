<?php

declare(strict_types=1);

namespace App\Filament\Agents\Resources\TelemedicinePatients\Pages;

use App\Filament\Agents\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicinePatient;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewTelemedicinePatient extends ViewRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    public function getTitle(): string|Htmlable
    {
        $patient = $this->getRecord();

        if (! $patient instanceof TelemedicinePatient) {
            return 'Ficha del paciente';
        }

        $name = trim((string) $patient->full_name);

        return $name !== '' ? mb_strtoupper($name) : 'Ficha del paciente';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
