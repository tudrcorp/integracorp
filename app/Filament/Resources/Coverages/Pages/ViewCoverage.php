<?php

namespace App\Filament\Resources\Coverages\Pages;

use App\Filament\Resources\Coverages\CoverageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCoverage extends ViewRecord
{
    protected static string $resource = CoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
