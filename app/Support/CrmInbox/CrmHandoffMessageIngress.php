<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Jobs\StoreCrmHandoffMessageJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Acepta el mensaje siguiente del cliente. Misma firma que el sobre.
 * Un message_id repetido no crea otra fila.
 */
final class CrmHandoffMessageIngress
{
    public function accept(string $rawBody, ?string $timestamp, ?string $signature, string $ip): CrmHandoffIngressResult
    {
        $secret = (string) config('crm-inbox.secret', '');

        if ($secret === '') {
            return CrmHandoffIngressResult::unavailable();
        }

        $maxBytes = max(1024, (int) config('crm-inbox.max_bytes', 65536));

        if (strlen($rawBody) > $maxBytes) {
            return CrmHandoffIngressResult::tooLarge();
        }

        $timestamp = trim((string) $timestamp);
        $signature = trim((string) $signature);

        if ($timestamp === '' || ! ctype_digit($timestamp)) {
            return CrmHandoffIngressResult::unauthorized('missing_timestamp');
        }

        if ($signature === '') {
            return CrmHandoffIngressResult::unauthorized('missing_signature');
        }

        $tolerance = max(30, (int) config('crm-inbox.tolerance', 300));

        if (abs(time() - (int) $timestamp) > $tolerance) {
            return CrmHandoffIngressResult::unauthorized('stale_timestamp');
        }

        if (! CrmHandoffSignature::matches($timestamp, $rawBody, $secret, $signature)) {
            return CrmHandoffIngressResult::unauthorized('bad_signature');
        }

        $parsed = CrmHandoffMessageData::fromJson($rawBody);

        if ($parsed['ok'] !== true) {
            return CrmHandoffIngressResult::invalid($parsed['reason']);
        }

        /** @var array{message_id: string, handoff_id: ?string, phone: string, text: string} $message */
        $message = $parsed['message'];

        try {
            if ($this->limited($ip)) {
                return CrmHandoffIngressResult::rateLimited();
            }

            $store = Cache::store(CrmHandoffIngress::resolveCacheStore(
                config('crm-inbox.cache_store'),
                app()->runningUnitTests() || app()->environment('local'),
            ));
            $seenKey = 'crm-handoff:msg:'.$message['message_id'];
            $ttl = now()->addDays(max(1, (int) config('crm-inbox.idempotency_ttl_days', 7)));

            if (! $store->add($seenKey, 1, $ttl)) {
                return CrmHandoffIngressResult::duplicate();
            }

            StoreCrmHandoffMessageJob::dispatch($message);
        } catch (Throwable $exception) {
            try {
                if (isset($seenKey, $store)) {
                    $store->forget($seenKey);
                }
            } catch (Throwable) {
            }

            Log::warning('CRM handoff: no se pudo aceptar el mensaje', [
                'message_id' => $message['message_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return CrmHandoffIngressResult::unavailable();
        }

        return CrmHandoffIngressResult::accepted();
    }

    private function limited(string $ip): bool
    {
        $limit = max(1, (int) config('crm-inbox.rate_per_minute', 600));
        $store = Cache::store(CrmHandoffIngress::resolveCacheStore(
            config('crm-inbox.cache_store'),
            app()->runningUnitTests() || app()->environment('local'),
        ));
        $key = 'crm-handoff:msg-rate:'.hash('sha256', $ip).':'.date('YmdHi');

        $store->add($key, 0, 70);
        $count = (int) $store->increment($key);

        return $count > $limit;
    }
}
