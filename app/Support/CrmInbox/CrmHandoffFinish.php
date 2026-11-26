<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Enums\CrmCaseOutcome;
use App\Models\CrmHandoffEnvelope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Finaliza una conversación atendida: es la forma normal de terminar la atención.
 *
 * Pasos, en orden, y ninguno se da por hecho sin confirmación:
 * 1. Mensaje de cierre al cliente (sale por n8n y queda en el hilo; un reintento no lo repite).
 * 2. n8n confirma `handoff/finish`: el bot vuelve a responder, pero el próximo mensaje del cliente
 *    abre una conversación nueva y se cancelan sus seguimientos automáticos.
 * 3. El caso sale de la bandeja con su resultado, quién lo finalizó y cuándo (estadísticas).
 *
 * Si algo falla, el caso sigue en atención y el analista puede reintentar. A diferencia de
 * {@see CrmHandoffRelease}, el bot no retoma la misma conversación.
 */
final class CrmHandoffFinish
{
    /**
     * @return array{ok: true, already: bool}|array{ok: false, reason: 'not_taken'|'ended'|'busy'|'farewell'|'unconfigured'|'unreachable'|'rejected'}
     */
    public function finish(CrmHandoffEnvelope $envelope, int $userId, CrmCaseOutcome $outcome, ?string $note = null): array
    {
        $envelope->refresh();

        if ($envelope->finished_at !== null) {
            return ['ok' => true, 'already' => true];
        }

        if ($envelope->released_at !== null || $envelope->closed_at !== null) {
            return ['ok' => false, 'reason' => 'ended'];
        }

        if ($envelope->taken_at === null) {
            return ['ok' => false, 'reason' => 'not_taken'];
        }

        $url = $this->url();
        $key = (string) config('crm-inbox.takeover_key', '');

        if ($url === '' || $key === '') {
            return ['ok' => false, 'reason' => 'unconfigured'];
        }

        $lock = null;

        try {
            $lock = Cache::lock('crm-finish-case:'.$envelope->handoff_id, 30);

            if (! $lock->get()) {
                return ['ok' => false, 'reason' => 'busy'];
            }
        } catch (Throwable) {
            $lock = null;
        }

        try {
            $farewell = app(CrmHandoffReply::class)->finishing($envelope, $this->clientName($envelope), $userId);

            if ($farewell['ok'] !== true) {
                return ['ok' => false, 'reason' => 'farewell'];
            }

            $confirmed = $this->confirmWithBot($envelope, $url, $key, $outcome);

            if ($confirmed !== null) {
                return ['ok' => false, 'reason' => $confirmed];
            }

            $finished = CrmHandoffEnvelope::query()
                ->whereKey($envelope->id)
                ->whereNull('finished_at')
                ->whereNull('closed_at')
                ->whereNull('released_at')
                ->update([
                    'finished_at' => now(),
                    'finished_by' => $userId,
                    'closed_at' => now(),
                    'closed_by' => $userId,
                    ...CrmHandoffOutcome::columns($outcome, $note, $userId),
                ]);

            if ($finished > 0) {
                CrmInboxPulse::bump();
            }

            return ['ok' => true, 'already' => $finished === 0];
        } finally {
            $lock?->release();
        }
    }

    /**
     * @return 'unreachable'|'rejected'|null null si n8n confirmó
     */
    private function confirmWithBot(CrmHandoffEnvelope $envelope, string $url, string $key, CrmCaseOutcome $outcome): ?string
    {
        try {
            $response = Http::timeout(6)
                ->connectTimeout(3)
                ->acceptJson()
                ->withHeaders(['X-Handoff-Key' => $key])
                ->post($url, [
                    'phone' => $envelope->phone,
                    'handoff_id' => (string) $envelope->handoff_id,
                    'outcome' => $outcome->value,
                ]);
        } catch (Throwable $exception) {
            Log::warning('CRM handoff: n8n no confirmó el cierre de la conversación', [
                'handoff_id' => $envelope->handoff_id,
                'error' => $exception->getMessage(),
            ]);

            return 'unreachable';
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            Log::warning('CRM handoff: n8n rechazó el cierre de la conversación', [
                'handoff_id' => $envelope->handoff_id,
                'status' => $response->status(),
            ]);

            return 'rejected';
        }

        return null;
    }

    private function clientName(CrmHandoffEnvelope $envelope): string
    {
        $payload = is_array($envelope->payload) ? $envelope->payload : [];
        $lead = is_array($payload['lead'] ?? null) ? $payload['lead'] : [];

        foreach ([$payload['name'] ?? null, $lead['nombre'] ?? null] as $name) {
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return '';
    }

    private function url(): string
    {
        $configured = trim((string) config('crm-inbox.finish_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        $takeover = trim((string) config('crm-inbox.takeover_url', ''));

        if (str_ends_with($takeover, '/handoff/takeover')) {
            return substr($takeover, 0, -strlen('takeover')).'finish';
        }

        return '';
    }
}
