<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Jobs\NotifyCrmHandoffJob;
use App\Jobs\StoreCrmHandoffEnvelopeJob;
use App\Models\CrmHandoffEnvelope;

/**
 * Marca un caso sin tomar para un compañero del mismo departamento. No escribe al cliente.
 */
final class CrmHandoffAssign
{
    /**
     * @return array{ok: true, already: bool, name: string}|array{ok: false, reason: string}
     */
    public function assign(CrmHandoffEnvelope $envelope, int $assigneeId, ?int $actorId): array
    {
        if ($envelope->taken_at !== null) {
            return ['ok' => false, 'reason' => 'taken'];
        }

        if ($envelope->released_at !== null || $envelope->closed_at !== null) {
            return ['ok' => false, 'reason' => 'closed'];
        }

        if ($actorId !== null && $assigneeId === $actorId) {
            return ['ok' => false, 'reason' => 'self'];
        }

        $name = CrmInboxColleagues::nameInArea($envelope->area, $assigneeId);

        if ($name === null) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if ((int) $envelope->assigned_to === $assigneeId) {
            return ['ok' => true, 'already' => true, 'name' => $name];
        }

        $updated = CrmHandoffEnvelope::query()
            ->whereKey($envelope->id)
            ->whereNull('taken_at')
            ->whereNull('released_at')
            ->whereNull('closed_at')
            ->update([
                'assigned_to' => $assigneeId,
                'assigned_by' => $actorId,
                'assigned_at' => now(),
            ]);

        if ($updated === 0) {
            return ['ok' => false, 'reason' => 'taken'];
        }

        $this->notify((string) $envelope->handoff_id, 'assign', $assigneeId);

        return ['ok' => true, 'already' => false, 'name' => $name];
    }

    private function notify(string $handoffId, string $notice, int $userId): void
    {
        $pending = NotifyCrmHandoffJob::dispatch($handoffId, $notice, $userId);
        $connection = StoreCrmHandoffEnvelopeJob::resolveConnection(
            config('crm-inbox.queue_connection'),
            app()->runningUnitTests() || app()->environment('local'),
        );

        if ($connection === 'sync') {
            $pending->afterResponse();
        }
    }
}
