<?php

namespace App\Filament\Business\Resources\BenefitCoverages\Pages;

use App\Filament\Business\Resources\BenefitCoverages\BenefitCoverageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBenefitCoverage extends ViewRecord
{
    protected static string $resource = BenefitCoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
