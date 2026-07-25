<?php

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Actions;

use App\Models\OperationInventory;
use App\Models\OperationInventoryEntry;
use App\Models\OperationInventoryOutflow;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryType;
use App\Models\OperationInventoryUbication;
use App\Services\OperationInventoryLowStockWatcher;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class LoadProductExistenceAction
{
    public static function make(): Action
    {
        return Action::make('load_product_existence')
            ->label('Cargar existencia')
            ->icon('heroicon-o-archive-box')
            ->color('primary')
            ->modalHeading('Cargar existencia por almacén')
            ->modalDescription('Indique la existencia del producto en cada almacén activo. Los almacenes nuevos aparecen automáticamente aquí.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Guardar existencias')
            ->fillForm(function (OperationInventoryProduct $record): array {
                $stocks = $record->stocks()
                    ->pluck('existence', 'operation_inventory_ubication_id');

                $data = [];

                foreach (self::activeUbications() as $ubication) {
                    $data["ubication_{$ubication->id}"] = (int) ($stocks[$ubication->id] ?? 0);
                }

                return $data;
            })
            ->form(function (): array {
                $ubications = self::activeUbications();

                if ($ubications->isEmpty()) {
                    return [
                        Placeholder::make('no_ubications')
                            ->label('Sin almacenes')
                            ->content(new HtmlString(
                                'No hay almacenes activos. Cree uno en <strong>Inventario Diagnomovil → Almacenes</strong>.'
                            )),
                    ];
                }

                return $ubications
                    ->map(fn (OperationInventoryUbication $ubication): TextInput => TextInput::make("ubication_{$ubication->id}")
                        ->label($ubication->name)
                        ->helperText(filled($ubication->address) ? (string) $ubication->address : null)
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(0)
                        ->suffix('unidades'))
                    ->values()
                    ->all();
            })
            ->action(function (array $data, OperationInventoryProduct $record): void {
                $ubications = self::activeUbications();

                if ($ubications->isEmpty()) {
                    Notification::make()
                        ->title('No hay almacenes activos')
                        ->body('Debe crear al menos un almacén activo antes de cargar existencias.')
                        ->warning()
                        ->send();

                    return;
                }

                $userName = Auth::user()?->name ?? 'system';
                $typeId = (int) (OperationInventoryType::query()->orderBy('id')->value('id') ?? 1);
                $previousTotal = $record->totalExistence();

                foreach ($ubications as $ubication) {
                    $key = "ubication_{$ubication->id}";
                    $existence = max(0, (int) ($data[$key] ?? 0));

                    $stock = OperationInventoryProductStock::query()->firstOrNew([
                        'operation_inventory_product_id' => $record->id,
                        'operation_inventory_ubication_id' => $ubication->id,
                    ]);

                    $previous = $stock->exists ? (int) $stock->existence : 0;

                    if (! $stock->exists) {
                        $stock->created_by = $userName;
                    }

                    $stock->existence = $existence;
                    $stock->updated_by = $userName;
                    $stock->save();

                    $inventory = OperationInventory::query()->updateOrCreate(
                        [
                            'operation_inventory_product_id' => $record->id,
                            'operation_inventory_ubication_id' => $ubication->id,
                        ],
                        [
                            'name' => $record->name,
                            'unit' => $record->unit,
                            'operation_inventory_type_id' => $typeId,
                            'existence' => $existence,
                            'cost' => $record->cost,
                            'ubication' => $ubication->name,
                            'barcode' => $record->code,
                            'min_stock' => 0,
                            'is_active' => true,
                            'created_by' => $userName,
                            'updated_by' => $userName,
                        ],
                    );

                    $delta = $existence - $previous;

                    if ($delta > 0) {
                        OperationInventoryEntry::query()->create([
                            'operation_inventory_id' => $inventory->id,
                            'operation_inventory_product_id' => $record->id,
                            'operation_inventory_ubication_id' => $ubication->id,
                            'operation_inventory_type_id' => $typeId,
                            'quantity' => $delta,
                            'type_entry' => $previous === 0 ? 'PRIMERA CARGA' : 'REPOSICIÓN DE INVENTARIO',
                            'created_by' => $userName,
                        ]);
                    }

                    if ($delta < 0) {
                        OperationInventoryOutflow::query()->create([
                            'operation_inventory_id' => $inventory->id,
                            'operation_inventory_product_id' => $record->id,
                            'operation_inventory_ubication_id' => $ubication->id,
                            'operation_inventory_type_id' => $typeId,
                            'quantity' => abs($delta),
                            'type_entry' => 'AJUSTE DE EXISTENCIA',
                            'created_by' => $userName,
                        ]);
                    }
                }

                app(OperationInventoryLowStockWatcher::class)
                    ->dispatchIfCrossedThreshold((int) $record->id, $previousTotal);

                Notification::make()
                    ->title('Existencias actualizadas')
                    ->body('La existencia del producto se guardó en '.$ubications->count().' almacén(es).')
                    ->success()
                    ->send();
            })
            ->after(function ($livewire): void {
                $livewire->record->refresh();
                $livewire->record->load(['category', 'stocks.ubication']);
            });
    }

    /**
     * @return Collection<int, OperationInventoryUbication>
     */
    public static function activeUbications(): Collection
    {
        return OperationInventoryUbication::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
