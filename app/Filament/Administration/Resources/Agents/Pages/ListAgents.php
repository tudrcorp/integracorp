<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agents\Pages;

use App\Filament\Administration\Resources\Agents\AgentResource;
use App\Filament\Administration\Resources\Agents\Tables\AgentsTable;
use App\Services\AdministrationAgentReportsExportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Listado de Agentes';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return AgentsTable::getTabs();
    }

    protected function getHeaderActions(): array
    {
        $reports = $this->agentReportModalItems();

        return [
            Action::make('export_commission_hierarchy')
                ->label('Comisiones por jerarquía (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color(self::PRIMARY_BUTTON_CLASS)
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->tooltip('Descarga un CSV con la jerarquía lineal de cada agente y los porcentajes TDEC/TDEV de cada integrante.')
                ->action(fn (): StreamedResponse => AdministrationAgentReportsExportService::toCsv(
                    AdministrationAgentReportsExportService::REPORT_COMMISSION_HIERARCHY,
                )),
            Action::make('agentReports')
                ->label('Reportes')
                ->icon(Heroicon::OutlinedChartBarSquare)
                ->color(self::PRIMARY_BUTTON_CLASS)
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
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
            AdministrationAgentReportsExportService::REPORT_COMMISSION_HIERARCHY => 'Jerarquía lineal por agente (casa matriz, agencias, superiores) con % TDEC y TDEV de cada integrante.',
            AdministrationAgentReportsExportService::REPORT_AGENT_STATUS => 'Distribución de agentes por estatus operativo.',
        ];

        $icons = [
            AdministrationAgentReportsExportService::REPORT_GEO_SUMMARY => 'map',
            AdministrationAgentReportsExportService::REPORT_COMMISSION_PERCENTAGES => 'percent',
            AdministrationAgentReportsExportService::REPORT_COMMISSION_HIERARCHY => 'hierarchy',
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
