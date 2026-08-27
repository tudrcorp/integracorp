<?php

namespace App\Filament\Resources\Agencies\Pages;

use App\Filament\Resources\Agencies\AgencyResource;
use App\Filament\Shared\CommercialStructure\Concerns\SyncsReferidorAssignments;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditAgency extends EditRecord
{
    use SyncsReferidorAssignments;

    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'EDITAR AGENCIA';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillReferidorAssignmentState($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->captureReferidorAssignments($data);
    }

    protected function AfterSave()
    {
        $this->persistCapturedReferidorAssignments();

        if ($this->record->agency_type_id == 1) {
            $this->record->update([
                'owner_code' => 'TDG-100',
            ]);
        }

        /**
         * Actualizo el usuario de la agencia
         * para que pueda acceder al portal
         * de las agencias tipo master
         */
        User::select('id', 'code_agency', 'agency_type')
            ->where('code_agency', $this->record->code)
            ->update([
                'agency_type' => 'MASTER',
            ]);
    }
}
