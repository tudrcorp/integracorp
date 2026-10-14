<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Enums\CrmCaseOutcome;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Números del tablero del supervisor. Agregados con columnas indexadas, cacheados 30 segundos en
 * Redis: diez supervisores mirando el tablero cuestan lo mismo que uno.
 */
final class CrmInboxDashboard
{
    /**
     * @var array<string, string>
     */
    public const RANGES = [
        'hoy' => 'Hoy',
        '7d' => 'Últimos 7 días',
        '30d' => 'Últimos 30 días',
    ];

    public const CACHE_SECONDS = 30;

    /**
     * Tope de filas para la mediana de primera respuesta: suficiente para que el número sea fiel sin traer todo.
     */
    private const SAMPLE = 3000;

    /**
     * @param  list<string>  $areas
     * @return array<string, mixed>
     */
    public static function build(string $panel, array $areas, bool $includeUnassigned, string $range, ?Carbon $now = null): array
    {
        $range = isset(self::RANGES[$range]) ? $range : 'hoy';
        $key = 'crm-inbox:dashboard:'.$panel.':'.$range;

        $cached = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get($key));

        if (is_array($cached) && $now === null) {
            return $cached;
        }

        $data = self::compute($panel, $areas, $includeUnassigned, $range, $now ?? Carbon::now());

        CrmInboxCache::attempt(static fn (Repository $store): bool => $store->put($key, $data, self::CACHE_SECONDS));

        return $data;
    }

    /**
     * @param  list<string>  $areas
     * @return array<string, mixed>
     */
    private static function compute(string $panel, array $areas, bool $includeUnassigned, string $range, Carbon $now): array
    {
        $from = match ($range) {
            '7d' => $now->copy()->timezone('America/Caracas')->subDays(6)->startOfDay(),
            '30d' => $now->copy()->timezone('America/Caracas')->subDays(29)->startOfDay(),
            default => $now->copy()->timezone('America/Caracas')->startOfDay(),
        };
        $from = $from->timezone(config('app.timezone', 'UTC'));
        $late = $now->copy()->subMinutes(CrmInboxWatch::lateMinutes());

        $scope = fn (): Builder => CrmHandoffEnvelope::query()->where(function (Builder $query) use ($areas, $includeUnassigned): void {
            if ($areas !== []) {
                $query->whereIn('area', $areas);
            }

            if ($includeUnassigned) {
                $query->orWhereNull('area');
            }

            if ($areas === [] && ! $includeUnassigned) {
                $query->whereRaw('1 = 0');
            }
        });
        $open = fn (): Builder => $scope()->whereNull('closed_at')->whereNull('released_at');

        $incoming = $scope()->where('accepted_at', '>=', $from)->count();
        $attended = $scope()->where('taken_at', '>=', $from)->count();
        $waitingNow = $open()->whereNull('taken_at')->count();
        $lateNow = $open()->whereNull('taken_at')->whereRaw('COALESCE(last_customer_at, accepted_at) <= ?', [$late])->count();
        $unansweredNow = self::unanswered($open(), $late)->count();
        $firstReply = self::median($scope()->where('accepted_at', '>=', $from)->whereNotNull('first_reply_at')->limit(self::SAMPLE)->get(['accepted_at', 'first_reply_at'])
            ->map(fn (CrmHandoffEnvelope $envelope): int => max(0, $envelope->first_reply_at->getTimestamp() - $envelope->accepted_at->getTimestamp()))
            ->all());

        $outcomes = $scope()->where('outcome_at', '>=', $from)->whereNotNull('outcome')
            ->selectRaw('outcome, COUNT(*) as total')->groupBy('outcome')->pluck('total', 'outcome')
            ->map(fn (mixed $total): int => (int) $total)->all();
        $closed = array_sum($outcomes);
        $won = (int) ($outcomes[CrmCaseOutcome::Sold->value] ?? 0);
        $quotes = CrmHandoffMessage::query()
            ->where('kind', 'quote')->where('status', 'sent')->where('received_at', '>=', $from)
            ->whereIn('handoff_id', $scope()->select('handoff_id'))
            ->count();

        return [
            'range' => $range,
            'generated_at' => $now->getTimestamp(),
            'kpis' => [
                'incoming' => $incoming,
                'attended' => $attended,
                'waiting_now' => $waitingNow,
                'late_now' => $lateNow,
                'unanswered_now' => $unansweredNow,
                'first_reply_median' => $firstReply,
                'closed' => $closed,
                'won' => $won,
                'conversion' => $closed > 0 ? round($won / $closed * 100) : null,
                'quotes' => $quotes,
            ],
            'outcomes' => self::outcomeRows($outcomes),
            'analysts' => self::analysts($panel, $areas, $includeUnassigned, $scope, $open, $from, $late, $now),
            'risk' => self::risk($open, $late, $now),
        ];
    }

    /**
     * Casos en atención donde el cliente escribió antes del umbral y no hubo respuesta después.
     *
     * @param  Builder<CrmHandoffEnvelope>  $query
     * @return Builder<CrmHandoffEnvelope>
     */
    private static function unanswered(Builder $query, Carbon $late): Builder
    {
        return $query->whereNotNull('taken_at')
            ->whereNotNull('last_customer_at')
            ->where('last_customer_at', '<=', $late)
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('crm_handoff_messages')
                    ->whereColumn('crm_handoff_messages.handoff_id', 'crm_handoff_envelopes.handoff_id')
                    ->where('crm_handoff_messages.direction', 'out')
                    ->whereIn('crm_handoff_messages.kind', CrmHandoffReply::HUMAN)
                    ->where('crm_handoff_messages.status', '!=', 'failed')
                    ->whereColumn('crm_handoff_messages.received_at', '>=', 'crm_handoff_envelopes.last_customer_at');
            });
    }

    /**
     * @param  list<string>  $areas
     * @param  callable(): Builder<CrmHandoffEnvelope>  $scope
     * @param  callable(): Builder<CrmHandoffEnvelope>  $open
     * @return list<array<string, mixed>>
     */
    private static function analysts(string $panel, array $areas, bool $includeUnassigned, callable $scope, callable $open, Carbon $from, Carbon $late, Carbon $now): array
    {
        $colleagues = CrmInboxColleagues::forArea($areas[0] ?? null);

        if ($colleagues === []) {
            return [];
        }

        $ids = array_column($colleagues, 'id');
        $states = CrmInboxPresence::effective($ids, $now);
        $active = $open()->whereNotNull('taken_at')->whereIn('taken_by', $ids)
            ->selectRaw('taken_by, COUNT(*) as total')->groupBy('taken_by')->pluck('total', 'taken_by')->all();
        $waiting = self::unanswered($open(), $late)->whereIn('taken_by', $ids)
            ->selectRaw('taken_by, COUNT(*) as total')->groupBy('taken_by')->pluck('total', 'taken_by')->all();
        $attended = $scope()->where('taken_at', '>=', $from)->whereIn('taken_by', $ids)
            ->selectRaw('taken_by, COUNT(*) as total')->groupBy('taken_by')->pluck('total', 'taken_by')->all();
        $closed = $scope()->where('outcome_at', '>=', $from)->whereIn('outcome_by', $ids)
            ->selectRaw('outcome_by, COUNT(*) as total, SUM(CASE WHEN outcome = ? THEN 1 ELSE 0 END) as won', [CrmCaseOutcome::Sold->value])
            ->groupBy('outcome_by')->get()->keyBy('outcome_by');
        $replies = [];

        foreach ($scope()->where('taken_at', '>=', $from)->whereIn('taken_by', $ids)->whereNotNull('first_reply_at')->limit(self::SAMPLE)->get(['taken_by', 'taken_at', 'first_reply_at']) as $envelope) {
            $replies[(int) $envelope->taken_by][] = max(0, $envelope->first_reply_at->getTimestamp() - $envelope->taken_at->getTimestamp());
        }

        $rows = array_map(fn (array $colleague): array => [
            'id' => $colleague['id'],
            'name' => $colleague['name'],
            'state' => $states[$colleague['id']] ?? CrmInboxPresence::OFFLINE,
            'state_label' => CrmInboxPresence::label($states[$colleague['id']] ?? CrmInboxPresence::OFFLINE),
            'active' => (int) ($active[$colleague['id']] ?? 0),
            'waiting' => (int) ($waiting[$colleague['id']] ?? 0),
            'attended' => (int) ($attended[$colleague['id']] ?? 0),
            'closed' => (int) ($closed[$colleague['id']]->total ?? 0),
            'won' => (int) ($closed[$colleague['id']]->won ?? 0),
            'reply_median' => self::median($replies[$colleague['id']] ?? []),
        ], $colleagues);

        usort($rows, fn (array $a, array $b): int => [$b['waiting'], $b['active'], $b['attended']] <=> [$a['waiting'], $a['active'], $a['attended']]);

        return $rows;
    }

    /**
     * Lo que el supervisor debería mirar ya: sin tomar en rojo y en atención sin respuesta.
     *
     * @param  callable(): Builder<CrmHandoffEnvelope>  $open
     * @return list<array{handoff_id: string, name: string, area: string, since: ?int, kind: string, taker_id: ?int}>
     */
    private static function risk(callable $open, Carbon $late, Carbon $now): array
    {
        $untaken = $open()->whereNull('taken_at')->whereRaw('COALESCE(last_customer_at, accepted_at) <= ?', [$late])
            ->orderByRaw('COALESCE(last_customer_at, accepted_at)')->limit(15)
            ->get(['handoff_id', 'payload', 'area', 'accepted_at', 'last_customer_at', 'taken_by']);
        $unanswered = self::unanswered($open(), $late)->orderBy('last_customer_at')->limit(15)
            ->get(['handoff_id', 'payload', 'area', 'accepted_at', 'last_customer_at', 'taken_by']);

        $rows = [];

        foreach ([['untaken', $untaken], ['unanswered', $unanswered]] as [$kind, $list]) {
            foreach ($list as $envelope) {
                $payload = is_array($envelope->payload) ? $envelope->payload : [];
                $name = trim((string) ($payload['name'] ?? ''));
                $since = $envelope->last_customer_at ?? $envelope->accepted_at;

                $rows[] = [
                    'handoff_id' => (string) $envelope->handoff_id,
                    'name' => $name !== '' ? $name : 'Sin nombre',
                    'area' => CrmInboxAreas::label($envelope->area),
                    'since' => $since?->getTimestamp(),
                    'kind' => $kind,
                    'taker_id' => $envelope->taken_by !== null ? (int) $envelope->taken_by : null,
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => ($a['since'] ?? PHP_INT_MAX) <=> ($b['since'] ?? PHP_INT_MAX));

        return array_slice($rows, 0, 20);
    }

    /**
     * @param  array<string, int>  $outcomes
     * @return list<array{key: string, label: string, total: int}>
     */
    private static function outcomeRows(array $outcomes): array
    {
        arsort($outcomes);
        $rows = [];

        foreach ($outcomes as $key => $total) {
            $rows[] = ['key' => (string) $key, 'label' => CrmCaseOutcome::labelFor((string) $key) ?? (string) $key, 'total' => $total];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $values
     */
    private static function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : intdiv($values[$middle - 1] + $values[$middle], 2);
    }

    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).' min';
        }

        return intdiv($seconds, 3600).' h '.intdiv($seconds % 3600, 60).' min';
    }
}
