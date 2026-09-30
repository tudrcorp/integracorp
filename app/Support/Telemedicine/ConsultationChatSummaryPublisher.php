<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseMessage;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Publica en el chat del caso el resumen de una consulta. Idempotente: la columna
 * `telemedicine_consultation_patient_id` es única, así que un reintento no duplica el mensaje.
 */
final class ConsultationChatSummaryPublisher
{
    public static function publish(int $consultationId, ?int $authorUserId = null): ?TelemedicineCaseMessage
    {
        $existing = TelemedicineCaseMessage::query()
            ->where('telemedicine_consultation_patient_id', $consultationId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $consultation = TelemedicineConsultationPatient::query()->find($consultationId);

        if (! $consultation instanceof TelemedicineConsultationPatient
            || blank($consultation->telemedicine_case_id)
            || ! ConsultationChatSummary::supportsStatus($consultation->status)) {
            return null;
        }

        $caseExists = TelemedicineCase::query()->whereKey($consultation->telemedicine_case_id)->exists();

        if (! $caseExists) {
            return null;
        }

        $authorId = self::resolveAuthorId($consultation, $authorUserId);

        if ($authorId === null) {
            Log::warning('Resumen de consulta no publicado en el chat: no se encontró un usuario autor.', [
                'telemedicine_consultation_patient_id' => $consultationId,
                'telemedicine_doctor_id' => $consultation->telemedicine_doctor_id,
            ]);

            return null;
        }

        $summary = ConsultationChatSummary::build($consultation);

        try {
            $message = TelemedicineCaseMessage::query()->create([
                'telemedicine_case_id' => (int) $consultation->telemedicine_case_id,
                'user_id' => $authorId,
                'body' => $summary['body'],
                'kind' => TelemedicineCaseMessage::KIND_CONSULTATION_SUMMARY,
                'telemedicine_consultation_patient_id' => $consultationId,
                'meta' => [
                    'title' => $summary['title'],
                    'subtitle' => $summary['subtitle'],
                    'status' => $summary['status'],
                    'sections' => $summary['sections'],
                ],
            ]);
        } catch (UniqueConstraintViolationException) {
            return TelemedicineCaseMessage::query()
                ->where('telemedicine_consultation_patient_id', $consultationId)
                ->first();
        }

        /** Sube el caso en la lista del chat sin disparar los observers del caso. */
        TelemedicineCase::query()
            ->whereKey($consultation->telemedicine_case_id)
            ->toBase()
            ->update(['updated_at' => now()]);

        return $message;
    }

    /**
     * Quien registró la consulta; si no se conoce (proceso sin sesión), el usuario del doctor.
     * El resumen no cuenta como «no leído» para su autor.
     */
    private static function resolveAuthorId(TelemedicineConsultationPatient $consultation, ?int $authorUserId): ?int
    {
        if ($authorUserId !== null && User::query()->whereKey($authorUserId)->exists()) {
            return $authorUserId;
        }

        if (filled($consultation->telemedicine_doctor_id)) {
            $doctorUserId = User::query()
                ->where('doctor_id', $consultation->telemedicine_doctor_id)
                ->orderBy('id')
                ->value('id');

            if ($doctorUserId !== null) {
                return (int) $doctorUserId;
            }
        }

        return null;
    }
}
