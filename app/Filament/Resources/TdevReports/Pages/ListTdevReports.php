<?php

namespace App\Filament\Resources\TdevReports\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Imports\TdevReportImporter;
use App\Filament\Resources\TdevReports\TdevReportResource;

class ListTdevReports extends ListRecords
{
    protected static string $resource = TdevReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
            ImportAction::make()
                ->importer(TdevReportImporter::class)
                ->label('Importar reporte CSV')
                ->color('warning')
                ->icon('heroicon-s-cloud-arrow-up'),
            
        ];
    }
}