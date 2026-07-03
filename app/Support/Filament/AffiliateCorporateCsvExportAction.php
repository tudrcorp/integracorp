<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Http\Controllers\AffiliateCorporateExportCsvController;
use App\Models\Plan;
use App\Support\Exports\AffiliateCorporateCsvExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

final class AffiliateCorporateCsvExportAction
{
    public static function make(
        string $name = 'exportAffiliateCorporatesCsv',
        string $exportRoute = 'business.affiliate-corporates.export-csv',
        string $panel = 'business',
        string $label = 'Exportar CSV',
        string $modalHeading = 'Exportar afiliados corporativos',
        string $modalDescription = 'Descarga un archivo .csv con los afiliados corporativos. Los filtros son opcionales.',
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->button()
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
            ->modalSubmitAction(fn (Action $submitAction): Action => $submitAction
                ->icon(Heroicon::OutlinedArrowDownTray))
            ->modalCancelActionLabel('Cancelar')
            ->successNotification(null)
            ->action(function (array $data, Action $action) use ($exportRoute, $panel): void {
                $token = AffiliateCorporateExportCsvController::storeFiltersAndGetToken([
                    'plan_id' => $data['plan_id'] ?? null,
                    'status' => $data['status'] ?? null,
                ], $panel);

                CsvExportDownloadTrigger::fromAction(
                    $action,
                    route($exportRoute, ['token' => $token]),
                );
            });
    }
}
