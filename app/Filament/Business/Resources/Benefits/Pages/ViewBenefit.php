<?php

namespace App\Filament\Business\Resources\Benefits\Pages;

use App\Filament\Business\Resources\Benefits\BenefitResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBenefit extends ViewRecord
{
    protected static string $resource = BenefitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
