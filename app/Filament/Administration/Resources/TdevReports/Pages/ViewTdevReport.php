<?php

namespace App\Filament\Administration\Resources\TdevReports\Pages;

use App\Filament\Administration\Resources\TdevReports\TdevReportResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTdevReport extends ViewRecord
{
    protected static string $resource = TdevReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
