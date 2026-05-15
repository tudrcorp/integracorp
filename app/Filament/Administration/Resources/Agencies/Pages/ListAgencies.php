<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agencies\Pages;

use App\Filament\Administration\Resources\Agencies\AgencyResource;
use App\Services\AdministrationAgencyReportsExportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

class ListAgencies extends ListRecords
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Gestión de Agencias';

    protected function getHeaderActions(): array
    {
        $reports = $this->agencyReportModalItems();

        return [
            Action::make('agencyReports')
                ->label('Reportes')
                ->icon(Heroicon::OutlinedChartBarSquare)
                ->color('success')
                ->modalHeading('Reportes de agencias')
                ->modalDescription('Descarga informes listos para análisis. Elige el tipo de reporte y el formato que prefieras.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalContent(fn (): ViewContract => View::make(
                    'filament.administration.agencies.agency-reports-export-modal',
                    ['reports' => $reports],
                )),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, icon: string, csvUrl: string, xlsxUrl: string}>
     */
    private function agencyReportModalItems(): array
    {
        $descriptions = [
            AdministrationAgencyReportsExportService::REPORT_COMMISSION_PERCENTAGES => 'Listado con porcentajes TDEC/TDEV, renovaciones, estatus y datos bancarios nacionales e internacionales por agencia.',
            AdministrationAgencyReportsExportService::REPORT_GEO_SUMMARY => 'Totales agrupados por estado, región y ciudad.',
            AdministrationAgencyReportsExportService::REPORT_AGENCY_TYPES => 'Conteo de agencias según su tipo comercial.',
            AdministrationAgencyReportsExportService::REPORT_AGENCY_STATUS => 'Distribución de agencias por estatus operativo.',
        ];

        $icons = [
            AdministrationAgencyReportsExportService::REPORT_COMMISSION_PERCENTAGES => 'percent',
            AdministrationAgencyReportsExportService::REPORT_GEO_SUMMARY => 'map',
            AdministrationAgencyReportsExportService::REPORT_AGENCY_TYPES => 'tag',
            AdministrationAgencyReportsExportService::REPORT_AGENCY_STATUS => 'status',
        ];

        $items = [];

        foreach (AdministrationAgencyReportsExportService::reportLabels() as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'description' => $descriptions[$key] ?? '',
                'icon' => $icons[$key] ?? 'default',
                'csvUrl' => route('administration.agencies.reports.export', [
                    'report' => $key,
                    'format' => 'csv',
                ]),
                'xlsxUrl' => route('administration.agencies.reports.export', [
                    'report' => $key,
                    'format' => 'xlsx',
                ]),
            ];
        }

        return $items;
    }
}
