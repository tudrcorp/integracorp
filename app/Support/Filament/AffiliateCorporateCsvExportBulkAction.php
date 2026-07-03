<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Http\Controllers\AffiliateCorporateExportCsvController;
use App\Models\Plan;
use App\Support\Exports\AffiliateCorporateCsvExportService;
use App\Support\SecurityAudit;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

final class AffiliateCorporateCsvExportBulkAction
{
    public static function make(
        string $name = 'exportAffiliateCorporatesCsv',
        string $exportRoute = 'business.affiliate-corporates.export-csv',
        string $panel = 'business',
        string $label = 'Exportar CSV afiliados',
        string $modalHeading = 'Exportar afiliados corporativos',
        string $modalDescription = 'Descarga un archivo .csv con los afiliados de las afiliaciones seleccionadas. Los filtros son opcionales.',
        string $emptySelectionTitle = 'Selecciona al menos una afiliación',
        string $emptySelectionBody = 'Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.',
    ): BulkAction {
        return BulkAction::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->modalHeading($modalHeading)
            ->modalDescription($modalDescription)
            ->modalIcon(Heroicon::OutlinedDocumentChartBar)
            ->modalIconColor('success')
            ->modalWidth(Width::Large)
            ->form([
                Select::make('plan_id')
                    ->label('Tipo de plan')
                    ->placeholder('Todos los planes')
                    ->options(fn (): array => Plan::query()
                        ->orderBy('description')
                        ->pluck('description', 'id')
                        ->all())
                    ->searchable(),
                Select::make('status')
                    ->label('Estatus de afiliado')
                    ->placeholder('Todos los estatus')
                    ->options(AffiliateCorporateCsvExportService::statusOptions()),
            ])
            ->modalSubmitActionLabel('Descargar CSV')
            ->modalSubmitAction(fn (BulkAction $submitAction): BulkAction => $submitAction
                ->icon(Heroicon::OutlinedArrowDownTray))
            ->modalCancelActionLabel('Cancelar')
            ->successNotification(null)
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, BulkAction $action) use ($exportRoute, $panel, $emptySelectionTitle, $emptySelectionBody): void {
                if ($records->isEmpty()) {
                    Notification::make()
                        ->warning()
                        ->title($emptySelectionTitle)
                        ->body($emptySelectionBody)
                        ->send();

                    return;
                }

                [$auditEvent, $auditRoute] = AffiliateCorporateExportCsvController::auditMetadataForPanel($panel);

                SecurityAudit::log($auditEvent, $auditRoute, [
                    'plan_id' => $data['plan_id'] ?? null,
                    'status' => $data['status'] ?? null,
                    'affiliation_corporate_ids_count' => $records->count(),
                    'exported_by_user_id' => auth()->id(),
                    'panel' => $panel,
                ]);

                $token = AffiliateCorporateExportCsvController::storeFiltersAndGetToken([
                    'plan_id' => $data['plan_id'] ?? null,
                    'status' => $data['status'] ?? null,
                    'affiliation_corporate_ids' => $records->pluck('id')->values()->all(),
                ], $panel);

                CsvExportDownloadTrigger::fromAction(
                    $action,
                    route($exportRoute, ['token' => $token]),
                );
            });
    }
}
