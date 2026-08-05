<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\AppointmentRescheduleMail;
use App\Models\OperationMedicalAppointment;
use App\Support\Operations\MedicalAppointmentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAppointmentRescheduleEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(
        public int $appointmentId,
    ) {}

    public function handle(): void
    {
        try {
            $appointment = OperationMedicalAppointment::query()
                ->with([
                    'operationServiceOrder',
                    'telemedicinePatient',
                    'telemedicineCase',
                    'operationCoordinationService',
                    'supplier',
                ])
                ->find($this->appointmentId);

            if (! $appointment instanceof OperationMedicalAppointment) {
                return;
            }

            $email = filled($appointment->supplier_notify_email)
                ? trim((string) $appointment->supplier_notify_email)
                : null;

            if ($email === null) {
                return;
            }

            $orderLabel = (string) ($appointment->operationServiceOrder?->order_number
                ?? 'OS #'.$appointment->operation_service_order_id);

            $details = [
                'Orden' => $orderLabel,
                'Paciente' => (string) ($appointment->telemedicinePatient?->full_name
                    ?? $appointment->operationCoordinationService?->patient
                    ?? '—'),
                'Caso' => filled($appointment->telemedicineCase?->code)
                    ? mb_strtoupper((string) $appointment->telemedicineCase->code)
                    : '—',
                'Proveedor' => MedicalAppointmentManager::supplierLabel($appointment),
                'Fecha anterior' => MedicalAppointmentManager::formatAppointmentAt($appointment->previous_appointment_at),
                'Fecha nueva' => MedicalAppointmentManager::formatAppointmentAt($appointment->appointment_at),
                'Motivo' => (string) ($appointment->last_change_reason ?? '—'),
            ];

            Mail::to($email)->send(new AppointmentRescheduleMail($orderLabel, $details));
        } catch (Throwable $e) {
            Log::error('CITA: Fallo al enviar correo de reprogramación al proveedor.', [
                'appointment_id' => $this->appointmentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
