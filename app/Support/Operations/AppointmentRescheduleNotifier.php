<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Jobs\SendAppointmentRescheduleEmail;
use App\Jobs\SendNotificacionWhatsApp;
use App\Models\OperationMedicalAppointment;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class AppointmentRescheduleNotifier
{
    public static function dispatchForAppointment(int $appointmentId): void
    {
        $appointment = OperationMedicalAppointment::query()
            ->with([
                'operationServiceOrder',
                'telemedicinePatient',
                'telemedicineCase',
                'operationCoordinationService',
                'supplier',
            ])
            ->find($appointmentId);

        if (! $appointment instanceof OperationMedicalAppointment) {
            return;
        }

        $email = filled($appointment->supplier_notify_email)
            ? trim((string) $appointment->supplier_notify_email)
            : null;
        $phone = filled($appointment->supplier_notify_phone)
            ? trim((string) $appointment->supplier_notify_phone)
            : null;

        if ($email === null || $phone === null) {
            throw new InvalidArgumentException('No hay correo o teléfono del proveedor para notificar el cambio de cita.');
        }

        $normalized = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone);

        if ($normalized === null) {
            throw new InvalidArgumentException('El teléfono del proveedor no es válido para WhatsApp.');
        }

        SendAppointmentRescheduleEmail::dispatch($appointmentId);

        SendNotificacionWhatsApp::dispatch(
            Auth::id(),
            self::buildWhatsAppCaption($appointment),
            $normalized,
            null,
            [
                'panel' => 'operations',
                'context' => 'appointment_reschedule',
                'entity_id' => $appointmentId,
            ],
        );

        Log::info('CITA: notificación de reprogramación enviada al proveedor.', [
            'appointment_id' => $appointmentId,
            'email' => $email,
            'phone' => $normalized,
        ]);
    }

    public static function buildWhatsAppCaption(OperationMedicalAppointment $appointment): string
    {
        $orderNumber = (string) ($appointment->operationServiceOrder?->order_number ?? '#'.$appointment->operation_service_order_id);
        $patient = (string) ($appointment->telemedicinePatient?->full_name
            ?? $appointment->operationCoordinationService?->patient
            ?? '—');
        $caseCode = filled($appointment->telemedicineCase?->code)
            ? mb_strtoupper((string) $appointment->telemedicineCase->code)
            : '—';
        $previous = MedicalAppointmentManager::formatAppointmentAt($appointment->previous_appointment_at);
        $next = MedicalAppointmentManager::formatAppointmentAt($appointment->appointment_at);
        $reason = (string) ($appointment->last_change_reason ?? '—');

        return <<<TEXT
        📅 *CAMBIO DE FECHA DE CITA*
        El paciente reprogramó la asistencia acordada.

        *Orden:* {$orderNumber}
        *Paciente:* {$patient}
        *Caso:* {$caseCode}
        *Fecha anterior:* {$previous}
        *Fecha nueva:* {$next}
        *Motivo:* {$reason}

        Favor actualizar su agenda con la nueva fecha.
        TEXT;
    }
}
