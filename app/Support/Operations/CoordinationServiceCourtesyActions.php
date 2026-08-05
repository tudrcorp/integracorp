<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Support\Filament\FilamentIosButton;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

final class CoordinationServiceCourtesyActions
{
    public static function makeMarkBulkAction(): BulkAction
    {
        return self::makeBulkAction(
            name: 'mark_coordination_items_courtesy',
            label: 'Marcar cortesía',
            heading: 'Marcar ítems como CORTESÍA',
            description: 'Seleccione uno o varios ítems de las coordinaciones elegidas. Quedarán con segundo estatus CORTESÍA y se registrará en la bitácora del caso.',
            mark: true,
            icon: Heroicon::OutlinedGift,
            color: 'success',
        );
    }

    public static function makeReverseBulkAction(): BulkAction
    {
        return self::makeBulkAction(
            name: 'reverse_coordination_items_courtesy',
            label: 'Reversar cortesía',
            heading: 'Reversar estatus CORTESÍA',
            description: 'Seleccione ítems que ya están en CORTESÍA para volverlos a servicio regular. Debe indicar el motivo.',
            mark: false,
            icon: Heroicon::OutlinedArrowUturnLeft,
            color: 'warning',
        );
    }

    public static function makeMarkRecordAction(): Action
    {
        return self::makeRecordAction(
            name: 'markCourtesyItems',
            label: 'Marcar cortesía',
            heading: 'Marcar ítems como CORTESÍA',
            mark: true,
            icon: Heroicon::OutlinedGift,
            color: 'success',
        );
    }

    public static function makeReverseRecordAction(): Action
    {
        return self::makeRecordAction(
            name: 'reverseCourtesyItems',
            label: 'Reversar cortesía',
            heading: 'Reversar estatus CORTESÍA',
            mark: false,
            icon: Heroicon::OutlinedArrowUturnLeft,
            color: 'warning',
        );
    }

    private static function makeBulkAction(
        string $name,
        string $label,
        string $heading,
        string $description,
        bool $mark,
        Heroicon $icon,
        string $color,
    ): BulkAction {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (): bool => OperationsSupplierScope::authenticatedUserIsTdgAnalyst())
            ->modalHeading($heading)
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">'.$description.'</p>'
            ))
            ->modalIcon($icon)
            ->modalIconColor($color)
            ->modalWidth(Width::ExtraLarge)
            ->modalSubmitActionLabel($mark ? 'Sí, marcar CORTESÍA' : 'Sí, reversar CORTESÍA')
            ->modalCancelActionLabel('Cancelar')
            ->deselectRecordsAfterCompletion()
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->color($color)
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor($color),
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->form(function (EloquentCollection|Collection $records) use ($mark): array {
                $options = CoordinationServiceCourtesy::checkboxOptionsForCoordinations(
                    $records,
                    onlyCourtesy: ! $mark,
                );

                return [
                    CheckboxList::make('item_keys')
                        ->label($mark ? 'Ítems a marcar como CORTESÍA' : 'Ítems a reversar')
                        ->options($options)
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(1)
                        ->required()
                        ->validationMessages([
                            'required' => 'Selecciona al menos un ítem.',
                        ]),
                    Textarea::make('courtesy_reason')
                        ->label('Motivo')
                        ->placeholder($mark
                            ? 'Ej.: Cortesía autorizada por gerencia por seguimiento comercial…'
                            : 'Ej.: Se revierte porque el paciente cubrirá el servicio…')
                        ->helperText('Obligatorio. Mínimo 10 caracteres. Se registra en la bitácora del caso.')
                        ->required()
                        ->minLength(10)
                        ->maxLength(5000)
                        ->rows(4)
                        ->columnSpanFull(),
                ];
            })
            ->action(function (EloquentCollection|Collection $records, array $data) use ($mark): void {
                self::executeGrouped($records, $data, $mark);
            });
    }

    private static function makeRecordAction(
        string $name,
        string $label,
        string $heading,
        bool $mark,
        Heroicon $icon,
        string $color,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (): bool => OperationsSupplierScope::authenticatedUserIsTdgAnalyst())
            ->modalHeading($heading)
            ->modalWidth(Width::ExtraLarge)
            ->modalIcon($icon)
            ->modalIconColor($color)
            ->modalSubmitActionLabel($mark ? 'Sí, marcar CORTESÍA' : 'Sí, reversar CORTESÍA')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->color($color)
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor($color),
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->form(function (OperationCoordinationService $record) use ($mark): array {
                $options = CoordinationServiceCourtesy::checkboxOptionsForCoordinations(
                    [$record],
                    onlyCourtesy: ! $mark,
                );

                // Keys without coordination prefix for single-record action
                $normalized = [];
                foreach ($options as $composite => $label) {
                    $itemKey = str_contains((string) $composite, '|')
                        ? explode('|', (string) $composite, 2)[1]
                        : (string) $composite;
                    $normalized[$itemKey] = $label;
                }

                return [
                    CheckboxList::make('item_keys')
                        ->label($mark ? 'Ítems a marcar como CORTESÍA' : 'Ítems a reversar')
                        ->options($normalized)
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(1)
                        ->required(),
                    Textarea::make('courtesy_reason')
                        ->label('Motivo')
                        ->required()
                        ->minLength(10)
                        ->maxLength(5000)
                        ->rows(4)
                        ->columnSpanFull(),
                ];
            })
            ->action(function (OperationCoordinationService $record, array $data) use ($mark): void {
                try {
                    $updated = $mark
                        ? CoordinationServiceCourtesy::markItems(
                            $record,
                            array_values((array) ($data['item_keys'] ?? [])),
                            (string) ($data['courtesy_reason'] ?? ''),
                            Auth::user(),
                        )
                        : CoordinationServiceCourtesy::reverseItems(
                            $record,
                            array_values((array) ($data['item_keys'] ?? [])),
                            (string) ($data['courtesy_reason'] ?? ''),
                            Auth::user(),
                        );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('No se pudo actualizar CORTESÍA')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($mark ? 'Ítems marcados como CORTESÍA' : 'CORTESÍA reversada')
                    ->body($updated === 1 ? 'Se actualizó 1 ítem.' : "Se actualizaron {$updated} ítems.")
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  EloquentCollection<int, OperationCoordinationService>|Collection<int, OperationCoordinationService>  $records
     * @param  array<string, mixed>  $data
     */
    private static function executeGrouped(EloquentCollection|Collection $records, array $data, bool $mark): void
    {
        $grouped = CoordinationServiceCourtesy::groupCompositeKeysByCoordination(
            array_values((array) ($data['item_keys'] ?? []))
        );

        if ($grouped === []) {
            Notification::make()
                ->warning()
                ->title('Sin ítems seleccionados')
                ->send();

            return;
        }

        $total = 0;
        $byId = $records->keyBy('id');

        foreach ($grouped as $coordinationId => $keys) {
            $record = $byId->get($coordinationId);

            if (! $record instanceof OperationCoordinationService) {
                $record = OperationCoordinationService::query()->find($coordinationId);
            }

            if (! $record instanceof OperationCoordinationService) {
                continue;
            }

            try {
                $total += $mark
                    ? CoordinationServiceCourtesy::markItems(
                        $record,
                        $keys,
                        (string) ($data['courtesy_reason'] ?? ''),
                        Auth::user(),
                    )
                    : CoordinationServiceCourtesy::reverseItems(
                        $record,
                        $keys,
                        (string) ($data['courtesy_reason'] ?? ''),
                        Auth::user(),
                    );
            } catch (Throwable $exception) {
                Notification::make()
                    ->title('Error en coordinación #'.$coordinationId)
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();
            }
        }

        Notification::make()
            ->title($mark ? 'CORTESÍA aplicada' : 'CORTESÍA reversada')
            ->body($total === 1 ? 'Se actualizó 1 ítem.' : "Se actualizaron {$total} ítems.")
            ->success()
            ->send();
    }
}
