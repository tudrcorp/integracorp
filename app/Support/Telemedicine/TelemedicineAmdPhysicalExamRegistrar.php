<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineAmdPhysicalExam;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Guarda el examen físico AMD: uno por consulta.
 *
 * Mismo ciclo que el informe AMD: si la consulta aún no existe, el examen
 * queda pendiente (sin consulta) y se vincula al guardarla. Si el Informe
 * Médico Largo ya se generó, se regenera para que incluya el examen.
 */
final class TelemedicineAmdPhysicalExamRegistrar
{
    public const SESSION_PENDING_EXAM_ID = 'pending_amd_physical_exam_id';

    /**
     * @param  array{telemedicine_patient_id: int, telemedicine_case_id: int, telemedicine_doctor_id?: int|null}  $context
     * @param  array<string, mixed>  $data
     */
    public static function save(
        array $context,
        array $data,
        ?TelemedicineConsultationPatient $consultation = null,
        ?int $pendingExamId = null,
        ?User $user = null,
    ): TelemedicineAmdPhysicalExam {
        $user ??= Auth::user();
        $caseId = (int) $context['telemedicine_case_id'];

        if ($caseId < 1 || (int) $context['telemedicine_patient_id'] < 1) {
            throw new \InvalidArgumentException('El examen físico necesita un caso y un paciente.');
        }

        $exam = DB::transaction(function () use ($context, $data, $consultation, $pendingExamId, $user, $caseId): TelemedicineAmdPhysicalExam {
            $existing = $consultation instanceof TelemedicineConsultationPatient
                ? self::forConsultation((int) $consultation->id)
                : self::pendingForCase($caseId, $pendingExamId, $user?->id);

            $attributes = [
                ...self::clinicalAttributes($data),
                'telemedicine_patient_id' => (int) $context['telemedicine_patient_id'],
                'telemedicine_case_id' => $caseId,
                'telemedicine_consultation_patient_id' => $consultation?->id,
                'telemedicine_doctor_id' => $context['telemedicine_doctor_id'] ?? $consultation?->telemedicine_doctor_id,
                'updated_by' => $user?->id,
            ];

            if ($existing instanceof TelemedicineAmdPhysicalExam) {
                $existing->update($attributes);

                return $existing->refresh();
            }

            return TelemedicineAmdPhysicalExam::query()->create([...$attributes, 'created_by' => $user?->id]);
        });

        if ($consultation instanceof TelemedicineConsultationPatient) {
            TelemedicineAmdInformRegistrar::regeneratePdfForConsultation($consultation);
        }

        return $exam;
    }

    /**
     * Vincula el examen pendiente a la consulta recién guardada. Si la consulta
     * ya tenía uno (no debería), el pendiente lo reemplaza: es el más reciente.
     */
    public static function attachPendingToConsultation(
        TelemedicineConsultationPatient $consultation,
        ?int $pendingExamId = null,
    ): ?TelemedicineAmdPhysicalExam {
        $pending = self::pendingForCase((int) $consultation->telemedicine_case_id, $pendingExamId);

        if (! $pending instanceof TelemedicineAmdPhysicalExam) {
            return null;
        }

        $exam = DB::transaction(function () use ($pending, $consultation): TelemedicineAmdPhysicalExam {
            self::forConsultation((int) $consultation->id)?->delete();

            $pending->update([
                'telemedicine_consultation_patient_id' => $consultation->id,
                'telemedicine_patient_id' => $consultation->telemedicine_patient_id,
                'telemedicine_doctor_id' => $consultation->telemedicine_doctor_id ?? $pending->telemedicine_doctor_id,
            ]);

            return $pending->refresh();
        });

        session()->forget(self::SESSION_PENDING_EXAM_ID);

        return $exam;
    }

    public static function forConsultation(int $consultationId): ?TelemedicineAmdPhysicalExam
    {
        if ($consultationId < 1) {
            return null;
        }

        return TelemedicineAmdPhysicalExam::query()
            ->where('telemedicine_consultation_patient_id', $consultationId)
            ->first();
    }

    /**
     * Examen que corresponde a un informe: el de la consulta o, si la consulta
     * aún no existe (informe pendiente), el pendiente del caso.
     */
    public static function forInform(int $caseId, int $consultationId): ?TelemedicineAmdPhysicalExam
    {
        return $consultationId > 0
            ? self::forConsultation($consultationId)
            : self::pendingForCase($caseId);
    }

    public static function pendingForCase(int $caseId, ?int $pendingExamId = null, ?int $createdBy = null): ?TelemedicineAmdPhysicalExam
    {
        if ($caseId < 1) {
            return null;
        }

        $query = TelemedicineAmdPhysicalExam::query()
            ->where('telemedicine_case_id', $caseId)
            ->whereNull('telemedicine_consultation_patient_id');

        if ($pendingExamId !== null && $pendingExamId > 0) {
            $byId = (clone $query)->whereKey($pendingExamId)->first();

            if ($byId instanceof TelemedicineAmdPhysicalExam) {
                return $byId;
            }
        }

        if ($createdBy !== null) {
            $query->where('created_by', $createdBy);
        }

        return $query->latest('id')->first();
    }

    /**
     * Sólo las columnas clínicas, recortadas; un texto vacío queda nulo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private static function clinicalAttributes(array $data): array
    {
        $out = [];

        foreach (TelemedicineAmdPhysicalExamTemplate::columns() as $column) {
            $value = trim((string) ($data[$column] ?? ''));

            if (array_key_exists($column, TelemedicineAmdPhysicalExamTemplate::VITALS)) {
                $value = mb_substr($value, 0, TelemedicineAmdPhysicalExamTemplate::VITAL_MAX_LENGTH);
            }

            $out[$column] = $value === '' ? null : $value;
        }

        return $out;
    }
}
