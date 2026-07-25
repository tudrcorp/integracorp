<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Actions;

use App\Http\Controllers\OperationInventoryProductExportCsvController;
use App\Models\OperationInventoryProductCategory;
use App\Models\OperationInventoryUbication;
use App\Support\Exports\OperationInventoryProductCsvExportService;
use App\Support\Filament\CsvExportDownloadTrigger;
use App\Support\SecurityAudit;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

final class ExportProductsCsvBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('export_products_csv')
            ->label('Exportar CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->modalHeading('Exportar productos a CSV')
            ->modalDescription('Todos los filtros son opcionales. Si los deja en blanco se exporta el catálogo completo sin restricciones.')
            ->modalIcon(Heroicon::OutlinedDocumentChartBar)
            ->modalIconColor('success')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Descargar CSV')
            ->modalCancelActionLabel('Cancelar')
            ->successNotification(null)
            ->deselectRecordsAfterCompletion()
            ->form([
                Select::make('category_id')
                    ->label('Categoría')
                    ->placeholder('Todas las categorías')
                    ->options(fn (): array => OperationInventoryProductCategory::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                Select::make('ubication_id')
                    ->label('Almacén')
                    ->placeholder('Todos los almacenes')
                    ->options(fn (): array => OperationInventoryUbication::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->helperText('Si elige un almacén, la existencia del reporte corresponde a ese almacén.'),
                Select::make('existence_operator')
                    ->label('Existencia')
                    ->placeholder('Sin filtro de existencia')
                    ->options(OperationInventoryProductCsvExportService::existenceOperatorOptions())
                    ->live(),
                TextInput::make('existence_value')
                    ->label('Cantidad de referencia')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('und.')
                    ->placeholder('Ej.: 0, 10, 50…')
                    ->visible(fn (Get $get): bool => filled($get('existence_operator')))
                    ->required(fn (Get $get): bool => filled($get('existence_operator')))
                    ->helperText('Se aplica según “Mayor a” o “Menor a”.'),
            ])
            ->action(function (Collection $records, array $data, BulkAction $action): void {
                $filters = [
                    'category_id' => $data['category_id'] ?? null,
                    'ubication_id' => $data['ubication_id'] ?? null,
                    'existence_operator' => $data['existence_operator'] ?? null,
                    'existence_value' => $data['existence_value'] ?? null,
                ];

                SecurityAudit::log('AUDIT_OPERATIONS_INVENTORY_PRODUCTS_CSV_EXPORT', 'operations.inventory-products.export-csv', [
                    ...$filters,
                    'selected_records_count' => $records->count(),
                    'exported_by_user_id' => auth()->id(),
                ]);

                $token = OperationInventoryProductExportCsvController::storeFiltersAndGetToken($filters);

                CsvExportDownloadTrigger::fromAction(
                    $action,
                    route('operations.inventory-products.export-csv', ['token' => $token]),
                );
            });
    }
}
