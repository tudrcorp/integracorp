<?php

namespace App\Filament\Business\Resources\ConfigCostoBenefits\Pages;

use App\Filament\Business\Resources\ConfigCostoBenefits\ConfigCostoBenefitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConfigCostoBenefits extends ListRecords
{
    protected static string $resource = ConfigCostoBenefitResource::class;

    protected static ?string $title = 'Configuración de Porcentajes para Beneficios';

}
