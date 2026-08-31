<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

final class OperationServiceOrderViewActions
{
    public const VOID_PREFIX = 'Anulación de orden de servicio por error.';

    /**
     * @return list<string>
     */
    public static function closedStatuses(): array
    {
        return OperationServiceOrderValidity::closedStatuses();
    }

    public static function normalizedStatus(OperationServiceOrder $record): string
    {
        return mb_strtoupper(trim((string) ($record->status ?? '')));
    }

    public static function canCancel(OperationServiceOrder $record): bool
    {
        return ! in_array(self::normalizedStatus($record), self::closedStatuses(), true);
    }

    public static function canVoidForRegeneration(OperationServiceOrder $record): bool
    {
        return self::canCancel($record);
    }

    public static function canFinalize(OperationServiceOrder $record): bool
    {
        return self::canCancel($record);
    }

    public static function cancelOrder(OperationServiceOrder $record, mixed $livewire = null): void
    {
        if (! self::canCancel($record)) {
            Notification::make()
                ->warning()
                ->title('No se puede cancelar')
                ->body('Las órdenes finalizadas o ya canceladas no pueden cancelarse nuevamente.')
                ->send();

            return;
        }

        $record->update([
            'status' => 'CANCELADA',
            'updated_by' => Auth::user()?->name,
        ]);

        $freshRecord = $record->fresh() ?? $record;

        OperationServiceOrderCoordinationSync::cancelClinicalItemsForOrder($freshRecord);

        if (is_object($livewire) && property_exists($livewire, 'record')) {
            $livewire->record = $freshRecord;
        }

        Notification::make()
            ->success()
            ->title('Orden cancelada')
            ->body('La orden #'.($freshRecord->order_number ?: $freshRecord->getKey()).' quedó en estatus CANCELADA.')
            ->send();
    }

    public static function makeCancelAction(): Action
    {
        return Action::make('cancelServiceOrder')
            ->label('Cancelar orden de servicio')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->button()
            ->requiresConfirmation()
            ->modalHeading('Cancelar orden de servicio')
            ->modalDescription('Confirme que desea cancelar esta orden. No podrá revertirse desde aquí si la orden ya fue finalizada.')
            ->modalSubmitActionLabel('Sí, cancelar orden')
            ->modalCancelActionLabel('Volver')
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalIconColor('danger')
            ->modalSubmitAction(
                fn (Action $action): Action => $action->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ])
            )
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
            ])
            ->visible(fn (OperationServiceOrder $record): bool => self::canCancel($record))
            ->action(function (OperationServiceOrder $record, mixed $livewire): void {
                self::cancelOrder($record, $livewire);
            });
    }

    public static function voidOrderForRegeneration(OperationServiceOrder $record, string $reason, mixed $livewire = null): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            Notification::make()
                ->danger()
                ->title('Motivo incompleto')
                ->body('El motivo de la anulación debe tener al menos 10 caracteres.')
                ->send();

            return;
        }

        if (! self::canVoidForRegeneration($record)) {
            Notification::make()
                ->warning()
                ->title('No se puede anular')
                ->body('Las órdenes finalizadas, caducadas o ya canceladas no pueden anularse para volver a generarlas.')
                ->send();

            return;
        }

        $user = Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $orderNumber = (string) ($record->order_number ?: '#'.$record->getKey());
        $bitacora = self::buildVoidBitacoraDescription($record, $reason, $userName);
        $record->loadMissing('operationCoordinationService');
        $loggedToCase = filled($record->operationCoordinationService?->telemedicine_case_id);

        DB::transaction(function () use ($record, $livewire, $user, $userName, $bitacora): void {
            $record->update([
                'status' => 'CANCELADA',
                'updated_by' => $userName,
            ]);

            $freshRecord = $record->fresh() ?? $record;
            $freshRecord->loadMissing(['operationCoordinationService', 'operationServiceOrderItems']);

            OperationServiceOrderCoordinationSync::releaseClinicalItemsForOrder($freshRecord);

            $coordination = $freshRecord->operationCoordinationService;

            if ($coordination instanceof OperationCoordinationService) {
                $previous = trim((string) ($coordination->observations ?? ''));
                $coordination->observations = $previous !== '' ? $previous."\n\n".$bitacora : $bitacora;
                $coordination->updated_by = $userName;
                $coordination->save();

                if (filled($coordination->telemedicine_case_id)) {
                    ObservationCase::query()->create([
                        'telemedicine_case_id' => $coordination->telemedicine_case_id,
                        'description' => $bitacora,
                        'created_by' => $user?->id !== null ? (string) $user->id : null,
                    ]);
                }
            }

            if (is_object($livewire) && property_exists($livewire, 'record')) {
                $livewire->record = $freshRecord;
            }
        });

        Notification::make()
            ->success()
            ->title('Orden anulada')
            ->body(
                'La orden #'.$orderNumber.' quedó en CANCELADA. '
                .'Los ítems volvieron a PENDIENTE para generar una nueva orden. '
                .($loggedToCase
                    ? 'El motivo quedó registrado en la bitácora del caso.'
                    : 'El motivo quedó registrado en las observaciones de la coordinación.')
            )
            ->send();
    }

    public static function buildVoidBitacoraDescription(OperationServiceOrder $record, string $reason, string $analystName): string
    {
        $orderNumber = (string) ($record->order_number ?: '#'.$record->getKey());

        return self::VOID_PREFIX
            ."\n".'Orden: '.$orderNumber
            ."\n".'Tipo: '.(filled($record->service_type) ? (string) $record->service_type : '—')
            ."\n".'Analista: '.$analystName
            ."\n".'Motivo: '.trim($reason)
            ."\n".'Los ítems cubiertos asociados volvieron a PENDIENTE para generar una nueva orden.';
    }

    public static function makeVoidForRegenerationAction(): Action
    {
        return Action::make('voidServiceOrderForRegeneration')
            ->label('Anular por error')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->button()
            ->modalHeading('Anular orden por error')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                .'Esta orden quedará en <strong class="text-gray-900 dark:text-white">CANCELADA</strong> '
                .'y los ítems volverán a <strong class="text-gray-900 dark:text-white">PENDIENTE</strong> '
                .'para que pueda generar una nueva orden. '
                .'Debe indicar el motivo; la anulación y el motivo quedarán en la bitácora del caso.'
                .'</p>'
            ))
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalIconColor('warning')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, anular y liberar ítems')
            ->modalCancelActionLabel('Volver')
            ->closeModalByClickingAway(false)
            ->form([
                Textarea::make('void_reason')
                    ->label('Motivo de la anulación')
                    ->placeholder('Ej.: Se cargó el proveedor equivocado, la fecha de cita era incorrecta, se seleccionaron ítems de más…')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Queda registrado en la bitácora del caso junto con la anulación.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar el motivo de la anulación.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->modalSubmitAction(
                fn (Action $action): Action => $action->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ])
            )
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
            ])
            ->visible(fn (OperationServiceOrder $record): bool => self::canVoidForRegeneration($record))
            ->action(function (OperationServiceOrder $record, array $data, mixed $livewire): void {
                self::voidOrderForRegeneration(
                    $record,
                    trim((string) ($data['void_reason'] ?? '')),
                    $livewire
                );
            });
    }
}
