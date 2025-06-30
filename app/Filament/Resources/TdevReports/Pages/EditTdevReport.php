<?php

namespace App\Filament\Resources\TdevReports\Pages;

use App\Filament\Resources\TdevReports\TdevReportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTdevReport extends EditRecord
{
    protected static string $resource = TdevReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
