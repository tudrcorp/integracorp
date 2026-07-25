<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Actions;

use App\Models\OperationInventoryUbication;
use App\Services\OperationInventoryProductStockAdjuster;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

final class BulkAdjustProductExistenceActions
{
    public static function increase(): BulkAction
    {
        return BulkAction::make('increase_product_existence')
            ->label('Aumentar existencia')
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Aumentar existencia de productos')
            ->modalDescription('La cantidad indicada se sumará a cada producto seleccionado en el almacén elegido. Quedará registrada como Reposición de Inventario.')
            ->modalSubmitActionLabel('Aumentar existencia')
            ->modalWidth(Width::Large)
            ->deselectRecordsAfterCompletion()
            ->form(self::sharedFormFields())
            ->action(function (Collection $records, array $data): void {
                try {
                    $result = app(OperationInventoryProductStockAdjuster::class)->increase(
                        $records,
                        (int) $data['operation_inventory_ubication_id'],
                        (int) $data['quantity'],
                    );

                    Notification::make()
                        ->title('Existencia aumentada')
                        ->body(sprintf(
                            'Se sumaron %d und. a %d producto(s) en %s (Reposición de Inventario).',
                            $result['quantity'],
                            $result['updated'],
                            $result['ubication'],
                        ))
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('No se pudo aumentar la existencia')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function decrease(): BulkAction
    {
        return BulkAction::make('decrease_product_existence')
            ->label('Restar existencia')
            ->icon('heroicon-o-minus-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Restar existencia de productos')
            ->modalDescription('La cantidad indicada se restará de cada producto seleccionado en el almacén elegido. Debe indicar el motivo; quedará registrada como Ajuste de Inventario.')
            ->modalSubmitActionLabel('Restar existencia')
            ->modalWidth(Width::Large)
            ->deselectRecordsAfterCompletion()
            ->form([
                ...self::sharedFormFields(),
                Textarea::make('note')
                    ->label('Motivo del ajuste')
                    ->placeholder('Ej.: merma, inventario físico, producto vencido, corrección de carga…')
                    ->helperText('Campo obligatorio. Se guardará en el movimiento de salida.')
                    ->required()
                    ->minLength(5)
                    ->maxLength(2000)
                    ->rows(3)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debes indicar el motivo del ajuste.',
                        'minLength' => 'El motivo debe tener al menos 5 caracteres.',
                    ]),
            ])
            ->action(function (Collection $records, array $data): void {
                try {
                    $result = app(OperationInventoryProductStockAdjuster::class)->decrease(
                        $records,
                        (int) $data['operation_inventory_ubication_id'],
                        (int) $data['quantity'],
                        (string) ($data['note'] ?? ''),
                    );

                    Notification::make()
                        ->title('Existencia ajustada')
                        ->body(sprintf(
                            'Se restaron %d und. a %d producto(s) en %s (Ajuste de Inventario).',
                            $result['quantity'],
                            $result['updated'],
                            $result['ubication'],
                        ))
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('No se pudo restar la existencia')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * @return array<int, Select|TextInput>
     */
    private static function sharedFormFields(): array
    {
        return [
            Select::make('operation_inventory_ubication_id')
                ->label('Almacén')
                ->options(fn (): array => OperationInventoryUbication::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->searchable()
                ->preload()
                ->helperText('La cantidad se aplicará a este almacén para todos los productos seleccionados.'),
            TextInput::make('quantity')
                ->label('Cantidad')
                ->numeric()
                ->minValue(1)
                ->required()
                ->default(1)
                ->suffix('und.')
                ->helperText(fn (): string => 'Se aplicará la misma cantidad a cada producto seleccionado.'),
        ];
    }
}
