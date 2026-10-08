<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Cola de lectura. La espera más larga queda arriba. Un caso cerrado o devuelto al bot no aparece.
 */
final class CrmInboxQueue
{
    public const PAGE_SIZE = 25;

    /**
     * @param  list<string>  $areas
     * @return array{
     *     count: int,
     *     oldest_waiting: ?string,
     *     rows: list<array<string, mixed>>
     * }
     */
    public static function snapshot(array $areas, bool $includeUnassigned, int $limit): array
    {
        $limit = max(1, min($limit, 200));
        $query = self::queryFor($areas, $includeUnassigned);
        $count = (clone $query)->count();
        $now = Carbon::now();
        $envelopes = (clone $query)
            ->orderByRaw('COALESCE(last_customer_at, accepted_at) IS NULL')
            ->orderByRaw('COALESCE(last_customer_at, accepted_at)')
            ->limit($limit)
            ->get();
        $names = self::assigneeNames($envelopes);
        $replies = self::lastReplies($envelopes);
        $quotes = self::lastQuotes($envelopes);
        $rows = $envelopes
            ->map(fn (CrmHandoffEnvelope $envelope): array => [
                ...CrmInboxCase::row(
                    $envelope,
                    $now,
                    $names[(int) $envelope->assigned_to] ?? null,
                ),
                'last_reply_at' => $replies[(string) $envelope->handoff_id]['at'] ?? null,
                'last_reply_text' => $replies[(string) $envelope->handoff_id]['text'] ?? null,
                'last_quote' => $quotes[(string) $envelope->handoff_id] ?? null,
            ])
            ->all();

        return [
            'count' => $count,
            'oldest_waiting' => $rows[0]['waiting'] ?? null,
            'rows' => $rows,
        ];
    }

    /**
     * Parte la cola en carriles según quién debe mover el caso. Cada carril conserva el orden por espera.
     *
     * Con $keepEmpty los tres carriles propios se devuelven aunque estén vacíos: ver «Al día» también informa.
     * «Con otro analista» solo aparece si tiene casos.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key: string, label: string, hint: string, empty: string, rows: list<array<string, mixed>>}>
     */
    public static function lanes(array $rows, ?int $viewerId, bool $keepEmpty = false): array
    {
        $lanes = [
            'reply' => ['key' => 'reply', 'label' => 'Te toca responder', 'hint' => 'El cliente escribió y espera tu respuesta, o te lo pasaron.', 'empty' => 'Al día', 'rows' => []],
            'open' => ['key' => 'open', 'label' => 'Sin tomar', 'hint' => 'SolIA sigue con ellos hasta que alguien los tome.', 'empty' => 'Nadie esperando', 'rows' => []],
            'waiting' => ['key' => 'waiting', 'label' => 'Esperando al cliente', 'hint' => 'Ya respondiste. Vuelven arriba cuando el cliente escriba.', 'empty' => 'Nada pendiente', 'rows' => []],
            'others' => ['key' => 'others', 'label' => 'Con otro analista', 'hint' => 'Los atiende otra persona del equipo.', 'empty' => '', 'rows' => []],
        ];

        foreach ($rows as $row) {
            $lanes[self::laneOf($row, $viewerId)]['rows'][] = $row;
        }

        return array_values(array_filter(
            $lanes,
            fn (array $lane): bool => $lane['rows'] !== [] || ($keepEmpty && $lane['key'] !== 'others'),
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function laneOf(array $row, ?int $viewerId): string
    {
        $taken = (bool) ($row['taken'] ?? false);

        if (! $taken) {
            return $viewerId !== null && ($row['assigned_to'] ?? null) === $viewerId ? 'reply' : 'open';
        }

        if ($viewerId === null || ($row['taken_by'] ?? null) !== $viewerId) {
            return 'others';
        }

        $customer = $row['waiting_since'] ?? null;
        $reply = $row['last_reply_at'] ?? null;

        if (! is_int($reply) || (is_int($customer) && $customer > $reply)) {
            return 'reply';
        }

        return 'waiting';
    }

    /**
     * @param  list<string>  $areas
     * @return array<string, mixed>|null
     */
    public static function find(string $handoffId, array $areas, bool $includeUnassigned): ?array
    {
        $envelope = self::queryFor($areas, $includeUnassigned)
            ->where('handoff_id', $handoffId)
            ->first();

        if ($envelope === null) {
            return null;
        }

        $names = self::assigneeNames(new Collection([$envelope]));

        return CrmInboxCase::detail(
            $envelope,
            Carbon::now(),
            $envelope->assigned_to !== null ? ($names[(int) $envelope->assigned_to] ?? null) : null,
            $envelope->taken_by !== null ? ($names[(int) $envelope->taken_by] ?? null) : null,
        );
    }

    /**
     * Huella barata de lo que la bandeja muestra. Si no cambia, la página no se vuelve a pintar.
     *
     * Además de la última escritura suma quién tiene cada caso y el largo del último mensaje: dos cambios
     * dentro del mismo segundo dejan igual MAX(updated_at), pero no esas sumas.
     *
     * @param  list<string>  $areas
     */
    public static function fingerprint(array $areas, bool $includeUnassigned, ?string $handoffId): string
    {
        $queue = self::queryFor($areas, $includeUnassigned)->toBase();
        $queue->columns = null;
        $queue->orders = null;
        $board = $queue->selectRaw(
            'COUNT(*) as total, MAX(id) as last_id, MAX(updated_at) as touched, MAX(last_customer_at) as last_customer, '
            .'SUM(CASE WHEN taken_at IS NULL THEN 0 ELSE 1 END) as held, SUM(COALESCE(taken_by, 0)) as takers, '
            .'SUM(COALESCE(assigned_to, 0)) as assignees, SUM(LENGTH(COALESCE(last_customer_text, \'\'))) as customer_chars'
        )->first();
        $thread = null;

        if ($handoffId !== null) {
            $messages = CrmHandoffMessage::query()
                ->where(function (Builder $query) use ($handoffId): void {
                    $query->where('handoff_id', $handoffId)
                        ->orWhere(function (Builder $query) use ($handoffId): void {
                            $query->whereNull('handoff_id')
                                ->whereIn('phone', CrmHandoffEnvelope::query()->select('phone')->where('handoff_id', $handoffId));
                        });
                })
                ->toBase();
            $thread = $messages->selectRaw(
                'COUNT(*) as total, MAX(id) as last_id, MAX(updated_at) as touched, '
                ."SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"
            )->first();
        }

        return md5((string) json_encode([$handoffId, (array) $board, $thread !== null ? (array) $thread : null]));
    }

    /**
     * @param  list<string>  $areas
     * @return Builder<CrmHandoffEnvelope>
     */
    public static function queryFor(array $areas, bool $includeUnassigned): Builder
    {
        return CrmHandoffEnvelope::query()
            ->select(['id', 'handoff_id', 'phone', 'area', 'motivo', 'payload', 'accepted_at', 'taken_at', 'taken_by', 'released_at', 'assigned_to', 'assigned_by', 'assigned_at', 'moved_by', 'moved_at', 'last_customer_at', 'last_customer_text', 'copilot_feedback'])
            ->whereNull('released_at')
            ->whereNull('closed_at')
            ->where(function (Builder $query) use ($areas, $includeUnassigned): void {
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
    }

    /**
     * Última respuesta enviada al cliente en cada caso visible: cuándo y qué dijo. Las notas internas
     * y los envíos fallidos no cuentan. Una consulta: el último id por caso y sus columnas.
     *
     * @param  Collection<int, CrmHandoffEnvelope>  $envelopes
     * @return array<string, array{at: int, text: string}>
     */
    private static function lastReplies(Collection $envelopes): array
    {
        $replies = [];

        foreach (self::latestOut($envelopes, fn (Builder $query) => $query->where('kind', '!=', 'note')) as $reply) {
            if ($reply->received_at !== null) {
                $replies[(string) $reply->handoff_id] = [
                    'at' => $reply->received_at->getTimestamp(),
                    'text' => self::preview((string) $reply->body),
                ];
            }
        }

        return $replies;
    }

    /**
     * Última propuesta enviada en cada caso visible, con el texto que recibió el cliente.
     *
     * @param  Collection<int, CrmHandoffEnvelope>  $envelopes
     * @return array<string, string>
     */
    private static function lastQuotes(Collection $envelopes): array
    {
        $quotes = [];

        foreach (self::latestOut($envelopes, fn (Builder $query) => $query->where('kind', 'quote')) as $quote) {
            $quotes[(string) $quote->handoff_id] = self::preview((string) $quote->body);
        }

        return $quotes;
    }

    /**
     * @param  Collection<int, CrmHandoffEnvelope>  $envelopes
     * @param  callable(Builder<CrmHandoffMessage>): mixed  $scope
     * @return \Illuminate\Database\Eloquent\Collection<int, CrmHandoffMessage>
     */
    private static function latestOut(Collection $envelopes, callable $scope): \Illuminate\Database\Eloquent\Collection
    {
        $ids = $envelopes->pluck('handoff_id')->map(fn (mixed $id): string => (string) $id)->all();

        if ($ids === []) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        $latest = CrmHandoffMessage::query()
            ->selectRaw('MAX(id)')
            ->whereIn('handoff_id', $ids)
            ->where('direction', 'out')
            ->where('status', '!=', 'failed')
            ->groupBy('handoff_id');
        $scope($latest);

        return CrmHandoffMessage::query()
            ->whereIn('id', $latest)
            ->get(['id', 'handoff_id', 'body', 'received_at']);
    }

    private static function preview(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_strlen($text) > 90 ? rtrim(mb_substr($text, 0, 87)).'…' : $text;
    }

    /**
     * @param  Collection<int, CrmHandoffEnvelope>  $envelopes
     * @return array<int, string>
     */
    private static function assigneeNames(Collection $envelopes): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $ids = $envelopes->pluck('assigned_to')
            ->merge($envelopes->pluck('taken_by'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach (User::query()->whereIn('id', $ids)->pluck('name', 'id') as $id => $name) {
            $given = CrmAnalystName::given((string) $name);
            $names[(int) $id] = $given !== '' ? $given : (string) $name;
        }

        return $names;
    }
}
