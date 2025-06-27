<?php

namespace App\Filament\Resources\Agencies\Pages;

use App\Filament\Resources\Agencies\AgencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Crear Agencia';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['commission_tdec']            = $data['commission_tdec']          != null ? $data['commission_tdec'] : 0.00;
        $data['commission_tdec_renewal']    = $data['commission_tdec_renewal']  != null ? $data['commission_tdec_renewal'] : 0.00;
        $data['commission_tdev']            = $data['commission_tdev']          != null ? $data['commission_tdev'] : 0.00;
        $data['commission_tdev_renewal']    = $data['commission_tdev_renewal']  != null ? $data['commission_tdev_renewal'] : 0.00;
        return $data;
    }
}