<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Jobs\NotifyCrmHandoffJob;
use App\Jobs\StoreCrmHandoffEnvelopeJob;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lo que la bandeja vigila sola cada minuto, aunque nadie la tenga abierta. Cada paso es acotado
 * (lotes pequeños, columnas con índice) y marca lo que ya avisó para no repetirlo.
 */
final class CrmInboxWatch
{
    /**
     * Reintenta lo que no salió por WhatsApp hace poco. Con el envío caído solo prueba uno:
     * si sale, el siguiente minuto sigue con el resto.
     */
    public static function retryFailed(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $window = max(1, (int) config('crm-inbox.retry.window_minutes', 15));
        $maxAttempts = max(1, (int) config('crm-inbox.retry.max_attempts', 3));
        $batch = CrmInboxHealth::status()['down'] ? 1 : max(1, (int) config('crm-inbox.retry.batch', 20));

        $messages = CrmHandoffMessage::query()
            ->where('direction', 'out')
            ->whereIn('kind', CrmHandoffReply::SENDABLE)
            ->where('received_at', '>=', $now->copy()->subMinutes($window))
            ->where('attempts', '<', $maxAttempts)
            ->where(function (Builder $query) use ($now): void {
                $query->where('status', 'failed')
                    ->orWhere(fn (Builder $query) => $query->where('status', 'pending')->where('updated_at', '<=', $now->copy()->subMinutes(2)));
            })
            ->whereNotNull('handoff_id')
            ->orderBy('id')
            ->limit($batch)
            ->get();

        if ($messages->isEmpty()) {
            return 0;
        }

        $envelopes = CrmHandoffEnvelope::query()
            ->whereIn('handoff_id', $messages->pluck('handoff_id')->unique()->all())
            ->whereNotNull('taken_at')
            ->whereNull('released_at')
            ->whereNull('closed_at')
            ->get()
            ->keyBy(fn (CrmHandoffEnvelope $envelope): string => (string) $envelope->handoff_id);

        $reply = app(CrmHandoffReply::class);
        $sent = 0;

        foreach ($messages as $message) {
            $envelope = $envelopes->get((string) $message->handoff_id);

            if ($envelope === null) {
                continue;
            }

            try {
                $result = $reply->deliver($envelope, (string) $message->message_id);
            } catch (Throwable) {
                continue;
            }

            if ($result['ok'] === true) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Casos sin tomar que pasaron el umbral rojo: aviso a toda el área, una vez por cada mensaje nuevo del cliente.
     */
    public static function alertLate(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $limit = $now->copy()->subMinutes(self::lateMinutes());
        $count = 0;

        $cases = self::open()
            ->whereNull('taken_at')
            ->whereRaw('COALESCE(last_customer_at, accepted_at) <= ?', [$limit])
            ->where(fn (Builder $query) => $query->whereNull('sla_alerted_at')->orWhereRaw('sla_alerted_at < COALESCE(last_customer_at, accepted_at)'))
            ->limit(50)
            ->get(['id', 'handoff_id', 'last_customer_at', 'accepted_at', 'sla_alerted_at']);

        foreach ($cases as $envelope) {
            if (self::mark($envelope, 'sla_alerted_at', $now)) {
                self::notify((string) $envelope->handoff_id, 'late', null, self::stamp($envelope));
                $count++;
            }
        }

        return $count;
    }

    /**
     * Casos en atención donde el cliente escribió y nadie le contesta. Pasado el umbral rojo se avisa
     * a quien lo atiende; pasado el de abandono, a los supervisores (y un compañero puede relevarlo).
     *
     * @return array{late: int, stale: int}
     */
    public static function alertUnanswered(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $late = 0;
        $stale = 0;
        $lateLimit = $now->copy()->subMinutes(self::lateMinutes());
        $staleLimit = $now->copy()->subMinutes(self::abandonMinutes());

        $cases = self::open()
            ->whereNotNull('taken_at')
            ->whereNotNull('last_customer_at')
            ->where('last_customer_at', '<=', $lateLimit)
            ->where(fn (Builder $query) => $query
                ->whereNull('sla_alerted_at')->orWhereColumn('sla_alerted_at', '<', 'last_customer_at')
                ->orWhereNull('stale_alerted_at')->orWhereColumn('stale_alerted_at', '<', 'last_customer_at'))
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('crm_handoff_messages')
                    ->whereColumn('crm_handoff_messages.handoff_id', 'crm_handoff_envelopes.handoff_id')
                    ->where('crm_handoff_messages.direction', 'out')
                    ->whereIn('crm_handoff_messages.kind', CrmHandoffReply::HUMAN)
                    ->where('crm_handoff_messages.status', '!=', 'failed')
                    ->whereColumn('crm_handoff_messages.received_at', '>=', 'crm_handoff_envelopes.last_customer_at');
            })
            ->limit(50)
            ->get(['id', 'handoff_id', 'taken_by', 'last_customer_at', 'accepted_at', 'sla_alerted_at', 'stale_alerted_at']);

        foreach ($cases as $envelope) {
            $since = $envelope->last_customer_at;

            if (($envelope->sla_alerted_at === null || $envelope->sla_alerted_at->lessThan($since))
                && self::mark($envelope, 'sla_alerted_at', $now)
                && $envelope->taken_by !== null) {
                self::notify((string) $envelope->handoff_id, 'late_mine', (int) $envelope->taken_by, self::stamp($envelope));
                $late++;
            }

            if ($since->lessThanOrEqualTo($staleLimit)
                && ($envelope->stale_alerted_at === null || $envelope->stale_alerted_at->lessThan($since))
                && self::mark($envelope, 'stale_alerted_at', $now)) {
                self::notify((string) $envelope->handoff_id, 'stale', null, self::stamp($envelope));
                $stale++;
            }
        }

        return ['late' => $late, 'stale' => $stale];
    }

    /**
     * Recordatorios que vencieron: aviso a quien lo programó. El caso sube a «Te toca responder».
     */
    public static function dueFollowUps(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $count = 0;

        $cases = self::open()
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', $now)
            ->where(fn (Builder $query) => $query->whereNull('follow_up_notified_at')->orWhereColumn('follow_up_notified_at', '<', 'follow_up_at'))
            ->limit(50)
            ->get(['id', 'handoff_id', 'follow_up_at', 'follow_up_by', 'taken_by', 'follow_up_notified_at']);

        foreach ($cases as $envelope) {
            $userId = $envelope->follow_up_by ?? $envelope->taken_by;

            if (self::mark($envelope, 'follow_up_notified_at', $now) && $userId !== null) {
                self::notify((string) $envelope->handoff_id, 'follow_up', (int) $userId, (string) $envelope->follow_up_at->getTimestamp());
                $count++;
            }
        }

        return $count;
    }

    /**
     * Borra los PDF de propuestas que nunca salieron. Los fallidos se guardan unos días por si el analista reintenta.
     */
    public static function pruneDrafts(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $disk = Storage::disk('local');
        $unsentCutoff = $now->copy()->subHours(max(1, (int) config('crm-inbox.draft_ttl_hours', 24)))->getTimestamp();
        $failedCutoff = $now->copy()->subDays(7)->getTimestamp();
        $deleted = 0;

        foreach (array_chunk($disk->files('crm-quotes'), 200) as $chunk) {
            $ids = [];

            foreach ($chunk as $path) {
                if (preg_match('/^crm-quotes\/([0-9a-f-]{36})\.pdf$/', $path, $match) === 1) {
                    $ids[$match[1]] = $path;
                }
            }

            $statuses = CrmHandoffMessage::query()
                ->whereIn('message_id', array_keys($ids))
                ->pluck('status', 'message_id');

            foreach ($ids as $id => $path) {
                $modified = $disk->lastModified($path);
                $status = $statuses[$id] ?? null;
                $expired = $status === null || $status === 'sent'
                    ? $modified < $unsentCutoff
                    : $modified < $failedCutoff;

                if ($expired) {
                    $disk->delete($path);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public static function lateMinutes(): int
    {
        $warn = max(1, (int) config('crm-inbox.sla.warn_minutes', 5));

        return max($warn + 1, (int) config('crm-inbox.sla.late_minutes', 15));
    }

    public static function abandonMinutes(): int
    {
        return max(self::lateMinutes() + 1, (int) config('crm-inbox.sla.abandon_minutes', 30));
    }

    /**
     * @return Builder<CrmHandoffEnvelope>
     */
    private static function open(): Builder
    {
        return CrmHandoffEnvelope::query()
            ->whereNull('closed_at')
            ->whereNull('released_at');
    }

    /**
     * Marca el aviso con una escritura condicional: si dos procesos corren a la vez, solo uno avisa.
     */
    private static function mark(CrmHandoffEnvelope $envelope, string $column, Carbon $now): bool
    {
        $original = $envelope->getRawOriginal($column);

        $updated = CrmHandoffEnvelope::query()
            ->whereKey($envelope->id)
            ->when(
                $original === null,
                fn (Builder $query) => $query->whereNull($column),
                fn (Builder $query) => $query->where($column, $original),
            )
            ->update([$column => $now]);

        if ($updated > 0) {
            CrmInboxPulse::bump();
        }

        return $updated > 0;
    }

    private static function stamp(CrmHandoffEnvelope $envelope): string
    {
        return (string) ($envelope->last_customer_at ?? $envelope->accepted_at)?->getTimestamp();
    }

    /**
     * Un aviso que no se pudo encolar no detiene la vigilancia: queda en el log y la marca ya puesta
     * evita repetirlo en bucle. El caso sigue rojo en la bandeja de quien la tenga abierta.
     */
    private static function notify(string $handoffId, string $notice, ?int $userId, string $stamp): void
    {
        try {
            $pending = NotifyCrmHandoffJob::dispatch($handoffId, $notice, $userId, $stamp);
            $connection = StoreCrmHandoffEnvelopeJob::resolveConnection(
                config('crm-inbox.queue_connection'),
                app()->runningUnitTests() || app()->environment('local'),
            );

            if ($connection === 'sync') {
                $pending->afterResponse();
            }
        } catch (Throwable $exception) {
            Log::warning('CRM vigilancia: no se pudo encolar el aviso', [
                'handoff_id' => $handoffId,
                'notice' => $notice,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
