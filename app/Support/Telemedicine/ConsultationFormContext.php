<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;

/**
 * Contexto clínico con el que se arma el formulario de consulta.
 *
 * Antes cada pieza del formulario leía `session('case')` / `session('patient')`
 * directamente. La sesión de Laravel es **una sola por usuario**: con dos
 * pestañas abiertas —lo normal en una guardia— la segunda reescribía el caso de
 * la primera y la primera seguía renderizando con datos ajenos (código del caso,
 * conteo de consultas, guard de alta, «Asociar Antecedente»).
 *
 * El contexto viaja ahora desde el componente Livewire, que sí es por pestaña.
 * {@see ProvidesConsultationFormContext}. {@see fromSession()} queda solo como
 * respaldo de enlaces antiguos que todavía no traen el caso en la URL.
 */
final class ConsultationFormContext
{
    public function __construct(
        public readonly ?TelemedicineCase $case = null,
        public readonly ?TelemedicinePatient $patient = null,
        public readonly ?TelemedicineConsultationPatient $consultation = null,
        public readonly ?string $action = null,
        public readonly ?string $status = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Respaldo heredado: reconstruye el contexto desde las claves de sesión.
     *
     * No usar en código nuevo. Solo cubre el caso de un enlace o marcador viejo
     * que llega sin el caso en la URL.
     */
    public static function fromSession(): self
    {
        $case = session()->get('case');
        $patient = session()->get('patient');
        $consultation = session()->get('consultation');
        $action = session()->get('action');
        $status = session()->get('status');

        return new self(
            $case instanceof TelemedicineCase ? $case : null,
            $patient instanceof TelemedicinePatient ? $patient : null,
            $consultation instanceof TelemedicineConsultationPatient ? $consultation : null,
            is_string($action) ? $action : null,
            is_string($status) ? $status : null,
        );
    }

    public function caseId(): ?int
    {
        return $this->case instanceof TelemedicineCase ? (int) $this->case->id : null;
    }

    public function patientId(): ?int
    {
        return $this->patient instanceof TelemedicinePatient ? (int) $this->patient->id : null;
    }

    /**
     * El médico está editando la consulta inicial del caso.
     *
     * En ese modo el asistente muestra el paso «Motivo de la Consulta» y oculta
     * el cuestionario de seguimiento, aunque el caso ya tenga consultas.
     */
    public function isEditingInitialConsultation(): bool
    {
        return $this->action === 'edit' && $this->status === 'CONSULTA INICIAL';
    }

    /**
     * Servicio con el que se precarga el select cuando se reabre una consulta.
     */
    public function defaultServiceListId(): ?int
    {
        if (! $this->consultation instanceof TelemedicineConsultationPatient) {
            return null;
        }

        if (filled($this->consultation->telemedicine_service_list_drift_id)) {
            return (int) $this->consultation->telemedicine_service_list_drift_id;
        }

        if (filled($this->consultation->telemedicine_service_list_id)) {
            return (int) $this->consultation->telemedicine_service_list_id;
        }

        return null;
    }
}
