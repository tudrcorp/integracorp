<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CrmHandoffEnvelope;
use App\Support\CrmInbox\CrmHandoffDuplicate;
use App\Support\CrmInbox\CrmHandoffIngress;
use App\Support\CrmInbox\CrmInboxPulse;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        $phone = (string) ($this->envelope['phone'] ?? '');
        $lock = $this->phoneLock($phone);

        try {
            $outcome = DB::transaction(function () use ($handoffId, $phone): string {
                if (CrmHandoffEnvelope::query()->where('handoff_id', $handoffId)->exists()) {
                    return 'existing';
                }

                $open = CrmHandoffDuplicate::openFor($phone);

                if ($open !== null) {
                    CrmHandoffDuplicate::merge($open, $this->envelope);

                    return 'merged';
                }

                CrmHandoffEnvelope::query()->create([
                    'handoff_id' => $handoffId,
                    'phone' => $phone,
                    'area' => $this->envelope['area'] ?? null,
                    'motivo' => $this->envelope['motivo'] ?? null,
                    'payload' => $this->envelope,
                    'accepted_at' => now(),
                ]);

                return 'created';
            });
        } finally {
            $lock?->release();
        }

        if ($outcome === 'merged') {
            Log::info('CRM handoff: derivación repetida unida al caso abierto del cliente', ['handoff_id' => $handoffId]);
            CrmInboxPulse::bump();

            return;
        }

        $pending = NotifyCrmHandoffJob::dispatch($handoffId);

        if ($this->connection === 'sync') {
            $pending->afterResponse();
        }
    }

    /**
     * Dos derivaciones del mismo teléfono que llegan a la vez se procesan una tras otra, así la
     * segunda ve el caso que abrió la primera. Sin Redis, el bloqueo de fila de la transacción
     * sigue protegiendo el caso que ya existía.
     */
    private function phoneLock(string $phone): ?Lock
    {
        if ($phone === '') {
            return null;
        }

        try {
            $lock = Cache::store(CrmHandoffIngress::resolveCacheStore(
                config('crm-inbox.cache_store'),
                app()->runningUnitTests() || app()->environment('local'),
            ))->lock('crm-envelope-phone:'.$phone, 15);

            return $lock->block(10) ? $lock : null;
        } catch (Throwable) {
            return null;
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
