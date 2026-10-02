<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseFollowUpReschedule;
use App\Models\TelemedicineConsultationPatient;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Próximo seguimiento de un caso: fecha de su última consulta + el intervalo
 * que el médico eligió en «Próximo Seguimiento» (`priorityMonitoring`).
 *
 * El campo guarda minutos (30…180) y, en los seguimientos, horas (24/48/72).
 * Todo se calcula en SQL para poder ordenar y filtrar la tabla sin consultas
 * por fila.
 */
final class TelemedicineCaseFollowUpSchedule
{
    /**
     * Valores de `priorityMonitoring` que son horas; el resto son minutos.
     *
     * @var list<int>
     */
    public const HOUR_VALUES = [24, 48, 72];

    /**
     * A menos de este tiempo el seguimiento se marca como próximo.
     */
    public const SOON_MINUTES = 60;

    public const TONE_PENDING = 'pending';

    public const TONE_OVERDUE = 'overdue';

    public const TONE_SOON = 'soon';

    public const TONE_SCHEDULED = 'scheduled';

    public const TONE_NONE = 'none';

    /**
     * Minutos que representa un valor de «Próximo Seguimiento».
     */
    public static function minutesFor(mixed $priorityMonitoring): ?int
    {
        $value = (int) $priorityMonitoring;

        if ($value <= 0) {
            return null;
        }

        return in_array($value, self::HOUR_VALUES, true) ? $value * 60 : $value;
    }

    /**
     * Opciones de «Próximo Seguimiento»: las mismas del formulario de seguimiento.
     *
     * @var array<int, string>
     */
    public const OPTIONS = [
        30 => '30 minutos',
        60 => '60 minutos',
        90 => '90 minutos',
        120 => '120 minutos',
        150 => '150 minutos',
        180 => '180 minutos',
        24 => '24 horas',
        48 => '48 horas',
        72 => '72 horas',
    ];

    /**
     * Etiqueta de un valor guardado: 24/48/72 son horas, el resto minutos (igual que {@see minutesFor()}).
     */
    public static function optionLabel(mixed $priorityMonitoring): string
    {
        $value = is_numeric($priorityMonitoring) ? (int) $priorityMonitoring : 0;

        if ($value <= 0) {
            return '—';
        }

        return self::OPTIONS[$value] ?? $value.' minutos';
    }

    /**
     * Expresión SQL con la fecha del próximo seguimiento del caso de la fila.
     *
     * Manda la última reprogramación ({@see TelemedicineCaseFollowUpReschedule})
     * si es igual o más reciente que la última consulta; si después llega una
     * consulta nueva, vuelve a mandar la consulta.
     */
    public static function nextFollowUpSql(): string
    {
        $cases = (new TelemedicineCase)->getTable();
        $consultations = (new TelemedicineConsultationPatient)->getTable();
        $reschedules = (new TelemedicineCaseFollowUpReschedule)->getTable();
        $hours = implode(', ', self::HOUR_VALUES);

        $fromConsultation = "(select date_add(lc.created_at, interval (case when lc.priorityMonitoring in ({$hours}) then lc.priorityMonitoring * 60 else lc.priorityMonitoring end) minute)"
            ." from {$consultations} lc"
            ." where lc.id = (select max(cmx.id) from {$consultations} cmx where cmx.telemedicine_case_id = {$cases}.id)"
            .' and lc.priorityMonitoring > 0)';

        $fromReschedule = "(select rs.next_follow_up_at from {$reschedules} rs"
            ." where rs.id = (select max(rsx.id) from {$reschedules} rsx where rsx.telemedicine_case_id = {$cases}.id)"
            ." and rs.created_at >= coalesce((select rc.created_at from {$consultations} rc where rc.id = (select max(rcx.id) from {$consultations} rcx where rcx.telemedicine_case_id = {$cases}.id)), '1000-01-01'))";

        return "coalesce({$fromReschedule}, {$fromConsultation})";
    }

    /**
     * Expresión SQL: 1 si el caso aún no tiene ninguna consulta.
     */
    public static function pendingFirstConsultationSql(): string
    {
        $cases = (new TelemedicineCase)->getTable();
        $consultations = (new TelemedicineConsultationPatient)->getTable();

        return "(not exists (select 1 from {$consultations} fc where fc.telemedicine_case_id = {$cases}.id))";
    }

    /**
     * Agrega `next_follow_up_at` y `pending_first_consultation` a la consulta.
     *
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function withFollowUpColumns(Builder $query): Builder
    {
        $cases = (new TelemedicineCase)->getTable();

        if ($query->getQuery()->columns === null) {
            $query->select("{$cases}.*");
        }

        return $query
            ->selectRaw(self::nextFollowUpSql().' as next_follow_up_at')
            ->selectRaw(self::pendingFirstConsultationSql().' as pending_first_consultation');
    }

    /**
     * Primero los pendientes de primera consulta, luego el seguimiento más
     * cercano (los vencidos, que son los más antiguos, quedan arriba) y al
     * final los sin programar. Requiere {@see withFollowUpColumns()}.
     *
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function orderByNextFollowUp(Builder $query): Builder
    {
        $cases = (new TelemedicineCase)->getTable();

        return $query
            ->orderByRaw('pending_first_consultation desc')
            ->orderByRaw('next_follow_up_at is null')
            ->orderBy('next_follow_up_at')
            ->orderByDesc("{$cases}.created_at");
    }

    /**
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function whereOverdue(Builder $query, ?CarbonInterface $now = null): Builder
    {
        return $query->whereRaw(self::nextFollowUpSql().' < ?', [($now ?? now())->toDateTimeString()]);
    }

    /**
     * Texto para la celda «Próximo seguimiento».
     *
     * @return array{label: string, detail: string|null, tone: string}
     */
    public static function describe(mixed $nextFollowUpAt, bool $pendingFirstConsultation, ?CarbonInterface $now = null): array
    {
        if ($pendingFirstConsultation) {
            return ['label' => 'Pendiente de primera consulta', 'detail' => null, 'tone' => self::TONE_PENDING];
        }

        if (blank($nextFollowUpAt)) {
            return ['label' => 'Sin programar', 'detail' => null, 'tone' => self::TONE_NONE];
        }

        $now ??= now();
        $at = $nextFollowUpAt instanceof CarbonInterface ? $nextFollowUpAt : Carbon::parse((string) $nextFollowUpAt);
        $minutes = (int) floor($now->diffInMinutes($at, false));
        $date = $at->isSameDay($now)
            ? 'Hoy '.$at->format('h:i A')
            : ($at->isSameDay($now->copy()->addDay()) ? 'Mañana '.$at->format('h:i A') : $at->format('d/m/Y h:i A'));

        if ($minutes < 0) {
            return ['label' => 'Vencido hace '.self::duration(abs($minutes)), 'detail' => $date, 'tone' => self::TONE_OVERDUE];
        }

        if ($minutes <= self::SOON_MINUTES) {
            return ['label' => $minutes === 0 ? 'Ahora' : 'En '.self::duration($minutes), 'detail' => $date, 'tone' => self::TONE_SOON];
        }

        return ['label' => $date, 'detail' => 'En '.self::duration($minutes), 'tone' => self::TONE_SCHEDULED];
    }

    private static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 48) {
            $rest = $minutes % 60;

            return $hours.' h'.($rest > 0 && $hours < 6 ? ' '.$rest.' min' : '');
        }

        return intdiv($hours, 24).' días';
    }
}
