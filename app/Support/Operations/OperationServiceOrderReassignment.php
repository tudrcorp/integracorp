<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\OperationServiceOrderPdfMail;
use App\Mail\OperationServiceOrderWithdrawnMail;
use App\Models\DoctorNurse;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicalAppointment;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Pasa una orden de servicio vigente a otro proveedor (jurídico o natural, como permite
 * el formulario de órdenes), con su cita y el proveedor asignado de su servicio médico.
 *
 * Corre dentro de la transacción de quien llama. El aviso al nuevo proveedor (PDF por
 * correo y WhatsApp) se encola con `afterCommit()`: si la transacción se revierte, nadie
 * recibe una orden que no existe.
 */
final class OperationServiceOrderReassignment
{
    public const CONTEXT_REASSIGN = 'REASSIGN';

    public const PREFIX = 'Reasignación de orden de servicio.';

    /**
     * Valor de un selector de proveedor destino: «supplier:12» o «doctor_nurse:7».
     *
     * @return array{type: string, id: int}
     */
    public static function parseTarget(mixed $value): array
    {
        if (is_string($value) && preg_match('/^(supplier|doctor_nurse):(\d+)$/', $value, $matches) === 1) {
            return ['type' => $matches[1], 'id' => (int) $matches[2]];
        }

        throw new InvalidArgumentException('Elija el proveedor al que pasa la orden.');
    }

    public static function targetKey(string $type, int $id): string
    {
        return $type.':'.$id;
    }

    /**
     * Proveedores vigentes (no eliminados) que pueden recibir una orden, para el selector.
     *
     * @return array<string, string>
     */
    public static function searchTargets(string $search, ?string $excludeKey = null, int $limit = 50): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search)).'%';
        $options = [];

        foreach (Supplier::query()->where('name', 'like', $like)->orderBy('name')->limit($limit)->get(['id', 'name']) as $supplier) {
            $options[self::targetKey(ProviderDeletion::TYPE_SUPPLIER, (int) $supplier->id)] = 'Jurídico · '.$supplier->name;
        }

        foreach (DoctorNurse::query()->where('name', 'like', $like)->orderBy('name')->limit($limit)->get(['id', 'name']) as $doctorNurse) {
            $options[self::targetKey(ProviderDeletion::TYPE_DOCTOR_NURSE, (int) $doctorNurse->id)] = 'Natural · '.$doctorNurse->name;
        }

        if ($excludeKey !== null) {
            unset($options[$excludeKey]);
        }

        return $options;
    }

    public static function targetLabel(mixed $value): ?string
    {
        try {
            $target = self::parseTarget($value);
        } catch (InvalidArgumentException) {
            return null;
        }

        $model = self::targetModel($target['type'], $target['id'], withTrashed: true);

        if ($model === null) {
            return null;
        }

        return ($target['type'] === ProviderDeletion::TYPE_SUPPLIER ? 'Jurídico · ' : 'Natural · ').ProviderDeletion::displayName($model);
    }

    /**
     * @return array{order_id: int, order_label: string, from: string, to: string}
     *
     * @throws InvalidArgumentException si la orden no admite el cambio o el destino no sirve
     */
    public static function reassign(OperationServiceOrder $order, string $targetType, int $targetId, string $reason, string $userName): array
    {
        $reason = trim($reason);
        $target = self::targetModel($targetType, $targetId, withTrashed: false);

        if ($target === null) {
            throw new InvalidArgumentException('El proveedor elegido para la orden '.ServiceOrderFlowTrace::orderLabel($order).' ya no está disponible.');
        }

        return DB::transaction(function () use ($order, $targetType, $targetId, $target, $reason, $userName): array {
            $locked = OperationServiceOrder::query()->lockForUpdate()->find($order->getKey());

            if (! $locked instanceof OperationServiceOrder) {
                throw new InvalidArgumentException('La orden '.ServiceOrderFlowTrace::orderLabel($order).' ya no existe.');
            }

            $blockReason = OperationServiceOrderViewActions::closingBlockReason($locked);

            if ($blockReason !== null) {
                throw new InvalidArgumentException($blockReason);
            }

            $fromType = filled($locked->supplier_id) ? ProviderDeletion::TYPE_SUPPLIER : (filled($locked->doctor_nurse_id) ? ProviderDeletion::TYPE_DOCTOR_NURSE : null);
            $fromId = $fromType === ProviderDeletion::TYPE_SUPPLIER ? (int) $locked->supplier_id : (int) $locked->doctor_nurse_id;

            if ($fromType === $targetType && $fromId === $targetId) {
                throw new InvalidArgumentException('La orden '.ServiceOrderFlowTrace::orderLabel($locked).' ya es de ese proveedor: elija otro.');
            }

            $fromLabel = ServiceOrderFlowTrace::orderProviderLabel($locked);
            $toLabel = (string) ProviderDeletion::displayName($target);

            ServiceOrderFlowTrace::withContext(
                ['action' => self::CONTEXT_REASSIGN, 'reason' => $reason],
                function () use ($locked, $targetType, $targetId, $userName): void {
                    $locked->update([
                        'supplier_id' => $targetType === ProviderDeletion::TYPE_SUPPLIER ? $targetId : null,
                        'doctor_nurse_id' => $targetType === ProviderDeletion::TYPE_DOCTOR_NURSE ? $targetId : null,
                        'supplier_external' => null,
                        'updated_by' => $userName,
                    ]);
                },
            );

            $contacts = $target instanceof Supplier
                ? OperationServiceOrderProviderContacts::fromSupplier($target)
                : OperationServiceOrderProviderContacts::fromDoctorNurse($target);

            self::moveAppointment($locked, $targetType, $targetId, $contacts, $userName);
            self::moveCoordinationAssignment($locked, $fromType, $fromId, $targetType, $targetId, $userName);

            OperationServiceOrderViewActions::appendToCoordinationAndCase(
                $locked,
                self::PREFIX
                    ."\n".'Orden: '.ServiceOrderFlowTrace::orderLabel($locked)
                    ."\n".'Proveedor anterior: '.$fromLabel
                    ."\n".'Proveedor nuevo: '.$toLabel
                    ."\n".'Analista: '.$userName
                    ."\n".'Motivo: '.$reason,
                $userName,
            );

            self::notifyAssignedProvider($locked, $contacts);

            SecurityAudit::log('AUDIT_OPERATIONS_SERVICE_ORDER_REASSIGNED', 'operations.operation-service-orders.reassign', [
                'operation_service_order_id' => $locked->getKey(),
                'order_number' => $locked->order_number,
                'from' => $fromLabel,
                'to' => $toLabel,
                'to_type' => $targetType,
                'to_id' => $targetId,
                'reason' => $reason,
                'notified_email' => $contacts['email'],
                'notified_phone' => $contacts['phone'],
            ]);

            return [
                'order_id' => (int) $locked->getKey(),
                'order_label' => ServiceOrderFlowTrace::orderLabel($locked),
                'from' => $fromLabel,
                'to' => $toLabel,
            ];
        });
    }

    /**
     * @param  array{email: ?string, phone: ?string}  $contacts
     */
    private static function moveAppointment(OperationServiceOrder $order, string $targetType, int $targetId, array $contacts, string $userName): void
    {
        $appointment = OperationMedicalAppointment::query()
            ->where('operation_service_order_id', $order->getKey())
            ->lockForUpdate()
            ->first();

        if (! $appointment instanceof OperationMedicalAppointment || ProviderPendingWork::isAppointmentCancelled($appointment)) {
            return;
        }

        $appointment->forceFill([
            'supplier_id' => $targetType === ProviderDeletion::TYPE_SUPPLIER ? $targetId : null,
            'supplier_external' => null,
            'supplier_notify_email' => $contacts['email'],
            'supplier_notify_phone' => $contacts['phone'],
            'last_changed_at' => now(),
            'last_changed_by' => $userName,
            'updated_by' => $userName,
        ])->save();
    }

    /**
     * El servicio médico solo cambia de proveedor asignado si apuntaba al que entrega la orden.
     */
    private static function moveCoordinationAssignment(OperationServiceOrder $order, ?string $fromType, int $fromId, string $targetType, int $targetId, string $userName): void
    {
        if ($fromType === null || ! filled($order->operation_coordination_service_id)) {
            return;
        }

        $fromColumn = ProviderPendingWork::COORDINATION_ASSIGNED_COLUMN[$fromType];
        $coordination = OperationCoordinationService::query()->withoutGlobalScopes()->lockForUpdate()->find($order->operation_coordination_service_id);

        if (! $coordination instanceof OperationCoordinationService || (int) $coordination->getAttribute($fromColumn) !== $fromId) {
            return;
        }

        $coordination->setAttribute($fromColumn, null);
        $coordination->setAttribute(ProviderPendingWork::COORDINATION_ASSIGNED_COLUMN[$targetType], $targetId);
        $coordination->updated_by = $userName;
        $coordination->save();
    }

    /**
     * Contactos de aviso de un proveedor del catálogo (vigente o eliminado).
     *
     * @return array{email: ?string, phone: ?string, address: ?string, name: ?string, missing: list<string>}
     */
    public static function contactsFor(string $type, int $id): array
    {
        $model = self::targetModel($type, $id, withTrashed: true);

        return match (true) {
            $model instanceof Supplier => OperationServiceOrderProviderContacts::fromSupplier($model),
            $model instanceof DoctorNurse => OperationServiceOrderProviderContacts::fromDoctorNurse($model),
            default => OperationServiceOrderProviderContacts::empty(),
        };
    }

    /**
     * Al proveedor que deja de tener la orden: correo y WhatsApp de «orden retirada».
     *
     * @param  array{email: ?string, phone: ?string}  $contacts
     */
    public static function notifyWithdrawnProvider(OperationServiceOrder $order, array $contacts): void
    {
        if ($contacts['email'] !== null) {
            Mail::to($contacts['email'])->queue((new OperationServiceOrderWithdrawnMail($order))->afterCommit());
        }

        $phone = $contacts['phone'] !== null ? HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($contacts['phone']) : null;

        if ($phone !== null) {
            SendNotificacionWhatsApp::dispatch(
                Auth::id(),
                '⚠️ *ORDEN DE SERVICIO RETIRADA*'
                    ."\n".'La orden *'.ServiceOrderFlowTrace::orderLabel($order).'* ya no está a su cargo: fue reasignada a otro proveedor.'
                    ."\n\n".'Por favor no preste este servicio. Ante cualquier duda, responda este mensaje.',
                $phone,
                null,
                [
                    'panel' => 'operations',
                    'context' => 'service_order_withdrawn',
                    'entity_id' => (int) $order->getKey(),
                ],
            )->afterCommit();
        }
    }

    /**
     * Al proveedor que recibe la orden: PDF por correo y WhatsApp.
     *
     * @param  array{email: ?string, phone: ?string}  $contacts
     */
    public static function notifyAssignedProvider(OperationServiceOrder $order, array $contacts): void
    {
        if ($contacts['email'] !== null) {
            Mail::to($contacts['email'])->queue((new OperationServiceOrderPdfMail($order))->afterCommit());
        }

        $phone = $contacts['phone'] !== null ? HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($contacts['phone']) : null;

        if ($phone !== null) {
            SendNotificacionWhatsApp::dispatch(
                Auth::id(),
                self::whatsAppCaption($order),
                $phone,
                null,
                [
                    'panel' => 'operations',
                    'context' => 'service_order_reassigned',
                    'entity_id' => (int) $order->getKey(),
                ],
            )->afterCommit();
        }

        if ($contacts['email'] === null && $phone === null) {
            Log::warning('Reasignación de orden: el nuevo proveedor no tiene correo ni teléfono válido.', ['order_id' => $order->getKey()]);
        }
    }

    public static function whatsAppCaption(OperationServiceOrder $order): string
    {
        $order->loadMissing(['operationCoordinationService', 'medicalAppointment']);
        $appointment = $order->medicalAppointment;
        $appointmentLine = $appointment instanceof OperationMedicalAppointment && ! ProviderPendingWork::isAppointmentCancelled($appointment)
            ? "\n".'*Cita:* '.MedicalAppointmentManager::formatAppointmentAt($appointment->appointment_at)
            : '';

        return '📄 *ORDEN DE SERVICIO ASIGNADA*'
            ."\n".'Se le asignó esta orden de servicio.'
            ."\n\n".'*Orden:* '.ServiceOrderFlowTrace::orderLabel($order)
            ."\n".'*Paciente:* '.(filled($order->operationCoordinationService?->patient) ? (string) $order->operationCoordinationService->patient : '—')
            ."\n".'*Servicio:* '.(filled($order->service_type) ? (string) $order->service_type : '—')
            .$appointmentLine
            ."\n\n".'Le enviamos el PDF de la orden por correo. Vista previa: '.route('operations.operation-service-orders.pdf.preview', ['operationServiceOrder' => $order]);
    }

    private static function targetModel(string $type, int $id, bool $withTrashed): ?Model
    {
        $model = match ($type) {
            ProviderDeletion::TYPE_SUPPLIER => Supplier::class,
            ProviderDeletion::TYPE_DOCTOR_NURSE => DoctorNurse::class,
            default => null,
        };

        if ($model === null || $id <= 0) {
            return null;
        }

        $query = $model::query();

        return ($withTrashed ? $query->withTrashed() : $query)->find($id);
    }
}
