<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pide a n8n que silencie al bot para ese teléfono. El caso queda tomado
 * solo si n8n confirma. Si n8n no responde, el bot sigue hablando.
 */
final class CrmHandoffTakeover
{
    /**
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    public function claim(CrmHandoffEnvelope $envelope, ?int $userId): array
    {
        if ($envelope->taken_at !== null) {
            return ['ok' => true, 'already' => true];
        }

        if ($userId !== null && self::atCapacity($userId)) {
            return ['ok' => false, 'reason' => 'cap'];
        }

        $url = (string) config('crm-inbox.takeover_url', '');
        $key = (string) config('crm-inbox.takeover_key', '');

        if ($url === '' || $key === '') {
            return ['ok' => false, 'reason' => 'unconfigured'];
        }

        try {
            $response = Http::timeout(4)
                ->acceptJson()
                ->withHeaders([
                    'X-Handoff-Key' => $key,
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'phone' => $envelope->phone,
                    'motivo' => 'Tomado desde IntegraCorp',
                ]);
        } catch (Throwable $exception) {
            Log::warning('CRM handoff: n8n no confirmó el takeover', [
                'handoff_id' => $envelope->handoff_id,
                'error' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'reason' => 'unreachable'];
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            Log::warning('CRM handoff: n8n rechazó el takeover', [
                'handoff_id' => $envelope->handoff_id,
                'status' => $response->status(),
            ]);

            return ['ok' => false, 'reason' => 'rejected'];
        }

        $claimed = CrmHandoffEnvelope::query()
            ->whereKey($envelope->id)
            ->whereNull('taken_at')
            ->update([
                'taken_at' => now(),
                'taken_by' => $userId,
            ]);

        if ($claimed > 0) {
            CrmInboxPulse::bump();
        }

        return ['ok' => true, 'already' => $claimed === 0];
    }

    public static function capacity(): int
    {
        return max(0, (int) config('crm-inbox.max_active_per_analyst', 8));
    }

    /**
     * Casos que la persona tiene en atención ahora mismo (tomados y sin devolver ni cerrar).
     */
    public static function activeCount(int $userId): int
    {
        return CrmHandoffEnvelope::query()
            ->where('taken_by', $userId)
            ->whereNotNull('taken_at')
            ->whereNull('released_at')
            ->whereNull('closed_at')
            ->count();
    }

    public static function atCapacity(int $userId): bool
    {
        $cap = self::capacity();

        return $cap > 0 && self::activeCount($userId) >= $cap;
    }
}
