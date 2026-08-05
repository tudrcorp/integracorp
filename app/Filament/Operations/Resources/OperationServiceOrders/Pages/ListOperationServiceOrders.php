<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Pages;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Services\OperationServiceOrderTableReportPdfService;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;

class ListOperationServiceOrders extends ListRecords
{
    protected static string $resource = OperationServiceOrderResource::class;

    protected static ?string $title = 'Gestión de Ordenes de Servicio';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report_orders_by_patient')
                ->label('Reporte por paciente')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('info')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ])
                ->modalHeading('Reporte detallado por paciente')
                ->modalDescription('Agrupa las órdenes del filtro/búsqueda actual por paciente para validar la cantidad de servicios.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon(Heroicon::OutlinedUserGroup)
                ->modalIconColor('info')
                ->modalContent(fn (): View => $this->tableReportModalContent(
                    OperationServiceOrderTableReportPdfService::TYPE_BY_PATIENT,
                    'Reporte detallado por paciente',
                    'Órdenes de servicio',
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null),
            Action::make('report_orders_by_service')
                ->label('Reporte por servicio')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('success')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ])
                ->modalHeading('Reporte por tipo de servicio')
                ->modalDescription('Contabiliza el número de servicios/órdenes del filtro/búsqueda actual por tipo de servicio.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon(Heroicon::OutlinedChartBar)
                ->modalIconColor('success')
                ->modalContent(fn (): View => $this->tableReportModalContent(
                    OperationServiceOrderTableReportPdfService::TYPE_BY_SERVICE,
                    'Reporte por tipo de servicio',
                    'Conteo de servicios realizados',
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null),
        ];
    }

    private function tableReportModalContent(string $type, string $title, string $subtitle): View
    {
        $ids = $this->getFilteredTableQuery()
            ->clone()
            ->reorder()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            Notification::make()
                ->warning()
                ->title('Sin registros para el reporte')
                ->body('No hay órdenes con el filtro o búsqueda actual. Ajusta los filtros e intenta de nuevo.')
                ->send();
        }

        $token = OperationServiceOrderTableReportPdfService::storeIdsAndGetToken($ids);

        return ViewFactory::make('filament.operations.service-orders.table-report-modal', [
            'title' => $title,
            'subtitle' => $subtitle,
            'pdfPreviewUrl' => route('operations.operation-service-orders.report.preview', [
                'token' => $token,
                'type' => $type,
            ]),
            'pdfDownloadUrl' => route('operations.operation-service-orders.report.download', [
                'token' => $token,
                'type' => $type,
            ]),
        ]);
    }
}
