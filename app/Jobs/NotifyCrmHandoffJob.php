<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CrmHandoffEnvelope;
use App\Support\CrmInbox\CrmHandoffIngress;
use App\Support\CrmInbox\CrmInboxAlerts;
use App\Support\CrmInbox\CrmWebPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class NotifyCrmHandoffJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(
        public string $handoffId,
        public string $notice = 'new',
        public ?int $onlyUserId = null,
    ) {
        $this->onConnection(StoreCrmHandoffEnvelopeJob::resolveConnection(
            config('crm-inbox.queue_connection'),
            app()->runningUnitTests() || app()->environment('local'),
        ));
        $this->onQueue((string) config('crm-inbox.queue', 'inbox'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 20, 60];
    }

    public function handle(CrmWebPush $push): void
    {
        if ($this->handoffId === '' || ! Schema::hasTable('users')) {
            return;
        }

        $envelope = CrmHandoffEnvelope::query()->where('handoff_id', $this->handoffId)->first();

        if ($envelope === null || $envelope->released_at !== null || $envelope->closed_at !== null) {
            return;
        }

        $onlyUserId = $this->notice === 'assign' ? $this->onlyUserId : null;
        CrmInboxAlerts::remember($envelope, $this->notice, $onlyUserId);

        if (! Schema::hasTable('crm_push_subscriptions')) {
            return;
        }

        $payload = json_encode(
            CrmInboxAlerts::payload($envelope, $this->notice, $this->onlyUserId),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $failed = false;

        foreach (CrmInboxAlerts::subscriptionsFor($envelope, $onlyUserId) as $subscription) {
            $key = match ($this->notice) {
                'assign' => 'crm-push:assign:'.$this->handoffId.':'.$this->onlyUserId.':'.$subscription->id,
                'move' => 'crm-push:move:'.$this->handoffId.':'.($envelope->area ?? '').':'.$subscription->id,
                default => 'crm-push:sent:'.$this->handoffId.':'.$subscription->id,
            };

            if ($this->cache()->has($key)) {
                continue;
            }

            try {
                $result = $push->send($subscription, $payload);
            } catch (Throwable $exception) {
                Log::warning('CRM push: el aviso no salió', [
                    'handoff_id' => $this->handoffId,
                    'subscription_id' => $subscription->id,
                    'error' => $exception->getMessage(),
                ]);
                $failed = true;

                continue;
            }

            if ($result === 'gone') {
                $subscription->delete();

                continue;
            }

            if ($result !== 'sent') {
                Log::warning('CRM push: el aviso no salió', [
                    'handoff_id' => $this->handoffId,
                    'subscription_id' => $subscription->id,
                    'result' => $result,
                ]);
                $failed = true;

                continue;
            }

            $this->cache()->put($key, 1, now()->addDays(max(1, (int) config('crm-inbox.idempotency_ttl_days', 7))));
        }

        if ($failed) {
            throw new RuntimeException('CRM push: algún aviso no salió.');
        }
    }

    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        $name = CrmHandoffIngress::resolveCacheStore(
            config('crm-inbox.cache_store'),
            app()->runningUnitTests() || app()->environment('local'),
        );

        return Cache::store($name);
    }
}
