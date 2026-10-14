<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Jobs\NotifyCrmHandoffJob;
use App\Jobs\StoreCrmHandoffEnvelopeJob;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pasa un caso en atención a otra persona sin devolverlo al bot: el cliente sigue con un humano.
 * Un compañero puede relevarse a sí mismo si el caso quedó sin respuesta o quien lo atiende no está;
 * el supervisor puede pasarlo a cualquiera del área.
 */
final class CrmHandoffRelay
{
    /**
     * @return array{ok: true, name: string}|array{ok: false, reason: string}
     */
    public function relay(CrmHandoffEnvelope $envelope, int $toUserId, ?int $actorId, bool $supervisor, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        if ($envelope->taken_at === null || $envelope->released_at !== null || $envelope->closed_at !== null) {
            return ['ok' => false, 'reason' => 'not_taken'];
        }

        $fromId = $envelope->taken_by !== null ? (int) $envelope->taken_by : null;

        if ($fromId === $toUserId) {
            return ['ok' => false, 'reason' => 'same'];
        }

        if (! $supervisor && ($actorId === null || $actorId !== $toUserId || ! self::canRelieve($envelope, $now))) {
            return ['ok' => false, 'reason' => 'forbidden'];
        }

        $name = CrmInboxColleagues::nameInArea($envelope->area, $toUserId);

        if ($name === null) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if (! $supervisor && CrmHandoffTakeover::atCapacity($toUserId)) {
            return ['ok' => false, 'reason' => 'cap'];
        }

        $updated = CrmHandoffEnvelope::query()
            ->whereKey($envelope->id)
            ->whereNotNull('taken_at')
            ->whereNull('released_at')
            ->whereNull('closed_at')
            ->when($fromId === null, fn ($query) => $query->whereNull('taken_by'), fn ($query) => $query->where('taken_by', $fromId))
            ->update([
                'taken_by' => $toUserId,
                'stale_alerted_at' => null,
                'sla_alerted_at' => null,
                'follow_up_at' => null,
                'follow_up_note' => null,
                'follow_up_by' => null,
                'follow_up_notified_at' => null,
            ]);

        if ($updated === 0) {
            return ['ok' => false, 'reason' => 'changed'];
        }

        CrmInboxPulse::bump();
        $envelope->refresh();
        $fromName = $fromId !== null ? (CrmInboxColleagues::nameInArea($envelope->area, $fromId) ?? 'otro analista') : 'otro analista';

        CrmHandoffMessage::query()->create([
            'message_id' => (string) Str::uuid(),
            'handoff_id' => $envelope->handoff_id,
            'phone' => $envelope->phone,
            'body' => 'Relevo: '.$name.' sigue con el caso (lo atendía '.$fromName.').',
            'received_at' => now(),
            'direction' => 'out',
            'kind' => 'note',
            'status' => 'stored',
            'user_id' => $actorId,
        ]);

        $this->greet($envelope, $toUserId, $name);

        if ($actorId !== $toUserId) {
            $this->notify((string) $envelope->handoff_id, $toUserId);
        }

        return ['ok' => true, 'name' => $name];
    }

    /**
     * Un compañero puede tomar el relevo si el cliente espera más que el umbral de abandono
     * o si quien lo atiende está ausente o desconectado.
     */
    public static function canRelieve(CrmHandoffEnvelope $envelope, ?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        if ($envelope->taken_by === null) {
            return true;
        }

        $state = CrmInboxPresence::effective([(int) $envelope->taken_by], $now)[(int) $envelope->taken_by] ?? CrmInboxPresence::AVAILABLE;

        if (in_array($state, [CrmInboxPresence::AWAY, CrmInboxPresence::OFFLINE], true)) {
            return true;
        }

        return self::isStale($envelope, $now);
    }

    /**
     * El cliente escribió hace más del umbral de abandono y nadie le respondió después.
     */
    public static function isStale(CrmHandoffEnvelope $envelope, ?Carbon $now = null): bool
    {
        $now ??= Carbon::now();
        $since = $envelope->last_customer_at;

        if ($since === null || $since->greaterThan($now->copy()->subMinutes(CrmInboxWatch::abandonMinutes()))) {
            return false;
        }

        return ! CrmHandoffMessage::query()
            ->where('handoff_id', $envelope->handoff_id)
            ->where('direction', 'out')
            ->whereIn('kind', CrmHandoffReply::HUMAN)
            ->where('status', '!=', 'failed')
            ->where('received_at', '>=', $since)
            ->exists();
    }

    /**
     * El cliente sabe que cambió la persona. Si WhatsApp falla, queda en el hilo para reintentar.
     */
    private function greet(CrmHandoffEnvelope $envelope, int $toUserId, string $name): void
    {
        $given = CrmAnalystName::given($name);
        $payload = is_array($envelope->payload) ? $envelope->payload : [];
        $client = CrmAnalystName::given((string) ($payload['name'] ?? ''));
        $text = ($client !== '' ? $client.', ' : 'Hola, ').'desde ahora te atiende '.($given !== '' ? $given : 'otra persona del equipo').'. Sigo con tu caso.';

        $replyId = (string) Str::uuid();
        $reply = app(CrmHandoffReply::class);

        try {
            $staged = $reply->stage($envelope, $toUserId, $replyId, $text, $name);

            if ($staged['ok'] === true) {
                $reply->deliver($envelope, $replyId);
            }
        } catch (Throwable) {
        }
    }

    private function notify(string $handoffId, int $userId): void
    {
        try {
            $pending = NotifyCrmHandoffJob::dispatch($handoffId, 'relay', $userId, (string) now()->getTimestamp());
            $connection = StoreCrmHandoffEnvelopeJob::resolveConnection(
                config('crm-inbox.queue_connection'),
                app()->runningUnitTests() || app()->environment('local'),
            );

            if ($connection === 'sync') {
                $pending->afterResponse();
            }
        } catch (Throwable) {
        }
    }
}
