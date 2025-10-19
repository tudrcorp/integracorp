<?php

namespace App\Filament\Business\Resources\BenefitCoverages\Pages;

use App\Filament\Business\Resources\BenefitCoverages\BenefitCoverageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBenefitCoverage extends EditRecord
{
    protected static string $resource = BenefitCoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
