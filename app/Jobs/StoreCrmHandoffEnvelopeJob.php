<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CrmHandoffEnvelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoreCrmHandoffEnvelopeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 15;

    /**
     * @param  array<string, mixed>  $envelope
     */
    public function __construct(public array $envelope)
    {
        $this->onConnection(self::resolveConnection(
            config('crm-inbox.queue_connection'),
            app()->runningUnitTests() || app()->environment('local'),
        ));
        $this->onQueue((string) config('crm-inbox.queue', 'inbox'));
    }

    /**
     * La cola real es Redis. `sync` solo en local o en pruebas: el sobre se guarda
     * en la misma petición, en su tabla, sin pasar por `jobs`.
     */
    public static function resolveConnection(mixed $configured, bool $allowSync): string
    {
        $configured = is_string($configured) && $configured !== '' ? $configured : 'redis';

        if ($configured === 'redis') {
            return 'redis';
        }

        if ($allowSync && $configured === 'sync') {
            return 'sync';
        }

        return 'redis';
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 20, 60, 120, 300];
    }

    public function handle(): void
    {
        $handoffId = (string) ($this->envelope['handoff_id'] ?? '');

        if ($handoffId === '') {
            return;
        }

        CrmHandoffEnvelope::query()->firstOrCreate(
            ['handoff_id' => $handoffId],
            [
                'phone' => (string) ($this->envelope['phone'] ?? ''),
                'area' => $this->envelope['area'] ?? null,
                'motivo' => $this->envelope['motivo'] ?? null,
                'payload' => $this->envelope,
                'accepted_at' => now(),
            ],
        );

        $pending = NotifyCrmHandoffJob::dispatch($handoffId);

        if ($this->connection === 'sync') {
            $pending->afterResponse();
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('CRM handoff: no se pudo guardar el sobre', [
            'handoff_id' => $this->envelope['handoff_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
