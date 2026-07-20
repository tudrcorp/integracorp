<?php

namespace App\Filament\Administration\Resources\TdevReports\Pages;

use App\Filament\Administration\Resources\TdevReports\TdevReportResource;
use App\Filament\Imports\TdevReportImporter;
use App\Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTdevReports extends ListRecords
{
    protected static string $resource = TdevReportResource::class;

    protected static ?string $title = 'Reporte de TDEV';

    /**
     * Estilo alineado con otros botones destacados del panel (theme.css).
     */
    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Importe el CSV oficial de TDEV y filtre por estatus desde la tabla. Las columnas ocultas se activan en el gestor de columnas.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(TdevReportImporter::class)
                ->label('Importar reporte CSV')
                ->modalHeading('Importar reporte TDEV')
                ->modalDescription('Asigne las columnas del archivo a los campos del sistema. Puede guardar el mapeo para importaciones futuras.')
                ->modalSubmitActionLabel('Iniciar importación')
                ->color('warning')
                ->icon('heroicon-s-cloud-arrow-up')
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),
        ];
    }
}
