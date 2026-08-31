<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use App\Models\TelemedicineDoctor;
use App\Support\Filament\TelemedicineDoctorPageHeader;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditTelemedicineDoctor extends EditRecord
{
    protected static string $resource = TelemedicineDoctorResource::class;

    public function getTitle(): string|Htmlable
    {
        $doctor = $this->getRecord();

        return $doctor instanceof TelemedicineDoctor
            ? TelemedicineDoctorPageHeader::forDoctor($doctor, context: 'profile')
            : 'Mi perfil médico';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
