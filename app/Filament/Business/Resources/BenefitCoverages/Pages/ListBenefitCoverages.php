<?php

namespace App\Filament\Business\Resources\BenefitCoverages\Pages;

use App\Filament\Business\Resources\BenefitCoverages\BenefitCoverageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBenefitCoverages extends ListRecords
{
    protected static string $resource = BenefitCoverageResource::class;

    protected static ?string $label = 'Limites de Uso';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}