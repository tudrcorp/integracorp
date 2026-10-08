<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;

/**
 * Quita de la bandeja un caso que nadie tomó. No escribe al cliente ni llama a n8n.
 */
final class CrmHandoffClose
{
    /**
     * @return array{ok: true, already: bool}|array{ok: false, reason: string}
     */
    public function close(CrmHandoffEnvelope $envelope, ?int $userId): array
    {
        if ($envelope->closed_at !== null || $envelope->released_at !== null) {
            return ['ok' => true, 'already' => true];
        }

        if ($envelope->taken_at !== null) {
            return ['ok' => false, 'reason' => 'taken'];
        }

        $closed = CrmHandoffEnvelope::query()
            ->whereKey($envelope->id)
            ->whereNull('closed_at')
            ->whereNull('taken_at')
            ->whereNull('released_at')
            ->update([
                'closed_at' => now(),
                'closed_by' => $userId,
            ]);

        return ['ok' => true, 'already' => $closed === 0];
    }
}
