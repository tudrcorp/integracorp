<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicalAppointment;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MedicalAppointmentManager
{
    public const ASSIGN_PREFIX = 'Asignación de fecha de cita.';

    public const RESCHEDULE_PREFIX = 'Reprogramación de cita.';

    /**
     * @return list<string>
     */
    public static function presencialServiceTypes(): array
    {
        return ['LABORATORIOS', 'IMAGENOLOGIA', 'ESPECIALISTA'];
    }

    public static function serviceTypeRequiresAppointment(?string $serviceType): bool
    {
        return in_array(mb_strtoupper(trim((string) $serviceType)), self::presencialServiceTypes(), true);
    }

    /**
     * @return array{email: ?string, phone: ?string}
     */
    public static function resolveSupplierNotifyContacts(?Supplier $supplier, ?string $emailOverride = null, ?string $phoneOverride = null): array
    {
        $email = filled($emailOverride)
            ? trim((string) $emailOverride)
            : (filled($supplier?->correo_principal) ? trim((string) $supplier->correo_principal) : null);

        $phone = filled($phoneOverride)
            ? trim((string) $phoneOverride)
            : (filled($supplier?->personal_phone)
                ? trim((string) $supplier->personal_phone)
                : (filled($supplier?->local_phone) ? trim((string) $supplier->local_phone) : null));

        return [
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
        ];
    }

    public static function supplierNeedsManualNotifyContacts(?int $supplierId, bool $isUnregistered = false): bool
    {
        if ($isUnregistered || $supplierId === null || $supplierId <= 0) {
            return true;
        }

        $supplier = Supplier::query()->find($supplierId);
        $contacts = self::resolveSupplierNotifyContacts($supplier);

        return $contacts['email'] === null || $contacts['phone'] === null;
    }

    /**
     * @param  array{email?: ?string, phone?: ?string}  $notifyContacts
     */
    public static function createFromServiceOrder(OperationServiceOrder $order, array $notifyContacts = []): ?OperationMedicalAppointment
    {
        if (! self::serviceTypeRequiresAppointment($order->service_type)) {
            return null;
        }

        if ($order->appointment_at === null) {
            return null;
        }

        $existing = OperationMedicalAppointment::query()
            ->where('operation_service_order_id', $order->id)
            ->first();

        if ($existing instanceof OperationMedicalAppointment) {
            return $existing;
        }

        $order->loadMissing(['operationCoordinationService', 'supplier']);
        $coordination = $order->operationCoordinationService;
        $contacts = self::resolveSupplierNotifyContacts(
            $order->supplier,
            $notifyContacts['email'] ?? null,
            $notifyContacts['phone'] ?? null,
        );

        $userName = Auth::user()?->name ?? 'SISTEMA';

        return DB::transaction(function () use ($order, $coordination, $contacts, $userName): OperationMedicalAppointment {
            $appointment = OperationMedicalAppointment::query()->create([
                'operation_service_order_id' => $order->id,
                'telemedicine_patient_id' => $coordination?->telemedicine_patient_id,
                'telemedicine_case_id' => $coordination?->telemedicine_case_id,
                'operation_coordination_service_id' => $coordination?->id ?? $order->operation_coordination_service_id,
                'supplier_id' => $order->supplier_id,
                'supplier_external' => $order->supplier_external,
                'supplier_notify_email' => $contacts['email'],
                'supplier_notify_phone' => $contacts['phone'],
                'appointment_at' => $order->appointment_at,
                'status' => OperationMedicalAppointment::STATUS_SCHEDULED,
                'created_by' => $userName,
                'updated_by' => $userName,
            ]);

            self::appendBitacora(
                $coordination,
                self::ASSIGN_PREFIX
                ."\n".'Orden: '.($order->order_number ?? '#'.$order->id)
                ."\n".'Fecha/hora: '.self::formatAppointmentAt($order->appointment_at)
                ."\n".'Proveedor: '.self::supplierLabel($order)
                ."\n".'Analista: '.$userName,
                Auth::user(),
            );

            return $appointment;
        });
    }

    /**
     * @param  array{appointment_at: mixed, reason: string, email?: ?string, phone?: ?string}  $data
     */
    public static function reschedule(OperationMedicalAppointment $appointment, array $data, ?User $user = null): OperationMedicalAppointment
    {
        $user ??= Auth::user();
        $reason = trim((string) ($data['reason'] ?? ''));

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('El motivo del cambio debe tener al menos 10 caracteres.');
        }

        $newAt = $data['appointment_at'] ?? null;

        if (! $newAt instanceof CarbonInterface && ! is_string($newAt)) {
            throw new InvalidArgumentException('Debe indicar la nueva fecha y hora de la cita.');
        }

        $newAt = $newAt instanceof CarbonInterface ? $newAt : \Illuminate\Support\Carbon::parse((string) $newAt);
        $contacts = self::resolveSupplierNotifyContacts(
            $appointment->supplier ?? $appointment->operationServiceOrder?->supplier,
            $data['email'] ?? $appointment->supplier_notify_email,
            $data['phone'] ?? $appointment->supplier_notify_phone,
        );

        if ($contacts['email'] === null || $contacts['phone'] === null) {
            throw new InvalidArgumentException('Debe capturar correo y teléfono del proveedor para notificar el cambio de cita.');
        }

        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $previousAt = $appointment->appointment_at;

        return DB::transaction(function () use ($appointment, $newAt, $reason, $contacts, $userName, $previousAt, $user): OperationMedicalAppointment {
            $appointment->forceFill([
                'previous_appointment_at' => $previousAt,
                'appointment_at' => $newAt,
                'last_change_reason' => $reason,
                'last_changed_at' => now(),
                'last_changed_by' => $userName,
                'supplier_notify_email' => $contacts['email'],
                'supplier_notify_phone' => $contacts['phone'],
                'status' => OperationMedicalAppointment::STATUS_RESCHEDULED,
                'updated_by' => $userName,
            ])->save();

            $order = $appointment->operationServiceOrder;

            if ($order instanceof OperationServiceOrder) {
                $order->forceFill([
                    'appointment_at' => $newAt,
                    'updated_by' => $userName,
                ])->save();
            }

            $coordination = $appointment->operationCoordinationService
                ?? $order?->operationCoordinationService;

            self::appendBitacora(
                $coordination,
                self::RESCHEDULE_PREFIX
                ."\n".'Orden: '.($order?->order_number ?? '#'.($order?->id ?? '—'))
                ."\n".'Fecha anterior: '.self::formatAppointmentAt($previousAt)
                ."\n".'Fecha nueva: '.self::formatAppointmentAt($newAt)
                ."\n".'Motivo: '.$reason
                ."\n".'Analista: '.$userName,
                $user,
            );

            AppointmentRescheduleNotifier::dispatchForAppointment((int) $appointment->id);

            return $appointment->fresh() ?? $appointment;
        });
    }

    public static function formatAppointmentAt(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
        }

        if (filled($value)) {
            return \Illuminate\Support\Carbon::parse((string) $value)
                ->timezone((string) config('app.timezone'))
                ->format('d/m/Y H:i');
        }

        return '—';
    }

    public static function supplierLabel(OperationServiceOrder|OperationMedicalAppointment $record): string
    {
        if ($record instanceof OperationMedicalAppointment) {
            if (filled($record->supplier?->name)) {
                return (string) $record->supplier->name;
            }

            if (filled($record->supplier_external)) {
                return (string) $record->supplier_external;
            }

            $order = $record->operationServiceOrder;

            return $order instanceof OperationServiceOrder ? self::supplierLabel($order) : '—';
        }

        if (filled($record->supplier?->name)) {
            return (string) $record->supplier->name;
        }

        if (filled($record->supplier_external)) {
            return (string) $record->supplier_external;
        }

        return '—';
    }

    private static function appendBitacora(?OperationCoordinationService $coordination, string $bitacora, ?User $user): void
    {
        if (! $coordination instanceof OperationCoordinationService) {
            return;
        }

        $previous = trim((string) ($coordination->observations ?? ''));
        $coordination->observations = $previous !== '' ? $previous."\n\n".$bitacora : $bitacora;
        $coordination->updated_by = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $coordination->save();

        if (filled($coordination->telemedicine_case_id)) {
            ObservationCase::query()->create([
                'telemedicine_case_id' => $coordination->telemedicine_case_id,
                'description' => $bitacora,
                'created_by' => $user?->id !== null ? (string) $user->id : null,
            ]);
        }
    }
}
