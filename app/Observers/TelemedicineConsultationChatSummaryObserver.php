<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\PublishConsultationSummaryToCaseChatJob;
use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\ConsultationChatSummary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Al registrar una consulta inicial, de seguimiento o de alta médica, encola su resumen para el
 * chat del caso. Se encola recién cuando la transacción del guardado confirma (así el resumen ve
 * los medicamentos y órdenes) y nunca interrumpe el guardado de la consulta.
 */
class TelemedicineConsultationChatSummaryObserver
{
    public function created(TelemedicineConsultationPatient $consultation): void
    {
        if (blank($consultation->telemedicine_case_id) || ! ConsultationChatSummary::supportsStatus($consultation->status)) {
            return;
        }

        $consultationId = (int) $consultation->getKey();
        $authorUserId = Auth::id();

        DB::afterCommit(static fn () => self::dispatchSafely($consultationId, $authorUserId));
    }

    public static function dispatchSafely(int $consultationId, int|string|null $authorUserId): void
    {
        try {
            PublishConsultationSummaryToCaseChatJob::dispatch($consultationId, $authorUserId !== null ? (int) $authorUserId : null);
        } catch (Throwable $exception) {
            Log::error('No se pudo encolar el resumen de la consulta para el chat del caso.', [
                'telemedicine_consultation_patient_id' => $consultationId,
                'exception' => $exception,
            ]);
        }
    }
}
