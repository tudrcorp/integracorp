<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pide a n8n que el bot vuelva a hablar. El caso sale de la cola solo si n8n
 * confirma. Si n8n no responde, el analista sigue en el chat.
 */
final class CrmHandoffRelease
{
    /**
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    public function release(CrmHandoffEnvelope $envelope): array
    {
        if ($envelope->released_at !== null) {
            return ['ok' => true, 'already' => true];
        }

        if ($envelope->taken_at === null) {
            return ['ok' => false, 'reason' => 'not_taken'];
        }

        $url = $this->url();
        $key = (string) config('crm-inbox.takeover_key', '');

        if ($url === '' || $key === '') {
            return ['ok' => false, 'reason' => 'unconfigured'];
        }

        try {
            $response = Http::timeout(4)
                ->connectTimeout(3)
                ->acceptJson()
                ->withHeaders([
                    'X-Handoff-Key' => $key,
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'phone' => $envelope->phone,
                ]);
        } catch (Throwable $exception) {
            Log::warning('CRM handoff: n8n no confirmó la devolución', [
                'handoff_id' => $envelope->handoff_id,
                'error' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'reason' => 'unreachable'];
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            Log::warning('CRM handoff: n8n rechazó la devolución', [
                'handoff_id' => $envelope->handoff_id,
                'status' => $response->status(),
            ]);

            return ['ok' => false, 'reason' => 'rejected'];
        }

        $released = CrmHandoffEnvelope::query()
            ->whereKey($envelope->id)
            ->whereNull('released_at')
            ->update([
                'released_at' => now(),
            ]);

        return ['ok' => true, 'already' => $released === 0];
    }

    private function url(): string
    {
        $configured = trim((string) config('crm-inbox.release_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        $takeover = trim((string) config('crm-inbox.takeover_url', ''));

        if (str_ends_with($takeover, '/handoff/takeover')) {
            return substr($takeover, 0, -strlen('takeover')).'release';
        }

        return '';
    }
}
