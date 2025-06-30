<?php

namespace App\Filament\Resources\TdevReports\Pages;

use App\Filament\Resources\TdevReports\TdevReportResource;
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
