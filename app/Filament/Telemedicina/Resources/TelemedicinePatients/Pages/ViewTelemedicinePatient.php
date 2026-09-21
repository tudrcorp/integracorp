<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicinePatient;
use App\Support\Filament\TelemedicinePatientPageHeader;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ViewTelemedicinePatient extends ViewRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var TelemedicinePatient $record */
        $record = parent::resolveRecord($key);
        $record->loadMissing([
            'plan:id,description',
            'plan.benefitPlans.limit:id,description',
            'plan.clinicalSettings',
        ]);

        return $record;
    }

    public function getTitle(): string|Htmlable
    {
        $patient = $this->getRecord();

        return $patient instanceof TelemedicinePatient
            ? TelemedicinePatientPageHeader::forPatient($patient)
            : 'Ficha del paciente';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Volver')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(TelemedicinePatientResource::getUrl('index')),
        ];
    }
}
