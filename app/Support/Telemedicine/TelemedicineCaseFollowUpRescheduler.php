<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseFollowUpReschedule;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineOperationsLog;
use App\Models\User;
use App\Support\SecurityAudit;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reasigna el «Próximo seguimiento» de un caso EN SEGUIMIENTO desde el escritorio
 * del médico, sin registrar una consulta nueva.
 *
 * El nuevo intervalo cuenta desde el momento de la reasignación. La consulta
 * original no se toca: la reprogramación queda en {@see TelemedicineCaseFollowUpReschedule}
 * (fuente de {@see TelemedicineCaseFollowUpSchedule::nextFollowUpSql()}) y en la
 * Bitácora operativa del caso, ambas en la misma transacción.
 */
final class TelemedicineCaseFollowUpRescheduler
{
    public const STATUS = 'EN SEGUIMIENTO';

    public const OPERATION = 'REASIGNACIÓN DE SEGUIMIENTO';

    public const OBSERVATION_MIN_LENGTH = 5;

    public const OBSERVATION_MAX_LENGTH = 255;

    public static function caseCanBeRescheduled(?TelemedicineCase $case): bool
    {
        return $case !== null && mb_strtoupper(trim((string) $case->status)) === self::STATUS;
    }

    /**
     * @throws DomainException con un mensaje en español apto para mostrar al médico
     */
    public static function reschedule(TelemedicineCase $case, mixed $priorityMonitoring, mixed $observation, mixed $user, ?CarbonInterface $now = null): TelemedicineCaseFollowUpReschedule
    {
        $interval = is_numeric($priorityMonitoring) ? (int) $priorityMonitoring : 0;
        $minutes = array_key_exists($interval, TelemedicineCaseFollowUpSchedule::OPTIONS)
            ? TelemedicineCaseFollowUpSchedule::minutesFor($interval)
            : null;

        if ($minutes === null) {
            throw new DomainException('Seleccione un «Próximo seguimiento» de la lista.');
        }

        $note = Str::squish(is_string($observation) ? $observation : '');

        if (mb_strlen($note) < self::OBSERVATION_MIN_LENGTH) {
            throw new DomainException('La observación es obligatoria: explique por qué se reasigna el seguimiento.');
        }

        if (mb_strlen($note) > self::OBSERVATION_MAX_LENGTH) {
            throw new DomainException('La observación no puede superar '.self::OBSERVATION_MAX_LENGTH.' caracteres.');
        }

        if (! $user instanceof User || ! TelemedicineCaseFilamentListQuery::caseBelongsToUserDoctorTeam($user, $case)) {
            throw new DomainException('El caso no pertenece a su equipo médico.');
        }

        $doctor = TelemedicineConsultationSigningDoctor::forUser($user);

        return DB::transaction(function () use ($case, $interval, $minutes, $note, $user, $doctor, $now): TelemedicineCaseFollowUpReschedule {
            /** @var TelemedicineCase|null $locked */
            $locked = TelemedicineCase::query()->whereKey($case->getKey())->lockForUpdate()->first();

            if (! self::caseCanBeRescheduled($locked)) {
                throw new DomainException('Solo se puede reasignar el seguimiento de un caso EN SEGUIMIENTO.');
            }

            $latestConsultation = TelemedicineConsultationPatient::query()
                ->where('telemedicine_case_id', $locked->id)
                ->orderByDesc('id')
                ->first(['id', 'telemedicine_patient_id']);

            if ($latestConsultation === null) {
                throw new DomainException('El caso aún no tiene consultas: registre la primera consulta.');
            }

            $previous = TelemedicineCaseFollowUpSchedule::withFollowUpColumns(TelemedicineCase::query()->whereKey($locked->id))
                ->value('next_follow_up_at');

            $at = Carbon::instance(($now ?? now())->toDateTime());
            $nextFollowUpAt = $at->copy()->addMinutes($minutes);

            $reschedule = new TelemedicineCaseFollowUpReschedule;
            $reschedule->forceFill([
                'telemedicine_case_id' => $locked->id,
                'telemedicine_doctor_id' => $doctor?->id,
                'user_id' => $user->id,
                'priority_monitoring' => $interval,
                'previous_next_follow_up_at' => filled($previous) ? Carbon::parse((string) $previous) : null,
                'next_follow_up_at' => $nextFollowUpAt,
                'observation' => $note,
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();

            $responsable = trim((string) ($doctor?->full_name ?: $user->name));

            TelemedicineOperationsLog::query()->create([
                'telemedicine_patient_id' => (int) ($locked->telemedicine_patient_id ?? $latestConsultation->telemedicine_patient_id),
                'telemedicine_case_id' => $locked->id,
                'telemedicine_consultation_patient_id' => $latestConsultation->id,
                'code_reference' => (string) $locked->code,
                'operation' => self::OPERATION,
                'description' => self::description($interval, $nextFollowUpAt, $previous),
                'status' => self::STATUS,
                'observations' => $note,
                'responsable' => mb_substr($responsable !== '' ? $responsable : 'Usuario #'.$user->id, 0, 255),
            ]);

            SecurityAudit::log('AUDIT_TELEMEDICINE_CASE_FOLLOW_UP_RESCHEDULED', 'telemedicina.dashboard.reschedule-follow-up', [
                'telemedicine_case_id' => $locked->id,
                'telemedicine_case_code' => $locked->code,
                'reschedule_id' => $reschedule->id,
                'priority_monitoring' => $interval,
                'next_follow_up_at' => $nextFollowUpAt->toDateTimeString(),
                'previous_next_follow_up_at' => filled($previous) ? (string) $previous : null,
                'telemedicine_doctor_id' => $doctor?->id,
            ]);

            return $reschedule;
        });
    }

    public static function description(int $interval, CarbonInterface $nextFollowUpAt, mixed $previous): string
    {
        $previousText = filled($previous)
            ? Carbon::parse((string) $previous)->format('d/m/Y h:i A')
            : 'sin programar';

        return mb_substr(sprintf(
            'Próximo seguimiento reasignado a %s: %s (antes: %s).',
            TelemedicineCaseFollowUpSchedule::optionLabel($interval),
            $nextFollowUpAt->format('d/m/Y h:i A'),
            $previousText,
        ), 0, 255);
    }
}
