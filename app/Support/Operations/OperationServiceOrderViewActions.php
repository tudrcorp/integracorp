<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationAccountsPayable;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Anular por error y cancelar una orden de servicio.
 *
 * Las dos acciones corren en una transacción con la orden bloqueada, vuelven a
 * validar el estado dentro del bloqueo, exigen motivo y dejan traza en
 * `operation_service_flow_events`, en la bitácora del caso y en la auditoría.
 * Una orden con factura registrada no se anula ni se cancela: el dinero ya corrió.
 */
final class OperationServiceOrderViewActions
{
    public const VOID_PREFIX = 'Anulación de orden de servicio por error.';

    public const CANCEL_PREFIX = 'Cancelación de orden de servicio.';

    /** La cotización vuelve a «Pendiente por aprobar» para generar la orden nueva con ella. */
    public const QUOTE_RELEASE = 'release';

    /** La cotización queda rechazada: hay que cotizar de nuevo. */
    public const QUOTE_REJECT = 'reject';

    public const MIN_REASON_LENGTH = 10;

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

    /**
     * Por qué la orden no puede anularse ni cancelarse, o null si puede.
     */
    public static function closingBlockReason(OperationServiceOrder $record): ?string
    {
        if (! self::canCancel($record)) {
            return 'La orden '.ServiceOrderFlowTrace::orderLabel($record).' ya está '.self::normalizedStatus($record).' y no admite cambios.';
        }

        if ($record->exists && OperationAccountsPayable::query()->where('operation_service_order_id', $record->getKey())->exists()) {
            return 'La orden '.ServiceOrderFlowTrace::orderLabel($record).' tiene una factura registrada en Cuentas por pagar. Revierta la factura antes de anularla o cancelarla.';
        }

        return null;
    }

    public static function linkedQuote(OperationServiceOrder $record): ?OperationQuoteGenerator
    {
        if (! $record->exists) {
            return null;
        }

        return OperationQuoteGenerator::query()
            ->where('operation_service_order_id', $record->getKey())
            ->first();
    }

    public static function cancelOrder(OperationServiceOrder $record, string $reason, mixed $livewire = null): bool
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            self::notifyIncompleteReason('cancelación');

            return false;
        }

        $userName = self::currentUserName();

        try {
            $freshRecord = DB::transaction(function () use ($record, $reason, $userName): OperationServiceOrder {
                $order = self::lockOrder($record);

                return ServiceOrderFlowTrace::withContext(
                    ['action' => ServiceOrderFlowTrace::CONTEXT_CANCEL, 'reason' => $reason],
                    function () use ($order, $reason, $userName): OperationServiceOrder {
                        $order->update([
                            'status' => 'CANCELADA',
                            'updated_by' => $userName,
                        ]);

                        $freshOrder = $order->fresh() ?? $order;

                        OperationServiceOrderCoordinationSync::cancelClinicalItemsForOrder($freshOrder);

                        self::appendToCoordinationAndCase(
                            $freshOrder,
                            self::CANCEL_PREFIX
                                ."\n".'Orden: '.ServiceOrderFlowTrace::orderLabel($freshOrder)
                                ."\n".'Tipo: '.(filled($freshOrder->service_type) ? (string) $freshOrder->service_type : '—')
                                ."\n".'Analista: '.$userName
                                ."\n".'Motivo: '.$reason
                                ."\n".'Los ítems de la orden quedaron CANCELADOS.',
                            $userName,
                        );

                        return $freshOrder;
                    },
                );
            });
        } catch (ServiceOrderFlowException $exception) {
            self::recordBlocked($record, 'cancelar', $exception->getMessage());

            return false;
        }

        SecurityAudit::log('AUDIT_OPERATIONS_SERVICE_ORDER_CANCELLED', 'operations.operation-service-orders.cancel', [
            'operation_service_order_id' => $freshRecord->getKey(),
            'order_number' => $freshRecord->order_number,
            'reason' => $reason,
        ]);

        self::refreshLivewireRecord($livewire, $freshRecord);

        Notification::make()
            ->success()
            ->title('Orden cancelada')
            ->body('La orden '.ServiceOrderFlowTrace::orderLabel($freshRecord).' quedó en CANCELADA. El motivo quedó en la traza y en la bitácora del caso.')
            ->send();

        return true;
    }

    public static function makeCancelAction(): Action
    {
        return Action::make('cancelServiceOrder')
            ->label('Cancelar orden de servicio')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->button()
            ->modalHeading('Cancelar orden de servicio')
            ->modalDescription(fn (OperationServiceOrder $record): HtmlString => new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                .'La orden <strong class="text-gray-900 dark:text-white">'.e(ServiceOrderFlowTrace::orderLabel($record)).'</strong> quedará '
                .'<strong class="text-gray-900 dark:text-white">CANCELADA</strong> y sus ítems también: el servicio no se prestará. '
                .'Si solo hubo un error en la orden y hay que generarla de nuevo, use <strong>«Anular por error»</strong>. No se puede deshacer.'
                .'</p>'
            ))
            ->modalSubmitActionLabel('Sí, cancelar orden')
            ->modalCancelActionLabel('Volver')
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->closeModalByClickingAway(false)
            ->form([
                self::reasonField('cancel_reason', 'Motivo de la cancelación', 'Ej.: El paciente desistió del servicio, el médico suspendió la indicación…'),
            ])
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
            ->disabled(fn (OperationServiceOrder $record): bool => self::closingBlockReason($record) !== null)
            ->tooltip(fn (OperationServiceOrder $record): ?string => self::closingBlockReason($record))
            ->action(function (OperationServiceOrder $record, array $data, mixed $livewire): void {
                self::cancelOrder($record, (string) ($data['cancel_reason'] ?? ''), $livewire);
            });
    }

    /**
     * @param  self::QUOTE_*|null  $quoteDecision  Obligatorio si la orden nació de una cotización.
     */
    public static function voidOrderForRegeneration(
        OperationServiceOrder $record,
        string $reason,
        mixed $livewire = null,
        ?string $quoteDecision = null,
    ): bool {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            self::notifyIncompleteReason('anulación');

            return false;
        }

        $userName = self::currentUserName();
        $loggedToCase = false;
        $quoteOutcome = null;

        try {
            [$freshRecord, $loggedToCase, $quoteOutcome] = DB::transaction(function () use ($record, $reason, $quoteDecision, $userName): array {
                $order = self::lockOrder($record);

                $quote = OperationQuoteGenerator::query()
                    ->where('operation_service_order_id', $order->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($quote instanceof OperationQuoteGenerator && ! in_array($quoteDecision, [self::QUOTE_RELEASE, self::QUOTE_REJECT], true)) {
                    throw new ServiceOrderFlowException(
                        'La orden nació de la cotización '.ServiceOrderFlowTrace::quoteLabel($quote).'. Indique si la cotización se libera para generar la nueva orden o se rechaza para cotizar de nuevo.'
                    );
                }

                return ServiceOrderFlowTrace::withContext(
                    ['action' => ServiceOrderFlowTrace::CONTEXT_VOID, 'reason' => $reason],
                    function () use ($order, $quote, $quoteDecision, $reason, $userName): array {
                        $order->update([
                            'status' => 'CANCELADA',
                            'updated_by' => $userName,
                        ]);

                        $freshOrder = $order->fresh() ?? $order;
                        $freshOrder->loadMissing(['operationCoordinationService', 'operationServiceOrderItems']);

                        OperationServiceOrderCoordinationSync::releaseClinicalItemsForOrder($freshOrder);

                        $quoteOutcome = $quote instanceof OperationQuoteGenerator
                            ? self::settleQuoteOnVoid($quote, (string) $quoteDecision, $userName)
                            : null;

                        self::reopenReceivableOnVoid($freshOrder, $quote, $quoteDecision, $userName);

                        $loggedToCase = self::appendToCoordinationAndCase(
                            $freshOrder,
                            self::buildVoidBitacoraDescription($freshOrder, $reason, $userName, $quoteOutcome),
                            $userName,
                        );

                        return [$freshOrder, $loggedToCase, $quoteOutcome];
                    },
                );
            });
        } catch (ServiceOrderFlowException $exception) {
            self::recordBlocked($record, 'anular por error', $exception->getMessage());

            return false;
        }

        SecurityAudit::log('AUDIT_OPERATIONS_SERVICE_ORDER_VOIDED', 'operations.operation-service-orders.void', [
            'operation_service_order_id' => $freshRecord->getKey(),
            'order_number' => $freshRecord->order_number,
            'reason' => $reason,
            'quote_decision' => $quoteDecision,
        ]);

        self::refreshLivewireRecord($livewire, $freshRecord);

        Notification::make()
            ->success()
            ->title('Orden anulada')
            ->body(
                'La orden '.ServiceOrderFlowTrace::orderLabel($freshRecord).' quedó en CANCELADA. '
                .'Los ítems volvieron a PENDIENTE para generar una nueva orden. '
                .($quoteOutcome !== null ? $quoteOutcome.' ' : '')
                .($loggedToCase
                    ? 'El motivo quedó registrado en la traza y en la bitácora del caso.'
                    : 'El motivo quedó registrado en la traza y en las observaciones de la coordinación.')
            )
            ->send();

        return true;
    }

    public static function buildVoidBitacoraDescription(
        OperationServiceOrder $record,
        string $reason,
        string $analystName,
        ?string $quoteOutcome = null,
    ): string {
        return self::VOID_PREFIX
            ."\n".'Orden: '.ServiceOrderFlowTrace::orderLabel($record)
            ."\n".'Tipo: '.(filled($record->service_type) ? (string) $record->service_type : '—')
            ."\n".'Analista: '.$analystName
            ."\n".'Motivo: '.trim($reason)
            ."\n".'Los ítems cubiertos asociados volvieron a PENDIENTE para generar una nueva orden.'
            .($quoteOutcome !== null ? "\n".$quoteOutcome : '');
    }

    public static function makeVoidForRegenerationAction(): Action
    {
        return Action::make('voidServiceOrderForRegeneration')
            ->label('Anular por error')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->button()
            ->modalHeading('Anular orden por error')
            ->modalDescription(fn (OperationServiceOrder $record): HtmlString => self::voidModalDescription($record))
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalIconColor('warning')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, anular y liberar ítems')
            ->modalCancelActionLabel('Volver')
            ->closeModalByClickingAway(false)
            ->form([
                Radio::make('quote_decision')
                    ->label(fn (OperationServiceOrder $record): string => '¿Qué hacer con la cotización '.ServiceOrderFlowTrace::quoteLabel(self::linkedQuote($record)).'?')
                    ->options([
                        self::QUOTE_RELEASE => 'Liberarla para generar la nueva orden con ella',
                        self::QUOTE_REJECT => 'Rechazarla y cotizar de nuevo',
                    ])
                    ->descriptions([
                        self::QUOTE_RELEASE => 'Vuelve a «Pendiente por aprobar» con el mismo proveedor y precio. Apruébela en «Gestionar cotización» para crear la nueva orden.',
                        self::QUOTE_REJECT => 'Úselo si cambia el proveedor o el precio. La cotización queda RECHAZADA como historial.',
                    ])
                    ->visible(fn (OperationServiceOrder $record): bool => self::linkedQuote($record) !== null)
                    ->required()
                    ->validationMessages([
                        'required' => 'Indique qué hacer con la cotización de la orden.',
                    ]),
                self::reasonField('void_reason', 'Motivo de la anulación', 'Ej.: Se cargó el proveedor equivocado, la fecha de cita era incorrecta, se seleccionaron ítems de más…'),
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
            ->disabled(fn (OperationServiceOrder $record): bool => self::closingBlockReason($record) !== null)
            ->tooltip(fn (OperationServiceOrder $record): ?string => self::closingBlockReason($record))
            ->action(function (OperationServiceOrder $record, array $data, mixed $livewire): void {
                self::voidOrderForRegeneration(
                    $record,
                    trim((string) ($data['void_reason'] ?? '')),
                    $livewire,
                    filled($data['quote_decision'] ?? null) ? (string) $data['quote_decision'] : null,
                );
            });
    }

    private static function voidModalDescription(OperationServiceOrder $record): HtmlString
    {
        $quote = self::linkedQuote($record);
        $quoteNotice = $quote instanceof OperationQuoteGenerator
            ? '<p class="mt-2 text-sm text-amber-800 dark:text-amber-200">Esta orden nació de la cotización <strong>'
                .e(ServiceOrderFlowTrace::quoteLabel($quote)).'</strong>. Indique abajo si se libera o se rechaza.</p>'
            : '';

        return new HtmlString(
            '<p class="text-sm text-gray-600 dark:text-gray-300">'
            .'La orden <strong class="text-gray-900 dark:text-white">'.e(ServiceOrderFlowTrace::orderLabel($record)).'</strong> quedará en '
            .'<strong class="text-gray-900 dark:text-white">CANCELADA</strong> y los ítems volverán a '
            .'<strong class="text-gray-900 dark:text-white">PENDIENTE</strong> para que pueda generar una nueva orden. '
            .'Su PDF quedará marcado como ANULADA. Debe indicar el motivo; queda en la traza y en la bitácora del caso.'
            .'</p>'
            .$quoteNotice
        );
    }

    /**
     * Bloquea la orden y vuelve a validar dentro del bloqueo: dos analistas no pueden
     * anular, cancelar o finalizar la misma orden a la vez.
     */
    private static function lockOrder(OperationServiceOrder $record): OperationServiceOrder
    {
        $order = OperationServiceOrder::query()->lockForUpdate()->find($record->getKey());

        if (! $order instanceof OperationServiceOrder) {
            throw new ServiceOrderFlowException('La orden ya no existe.');
        }

        $blockReason = self::closingBlockReason($order);

        if ($blockReason !== null) {
            throw new ServiceOrderFlowException($blockReason);
        }

        return $order;
    }

    private static function settleQuoteOnVoid(OperationQuoteGenerator $quote, string $decision, string $userName): string
    {
        $label = ServiceOrderFlowTrace::quoteLabel($quote);

        if ($decision === self::QUOTE_RELEASE) {
            $quote->update([
                'status' => OperationQuoteGenerator::STATUS_PENDING,
                'operation_service_order_id' => null,
                'updated_by' => $userName,
            ]);

            return 'La cotización '.$label.' quedó liberada en «Pendiente por aprobar»: apruébela en «Gestionar cotización» para crear la nueva orden.';
        }

        $quote->update([
            'status' => OperationQuoteGenerator::STATUS_REJECTED,
            'updated_by' => $userName,
        ]);

        return 'La cotización '.$label.' quedó RECHAZADA: registre una nueva cotización para generar la orden.';
    }

    /**
     * La cuenta por cobrar (casos reasignados a TDG) apuntaba a la orden anulada:
     * vuelve a quedar abierta para que la orden nueva la complete.
     */
    private static function reopenReceivableOnVoid(
        OperationServiceOrder $order,
        ?OperationQuoteGenerator $quote,
        ?string $quoteDecision,
        string $userName,
    ): void {
        $receivables = OperationAccountsReceivable::query()
            ->where('operation_service_order_id', $order->getKey())
            ->lockForUpdate()
            ->get();

        foreach ($receivables as $receivable) {
            $keepsQuote = filled($receivable->operation_quote_generator_id)
                && ! ($quote instanceof OperationQuoteGenerator
                    && (int) $receivable->operation_quote_generator_id === (int) $quote->getKey()
                    && $quoteDecision === self::QUOTE_REJECT);

            $updates = [
                'operation_service_order_id' => null,
                'service_order_number' => null,
                'status' => $keepsQuote
                    ? OperationAccountsReceivable::STATUS_QUOTE_ASSIGNED
                    : OperationAccountsReceivable::STATUS_PENDING_TDG,
                'updated_by' => $userName,
            ];

            if (! $keepsQuote) {
                $updates += [
                    'operation_quote_generator_id' => null,
                    'quote_number' => null,
                    'quote_amount_usd' => null,
                    'quote_amount_ves' => null,
                ];
            }

            $previousStatus = (string) $receivable->status;
            $receivable->update($updates);

            ServiceOrderFlowTrace::record(
                ServiceOrderFlowTrace::RECEIVABLE_REOPENED,
                sprintf(
                    'La cuenta por cobrar %s se desvinculó de la orden %s y pasó de %s a %s.',
                    AccountsReceivableManager::formatReceivableNumber((int) $receivable->getKey()),
                    ServiceOrderFlowTrace::orderLabel($order),
                    $previousStatus,
                    (string) $updates['status'],
                ),
                ['order' => $order, 'quote' => $quote],
                changes: ['status' => ['antes' => $previousStatus, 'despues' => $updates['status']]],
            );
        }
    }

    /**
     * @return bool true si quedó en la bitácora del caso; false si solo en la coordinación.
     */
    private static function appendToCoordinationAndCase(OperationServiceOrder $order, string $text, string $userName): bool
    {
        $order->loadMissing('operationCoordinationService');
        $coordination = $order->operationCoordinationService;

        if (! $coordination instanceof OperationCoordinationService) {
            return false;
        }

        $previous = trim((string) ($coordination->observations ?? ''));
        $coordination->observations = $previous !== '' ? $previous."\n\n".$text : $text;
        $coordination->updated_by = $userName;
        $coordination->save();

        if (! filled($coordination->telemedicine_case_id)) {
            return false;
        }

        $user = Auth::user();

        ObservationCase::query()->create([
            'telemedicine_case_id' => $coordination->telemedicine_case_id,
            'description' => $text,
            // La columna es obligatoria: sin usuario (proceso del sistema) va el nombre.
            'created_by' => $user?->id !== null ? (string) $user->id : $userName,
        ]);

        return true;
    }

    private static function recordBlocked(OperationServiceOrder $record, string $actionLabel, string $message): void
    {
        ServiceOrderFlowTrace::record(
            ServiceOrderFlowTrace::ACTION_BLOCKED,
            sprintf('Se intentó %s la orden %s y el sistema lo impidió: %s', $actionLabel, ServiceOrderFlowTrace::orderLabel($record), $message),
            ['order' => $record],
        );

        Notification::make()
            ->warning()
            ->title('No se pudo '.$actionLabel.' la orden')
            ->body($message)
            ->persistent()
            ->send();
    }

    private static function reasonField(string $name, string $label, string $placeholder): Textarea
    {
        return Textarea::make($name)
            ->label($label)
            ->placeholder($placeholder)
            ->helperText('Campo obligatorio. Mínimo '.self::MIN_REASON_LENGTH.' caracteres. Queda en la traza de la orden y en la bitácora del caso.')
            ->required()
            ->minLength(self::MIN_REASON_LENGTH)
            ->maxLength(5000)
            ->rows(4)
            ->columnSpanFull()
            ->validationMessages([
                'required' => 'Debe indicar el motivo.',
                'minLength' => 'El motivo debe tener al menos '.self::MIN_REASON_LENGTH.' caracteres.',
            ]);
    }

    private static function notifyIncompleteReason(string $what): void
    {
        Notification::make()
            ->danger()
            ->title('Motivo incompleto')
            ->body('El motivo de la '.$what.' debe tener al menos '.self::MIN_REASON_LENGTH.' caracteres.')
            ->send();
    }

    private static function currentUserName(): string
    {
        $name = Auth::user()?->name;

        return filled($name) ? (string) $name : 'SISTEMA';
    }

    private static function refreshLivewireRecord(mixed $livewire, OperationServiceOrder $record): void
    {
        if (is_object($livewire) && property_exists($livewire, 'record')) {
            $livewire->record = $record;
        }
    }
}
