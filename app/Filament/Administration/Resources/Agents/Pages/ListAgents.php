<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agents\Pages;

use App\Filament\Administration\Resources\Agents\AgentResource;
use App\Services\AdministrationAgentReportsExportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Listado de Agentes';

    protected function getHeaderActions(): array
    {
        $reports = $this->agentReportModalItems();

        return [
            Action::make('agentReports')
                ->label('Reportes')
                ->icon(Heroicon::OutlinedChartBarSquare)
                ->color('success')
                ->modalHeading('Reportes de agentes')
                ->modalDescription('Descarga informes listos para análisis. Elige el tipo de reporte y el formato que prefieras.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalContent(fn (): ViewContract => View::make(
                    'filament.administration.agents.agent-reports-export-modal',
                    ['reports' => $reports],
                )),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, icon: string, csvUrl: string, xlsxUrl: string}>
     */
    private function agentReportModalItems(): array
    {
        $descriptions = [
            AdministrationAgentReportsExportService::REPORT_GEO_SUMMARY => 'Totales agrupados por estado, región y ciudad.',
            AdministrationAgentReportsExportService::REPORT_COMMISSION_PERCENTAGES => 'Listado con porcentajes TDEC/TDEV, renovaciones, estatus y datos bancarios nacionales e internacionales por agente.',
            AdministrationAgentReportsExportService::REPORT_AGENT_STATUS => 'Distribución de agentes por estatus operativo.',
        ];

        $icons = [
            AdministrationAgentReportsExportService::REPORT_GEO_SUMMARY => 'map',
            AdministrationAgentReportsExportService::REPORT_COMMISSION_PERCENTAGES => 'percent',
            AdministrationAgentReportsExportService::REPORT_AGENT_STATUS => 'status',
        ];

        $items = [];

        foreach (AdministrationAgentReportsExportService::reportLabels() as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'description' => $descriptions[$key] ?? '',
                'icon' => $icons[$key] ?? 'default',
                'csvUrl' => route('administration.agents.reports.export', [
                    'report' => $key,
                    'format' => 'csv',
                ]),
                'xlsxUrl' => route('administration.agents.reports.export', [
                    'report' => $key,
                    'format' => 'xlsx',
                ]),
            ];
        }

        return $items;
    }
}
