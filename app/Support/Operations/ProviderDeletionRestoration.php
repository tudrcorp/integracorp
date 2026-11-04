<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationAccountsPayable;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicalAppointment;
use App\Models\OperationServiceOrder;
use App\Models\ProviderDeletionLog;
use App\Models\ProviderDeletionLogItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Deshace lo que una eliminación de proveedor hizo con sus órdenes y citas.
 *
 * Al eliminar, `capture*()` guarda una «foto» de cada orden o cita antes de reasignarla o
 * cancelarla. Al restaurar, `evaluate()` decide —con lo que hay hoy en la base— si cada
 * una puede volver sin pisar lo que pasó después:
 *
 * - **Reasignada**: vuelve si sigue vigente, sin factura y con el mismo proveedor al que
 *   se pasó. Si el nuevo proveedor la finalizó, la facturó o cambió de manos, se queda.
 * - **Cancelada**: vuelve si sigue CANCELADA, con el mismo proveedor y sin factura.
 * - **Cita cancelada** (de una orden cerrada): vuelve si sigue CANCELADA.
 *
 * Lo que no puede volver queda como está, con el motivo en la bitácora de la eliminación.
 */
final class ProviderDeletionRestoration
{
    public const CONTEXT_RESTORE = 'RESTORE';

    public const PREFIX = 'Devolución de orden por restauración del proveedor.';

    /**
     * @return array<string, mixed>
     */
    public static function captureOrder(OperationServiceOrder $order, bool $withClinicalItems): array
    {
        $appointment = OperationMedicalAppointment::query()->where('operation_service_order_id', $order->getKey())->first();
        $coordination = filled($order->operation_coordination_service_id)
            ? OperationCoordinationService::query()->withoutGlobalScopes()->find($order->operation_coordination_service_id)
            : null;

        return [
            'order' => [
                'status' => $order->status,
                'supplier_id' => $order->supplier_id !== null ? (int) $order->supplier_id : null,
                'doctor_nurse_id' => $order->doctor_nurse_id !== null ? (int) $order->doctor_nurse_id : null,
                'supplier_external' => $order->supplier_external,
            ],
            'appointment' => $appointment instanceof OperationMedicalAppointment ? self::appointmentFields($appointment) : null,
            'coordination' => $coordination instanceof OperationCoordinationService ? [
                'id' => (int) $coordination->getKey(),
                'assigned_supplier_id' => $coordination->assigned_supplier_id !== null ? (int) $coordination->assigned_supplier_id : null,
                'assigned_doctor_nurse_id' => $coordination->assigned_doctor_nurse_id !== null ? (int) $coordination->assigned_doctor_nurse_id : null,
                'assigned_corporate_ally_id' => $coordination->assigned_corporate_ally_id !== null ? (int) $coordination->assigned_corporate_ally_id : null,
            ] : null,
            'clinical_items' => $withClinicalItems
                ? array_map(fn (Model $record): array => [
                    'model' => $record::class,
                    'id' => (int) $record->getKey(),
                    'status' => (string) $record->getAttribute('status'),
                ], OperationServiceOrderCoordinationSync::clinicalItemsForOrder($order))
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function captureAppointment(OperationMedicalAppointment $appointment): array
    {
        return ['appointment' => self::appointmentFields($appointment)];
    }

    public static function openLogFor(string $type, Model $provider): ?ProviderDeletionLog
    {
        return ProviderDeletionLog::query()
            ->where('provider_type', $type)
            ->where('provider_id', $provider->getKey())
            ->whereNull('restored_at')
            ->latest('id')
            ->first();
    }

    /**
     * Veredicto de cada orden o cita de la eliminación, con lo que hay hoy en la base.
     *
     * @return list<array{item_id: int, title: string, ok: bool, note: string, warning: string|null}>
     */
    public static function preview(ProviderDeletionLog $log): array
    {
        return $log->items->map(fn (ProviderDeletionLogItem $item): array => [
            'item_id' => (int) $item->getKey(),
            'title' => self::itemTitle($item),
            ...self::evaluate($item),
        ])->values()->all();
    }

    /**
     * @return array{ok: bool, note: string, warning: string|null}
     */
    public static function evaluate(ProviderDeletionLogItem $item, bool $lock = false): array
    {
        if ($item->kind === ProviderDeletionLogItem::KIND_APPOINTMENT) {
            $appointment = self::appointmentQuery($lock)->find($item->operation_medical_appointment_id);

            if (! $appointment instanceof OperationMedicalAppointment) {
                return self::verdict(false, 'La cita ya no existe.');
            }

            if (! ProviderPendingWork::isAppointmentCancelled($appointment)) {
                return self::verdict(false, 'La cita ya no está cancelada: alguien la cambió después.');
            }

            return self::verdict(true, 'La cita vuelve a quedar agendada con el proveedor.', self::pastAppointmentWarning($appointment));
        }

        $order = self::orderQuery($lock)->find($item->operation_service_order_id);

        if (! $order instanceof OperationServiceOrder) {
            return self::verdict(false, 'La orden ya no existe.');
        }

        if (filled($order->invoice_number) || filled($order->invoice_file_path)) {
            return self::verdict(false, 'La orden ya tiene factura cargada.');
        }

        $snapshot = (array) $item->snapshot;
        $status = mb_strtoupper(trim((string) $order->status));

        if ($item->action === ProviderDeletion::RESOLVE_REASSIGN) {
            $blockReason = OperationServiceOrderViewActions::closingBlockReason($order);

            if ($blockReason !== null) {
                return self::verdict(false, 'El nuevo proveedor ya avanzó: '.$blockReason);
            }

            $result = (array) $item->result;

            if (! self::orderBelongsTo($order, (string) ($result['target_type'] ?? ''), (int) ($result['target_id'] ?? 0))) {
                return self::verdict(false, 'La orden cambió de proveedor otra vez después de la eliminación.');
            }

            return self::verdict(true, 'Vuelve al proveedor; '.ServiceOrderFlowTrace::orderProviderLabel($order).' recibe el aviso de que ya no la tiene.', self::orderAppointmentWarning($order));
        }

        if (! in_array($status, ['CANCELADA', 'CANCELADO'], true)) {
            return self::verdict(false, 'La orden ya no está cancelada: está '.($status !== '' ? $status : 'sin estado').'.');
        }

        $original = (array) ($snapshot['order'] ?? []);

        if ((int) ($order->supplier_id ?? 0) !== (int) ($original['supplier_id'] ?? 0) || (int) ($order->doctor_nurse_id ?? 0) !== (int) ($original['doctor_nurse_id'] ?? 0)) {
            return self::verdict(false, 'La orden cambió de proveedor después de cancelarse.');
        }

        if (OperationAccountsPayable::query()->where('operation_service_order_id', $order->getKey())->exists()) {
            return self::verdict(false, 'La orden tiene una factura registrada en Cuentas por pagar.');
        }

        return self::verdict(true, 'Vuelve a '.(filled($original['status'] ?? null) ? (string) $original['status'] : 'EN GESTION').' con sus ítems clínicos y su cita.', self::orderAppointmentWarning($order));
    }

    /**
     * Devuelve una orden o cita. Corre dentro de la transacción de la restauración, con la
     * fila bloqueada y el veredicto recalculado bajo ese bloqueo.
     *
     * @return array{ok: bool, note: string, warning: string|null}
     */
    public static function apply(ProviderDeletionLogItem $item, string $providerType, int $providerId, string $reason, string $userName): array
    {
        $verdict = self::evaluate($item, lock: true);

        if (! $verdict['ok']) {
            $item->forceFill(['restore_status' => ProviderDeletionLogItem::SKIPPED, 'restore_note' => $verdict['note']])->save();

            return $verdict;
        }

        $snapshot = (array) $item->snapshot;

        if ($item->kind === ProviderDeletionLogItem::KIND_APPOINTMENT) {
            $appointment = OperationMedicalAppointment::query()->find($item->operation_medical_appointment_id);
            self::restoreAppointmentStatus($appointment, (array) $snapshot['appointment'], $userName);
        } elseif ($item->action === ProviderDeletion::RESOLVE_REASSIGN) {
            self::undoReassignment($item, $providerType, $providerId, $reason, $userName);
        } else {
            self::undoCancellation($item, $providerType, $providerId, $reason, $userName);
        }

        $item->forceFill(['restore_status' => ProviderDeletionLogItem::RESTORED, 'restore_note' => $verdict['warning']])->save();

        return $verdict;
    }

    private static function undoReassignment(ProviderDeletionLogItem $item, string $providerType, int $providerId, string $reason, string $userName): void
    {
        $snapshot = (array) $item->snapshot;
        $result = (array) $item->result;
        $original = (array) $snapshot['order'];
        $order = OperationServiceOrder::query()->findOrFail($item->operation_service_order_id);
        $fromLabel = ServiceOrderFlowTrace::orderProviderLabel($order);
        $withdrawnContacts = OperationServiceOrderReassignment::contactsFor((string) $result['target_type'], (int) $result['target_id']);

        ServiceOrderFlowTrace::withContext(
            ['action' => self::CONTEXT_RESTORE, 'reason' => $reason],
            function () use ($order, $original, $userName): void {
                $order->update([
                    'supplier_id' => $original['supplier_id'],
                    'doctor_nurse_id' => $original['doctor_nurse_id'],
                    'supplier_external' => $original['supplier_external'],
                    'updated_by' => $userName,
                ]);
            },
        );

        $appointment = OperationMedicalAppointment::query()->where('operation_service_order_id', $order->getKey())->lockForUpdate()->first();
        $appointmentSnapshot = $snapshot['appointment'] ?? null;

        if ($appointment instanceof OperationMedicalAppointment && is_array($appointmentSnapshot) && ! ProviderPendingWork::isAppointmentCancelled($appointment)) {
            $appointment->forceFill([
                'supplier_id' => $appointmentSnapshot['supplier_id'],
                'supplier_external' => $appointmentSnapshot['supplier_external'],
                'supplier_notify_email' => $appointmentSnapshot['supplier_notify_email'],
                'supplier_notify_phone' => $appointmentSnapshot['supplier_notify_phone'],
                'last_changed_at' => now(),
                'last_changed_by' => $userName,
                'updated_by' => $userName,
            ])->save();
        }

        self::restoreCoordinationAssignment($snapshot, (string) $result['target_type'], (int) $result['target_id'], $userName);

        $toLabel = ServiceOrderFlowTrace::orderProviderLabel($order->fresh() ?? $order);

        OperationServiceOrderViewActions::appendToCoordinationAndCase(
            $order,
            self::PREFIX
                ."\n".'Orden: '.ServiceOrderFlowTrace::orderLabel($order)
                ."\n".'Se retira de: '.$fromLabel
                ."\n".'Vuelve a: '.$toLabel
                ."\n".'Analista: '.$userName
                ."\n".'Motivo: '.$reason,
            $userName,
        );

        OperationServiceOrderReassignment::notifyAssignedProvider($order, OperationServiceOrderReassignment::contactsFor($providerType, $providerId));
        OperationServiceOrderReassignment::notifyWithdrawnProvider($order, $withdrawnContacts);
    }

    private static function undoCancellation(ProviderDeletionLogItem $item, string $providerType, int $providerId, string $reason, string $userName): void
    {
        $snapshot = (array) $item->snapshot;
        $original = (array) $snapshot['order'];
        $order = OperationServiceOrder::query()->findOrFail($item->operation_service_order_id);
        $previousStatus = filled($original['status'] ?? null) ? (string) $original['status'] : 'EN GESTION';

        ServiceOrderFlowTrace::withContext(
            ['action' => self::CONTEXT_RESTORE, 'reason' => $reason],
            function () use ($order, $previousStatus, $userName): void {
                $order->update(['status' => $previousStatus, 'updated_by' => $userName]);
            },
        );

        $restoredItems = 0;
        $changedItems = 0;

        foreach ((array) ($snapshot['clinical_items'] ?? []) as $clinical) {
            $model = (string) ($clinical['model'] ?? '');

            if (! in_array($model, array_column(OperationServiceOrderCoordinationSync::CLINICAL_ITEM_SOURCES, 0), true)) {
                continue;
            }

            $record = $model::query()->lockForUpdate()->find((int) $clinical['id']);

            if ($record !== null && mb_strtoupper(trim((string) $record->getAttribute('status'))) === 'CANCELADA') {
                $record->update(['status' => (string) $clinical['status']]);
                $restoredItems++;
            } else {
                $changedItems++;
            }
        }

        $appointment = OperationMedicalAppointment::query()->where('operation_service_order_id', $order->getKey())->lockForUpdate()->first();
        $appointmentSnapshot = $snapshot['appointment'] ?? null;
        $appointmentRestored = false;

        if ($appointment instanceof OperationMedicalAppointment && is_array($appointmentSnapshot) && ProviderPendingWork::isAppointmentCancelled($appointment)) {
            self::restoreAppointmentStatus($appointment, $appointmentSnapshot, $userName);
            $appointmentRestored = true;
        }

        self::restoreCoordinationAssignment($snapshot, null, 0, $userName, providerType: $providerType, providerId: $providerId);

        OperationServiceOrderViewActions::appendToCoordinationAndCase(
            $order,
            self::PREFIX
                ."\n".'Orden: '.ServiceOrderFlowTrace::orderLabel($order)
                ."\n".'La orden vuelve a '.$previousStatus.' (se había cancelado al eliminar al proveedor).'
                ."\n".'Ítems clínicos devueltos: '.$restoredItems.($changedItems > 0 ? ' · '.$changedItems.' ya habían cambiado y se dejaron como están' : '')
                .($appointmentRestored ? "\n".'La cita médica vuelve a quedar agendada.' : '')
                ."\n".'Analista: '.$userName
                ."\n".'Motivo: '.$reason,
            $userName,
        );
    }

    /**
     * Reasignada: el servicio médico vuelve al proveedor original si sigue con el nuevo.
     * Cancelada: vuelve si quedó sin proveedor asignado.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private static function restoreCoordinationAssignment(
        array $snapshot,
        ?string $targetType,
        int $targetId,
        string $userName,
        ?string $providerType = null,
        int $providerId = 0,
    ): void {
        $original = $snapshot['coordination'] ?? null;

        if (! is_array($original)) {
            return;
        }

        $coordination = OperationCoordinationService::query()->withoutGlobalScopes()->lockForUpdate()->find((int) $original['id']);

        if (! $coordination instanceof OperationCoordinationService) {
            return;
        }

        $columns = ['assigned_supplier_id', 'assigned_doctor_nurse_id', 'assigned_corporate_ally_id'];

        if ($targetType !== null) {
            $targetColumn = ProviderPendingWork::COORDINATION_ASSIGNED_COLUMN[$targetType];

            if ((int) $coordination->getAttribute($targetColumn) !== $targetId) {
                return;
            }
        } else {
            $column = ProviderPendingWork::COORDINATION_ASSIGNED_COLUMN[(string) $providerType];

            if ((int) ($original[$column] ?? 0) !== $providerId || $coordination->getAttribute($column) !== null) {
                return;
            }
        }

        foreach ($columns as $column) {
            $coordination->setAttribute($column, $original[$column] ?? null);
        }

        $coordination->updated_by = $userName;
        $coordination->save();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function restoreAppointmentStatus(OperationMedicalAppointment $appointment, array $snapshot, string $userName): void
    {
        $status = filled($snapshot['status'] ?? null) ? (string) $snapshot['status'] : OperationMedicalAppointment::STATUS_SCHEDULED;

        $appointment->forceFill([
            'status' => $status,
            'last_change_reason' => 'Restauración del proveedor: la cita vuelve a quedar agendada.',
            'last_changed_at' => now(),
            'last_changed_by' => $userName,
            'updated_by' => $userName,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private static function appointmentFields(OperationMedicalAppointment $appointment): array
    {
        return [
            'id' => (int) $appointment->getKey(),
            'status' => $appointment->status,
            'supplier_id' => $appointment->supplier_id !== null ? (int) $appointment->supplier_id : null,
            'supplier_external' => $appointment->supplier_external,
            'supplier_notify_email' => $appointment->supplier_notify_email,
            'supplier_notify_phone' => $appointment->supplier_notify_phone,
        ];
    }

    private static function orderBelongsTo(OperationServiceOrder $order, string $type, int $id): bool
    {
        return match ($type) {
            ProviderDeletion::TYPE_SUPPLIER => (int) ($order->supplier_id ?? 0) === $id,
            ProviderDeletion::TYPE_DOCTOR_NURSE => (int) ($order->doctor_nurse_id ?? 0) === $id && ! filled($order->supplier_id),
            default => false,
        };
    }

    private static function orderAppointmentWarning(OperationServiceOrder $order): ?string
    {
        $appointment = OperationMedicalAppointment::query()->where('operation_service_order_id', $order->getKey())->first();

        return $appointment instanceof OperationMedicalAppointment ? self::pastAppointmentWarning($appointment) : null;
    }

    private static function pastAppointmentWarning(OperationMedicalAppointment $appointment): ?string
    {
        return $appointment->appointment_at !== null && $appointment->appointment_at->lt(Carbon::now())
            ? 'La fecha de la cita ('.MedicalAppointmentManager::formatAppointmentAt($appointment->appointment_at).') ya pasó: reprográmela.'
            : null;
    }

    private static function itemTitle(ProviderDeletionLogItem $item): string
    {
        if ($item->kind === ProviderDeletionLogItem::KIND_APPOINTMENT) {
            return 'Cita #'.$item->operation_medical_appointment_id.' (cancelada al eliminar)';
        }

        return 'Orden '.ServiceOrderFlowTrace::orderLabel((int) $item->operation_service_order_id)
            .($item->action === ProviderDeletion::RESOLVE_REASSIGN ? ' (reasignada al eliminar)' : ' (cancelada al eliminar)');
    }

    /**
     * @return Builder<OperationServiceOrder>
     */
    private static function orderQuery(bool $lock): Builder
    {
        $query = OperationServiceOrder::query();

        return $lock ? $query->lockForUpdate() : $query;
    }

    /**
     * @return Builder<OperationMedicalAppointment>
     */
    private static function appointmentQuery(bool $lock): Builder
    {
        $query = OperationMedicalAppointment::query();

        return $lock ? $query->lockForUpdate() : $query;
    }

    /**
     * @return array{ok: bool, note: string, warning: string|null}
     */
    private static function verdict(bool $ok, string $note, ?string $warning = null): array
    {
        return ['ok' => $ok, 'note' => $note, 'warning' => $warning];
    }
}
