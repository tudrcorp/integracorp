<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Jobs\NotifyCrmHandoffJob;
use App\Jobs\StoreCrmHandoffEnvelopeJob;
use App\Models\CrmHandoffEnvelope;

/**
 * Cambia el área de un caso que nadie tomó. Sale de esta bandeja y entra en la otra. No escribe al cliente.
 */
final class CrmHandoffMove
{
    /**
     * @return array{ok: true, already: bool, label: string}|array{ok: false, reason: string}
     */
    public function move(CrmHandoffEnvelope $envelope, string $area, ?int $actorId): array
    {
        if ($envelope->taken_at !== null) {
            return ['ok' => false, 'reason' => 'taken'];
        }

        if ($envelope->released_at !== null || $envelope->closed_at !== null) {
            return ['ok' => false, 'reason' => 'closed'];
        }

        $label = CrmInboxAreas::destinationLabel($area, $envelope->area);

        if ($label === null) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $updated = CrmHandoffEnvelope::query()
            ->whereKey($envelope->id)
            ->whereNull('taken_at')
            ->whereNull('released_at')
            ->whereNull('closed_at')
            ->update([
                'area' => $area,
                'moved_by' => $actorId,
                'moved_at' => now(),
                'assigned_to' => null,
                'assigned_by' => null,
                'assigned_at' => null,
            ]);

        if ($updated === 0) {
            return ['ok' => false, 'reason' => 'taken'];
        }

        $pending = NotifyCrmHandoffJob::dispatch((string) $envelope->handoff_id, 'move');
        $connection = StoreCrmHandoffEnvelopeJob::resolveConnection(
            config('crm-inbox.queue_connection'),
            app()->runningUnitTests() || app()->environment('local'),
        );

        if ($connection === 'sync') {
            $pending->afterResponse();
        }

        return ['ok' => true, 'already' => false, 'label' => $label];
    }
}
