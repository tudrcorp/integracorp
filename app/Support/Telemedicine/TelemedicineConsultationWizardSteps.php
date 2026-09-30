<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Claves fijas de los pasos del asistente de consulta. La revisión las usa
 * para llevar al médico al paso exacto que quiere corregir.
 */
final class TelemedicineConsultationWizardSteps
{
    public const PATIENT = 'consulta-datos-paciente';

    public const REASON = 'consulta-motivo';

    public const FOLLOW_UP = 'consulta-seguimiento';

    public const MEDICATIONS = 'consulta-medicamentos';

    public const LABS = 'consulta-laboratorios';

    public const SPECIALIST = 'consulta-especialista';

    public const REVIEW = 'consulta-revision';
}
