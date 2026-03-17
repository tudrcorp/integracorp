<?php

namespace App\Filament\Business\Resources\ConfigCostoBenefits\Pages;

use App\Filament\Business\Resources\ConfigCostoBenefits\ConfigCostoBenefitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConfigCostoBenefit extends EditRecord
{
    protected static string $resource = ConfigCostoBenefitResource::class;

    protected static ?string $title = 'Editar Porcentajes de Beneficios';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
