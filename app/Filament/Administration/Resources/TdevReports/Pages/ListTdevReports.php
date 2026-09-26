<?php

namespace App\Filament\Administration\Resources\TdevReports\Pages;

use App\Filament\Actions\ImportAction;
use App\Filament\Administration\Resources\TdevReports\TdevReportResource;
use App\Filament\Imports\TdevReportImporter;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListTdevReports extends ListRecords
{
    protected static string $resource = TdevReportResource::class;

    protected static ?string $title = 'Reporte de TDEV';

    /**
     * Estilo alineado con otros botones destacados del panel (theme.css).
     */
    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Mismo encabezado de marca que Compensación de voucher: logo de Tu Doctor
     * En Viajes, sobretítulo, título y explicación en un solo bloque. El título
     * de la pestaña del navegador sigue saliendo de `$title`.
     */
    public function getHeading(): string|Htmlable
    {
        return new HtmlString(
            <<<'HTML'
            <div class="flex flex-col items-start gap-3 py-1">
                <img src="/image/logo-tdev.png" alt="Tu Doctor En Viajes" class="h-16 w-auto max-w-[12rem] object-contain drop-shadow-md sm:h-20 sm:max-w-[14rem]">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-cyan-700 dark:text-cyan-300">
                        Tu Doctor En Viajes
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                        Reporte de TDEV
                    </h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Importe el CSV oficial de TDEV y filtre por estatus desde la tabla. Las columnas ocultas se activan en el gestor de columnas.
                    </p>
                </div>
            </div>
            HTML
        );
    }

    /**
     * La explicación ya va dentro del encabezado de marca.
     */
    public function getSubheading(): string|Htmlable|null
    {
        return null;
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
