<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Tendencias de la bandeja para decidir: casos por día, cumplimiento del tiempo de toma,
 * abandonos, espera por área y por hora del día (dónde faltan analistas) y derivaciones
 * repetidas que se unieron al caso abierto.
 *
 * Se calcula sobre los casos que entraron en el periodo, con un tope de filas; se cachea junto
 * con el tablero.
 */
final class CrmInboxTrends
{
    private const SAMPLE = 5000;

    private const TZ = 'America/Caracas';

    /**
     * @param  callable(): Builder<CrmHandoffEnvelope>  $scope
     * @return array<string, mixed>
     */
    public static function compute(callable $scope, Carbon $from, Carbon $now): array
    {
        $lateSeconds = CrmInboxWatch::lateMinutes() * 60;
        $abandonSeconds = CrmInboxWatch::abandonMinutes() * 60;
        $nowTs = $now->getTimestamp();

        $rows = $scope()->where('accepted_at', '>=', $from)
            ->orderBy('accepted_at')
            ->limit(self::SAMPLE)
            ->get(['handoff_id', 'area', 'accepted_at', 'taken_at', 'released_at', 'closed_at']);

        $finishedByDay = $scope()->where('finished_at', '>=', $from)->limit(self::SAMPLE)->pluck('finished_at')
            ->map(fn (mixed $at): string => Carbon::parse($at)->timezone(self::TZ)->format('Y-m-d'))
            ->countBy()->all();

        $days = [];

        for ($day = $from->copy()->timezone(self::TZ)->startOfDay(); $day->lte($now->copy()->timezone(self::TZ)); $day->addDay()) {
            $days[$day->format('Y-m-d')] = ['day' => $day->format('Y-m-d'), 'incoming' => 0, 'taken' => 0, 'finished' => (int) ($finishedByDay[$day->format('Y-m-d')] ?? 0)];
        }

        $hours = array_fill(0, 24, ['incoming' => 0, 'waits' => []]);
        $areas = [];
        $waits = [];
        $withinSla = 0;
        $measured = 0;
        $abandoned = 0;

        foreach ($rows as $envelope) {
            $accepted = $envelope->accepted_at;

            if ($accepted === null) {
                continue;
            }

            $local = $accepted->copy()->timezone(self::TZ);
            $dayKey = $local->format('Y-m-d');
            $hour = (int) $local->format('G');
            $area = (string) ($envelope->area ?? '');
            $areas[$area] ??= ['incoming' => 0, 'waits' => []];
            $areas[$area]['incoming']++;
            $hours[$hour]['incoming']++;

            if (isset($days[$dayKey])) {
                $days[$dayKey]['incoming']++;
            }

            if ($envelope->taken_at !== null) {
                $wait = max(0, $envelope->taken_at->getTimestamp() - $accepted->getTimestamp());
                $waits[] = $wait;
                $areas[$area]['waits'][] = $wait;
                $hours[$hour]['waits'][] = $wait;
                $measured++;
                $withinSla += $wait <= $lateSeconds ? 1 : 0;
                $abandoned += $wait > $abandonSeconds ? 1 : 0;

                $takenDay = $envelope->taken_at->copy()->timezone(self::TZ)->format('Y-m-d');

                if (isset($days[$takenDay])) {
                    $days[$takenDay]['taken']++;
                }

                continue;
            }

            $ended = $envelope->released_at ?? $envelope->closed_at;
            $waited = ($ended?->getTimestamp() ?? $nowTs) - $accepted->getTimestamp();

            if ($waited > $lateSeconds) {
                $measured++;
            }

            if ($ended !== null || $waited > $abandonSeconds) {
                $abandoned++;
            }
        }

        $areaRows = [];

        foreach ($areas as $key => $area) {
            $areaRows[] = [
                'key' => $key,
                'label' => $key !== '' ? CrmInboxAreas::label($key) : 'Sin área',
                'incoming' => $area['incoming'],
                'wait_median' => CrmInboxDashboard::median($area['waits']),
            ];
        }

        usort($areaRows, fn (array $a, array $b): int => $b['incoming'] <=> $a['incoming']);

        $hourRows = [];

        foreach ($hours as $hour => $data) {
            $hourRows[] = ['hour' => $hour, 'incoming' => $data['incoming'], 'wait_median' => CrmInboxDashboard::median($data['waits'])];
        }

        $merged = CrmHandoffMessage::query()
            ->where('message_id', 'like', 'merge:%')
            ->where('received_at', '>=', $from)
            ->whereIn('handoff_id', $scope()->select('handoff_id'))
            ->count();

        return [
            'days' => array_values($days),
            'hours' => $hourRows,
            'areas' => $areaRows,
            'wait_median' => CrmInboxDashboard::median($waits),
            'sla' => $measured > 0 ? (int) round($withinSla / $measured * 100) : null,
            'sla_minutes' => intdiv($lateSeconds, 60),
            'abandoned' => $abandoned,
            'abandon_minutes' => intdiv($abandonSeconds, 60),
            'merged' => $merged,
            'sampled' => $rows->count() >= self::SAMPLE,
            'peak_wait_hours' => self::peakWaitHours($hourRows),
        ];
    }

    /**
     * Las horas con más espera para tomar un caso (con al menos 3 casos): dónde reforzar turnos.
     *
     * @param  list<array{hour: int, incoming: int, wait_median: ?int}>  $hours
     * @return list<array{hour: int, incoming: int, wait_median: int}>
     */
    private static function peakWaitHours(array $hours): array
    {
        $candidates = array_values(array_filter($hours, fn (array $row): bool => $row['incoming'] >= 3 && $row['wait_median'] !== null));
        usort($candidates, fn (array $a, array $b): int => $b['wait_median'] <=> $a['wait_median']);

        return array_slice($candidates, 0, 3);
    }
}
