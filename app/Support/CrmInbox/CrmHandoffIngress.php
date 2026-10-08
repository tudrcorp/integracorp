<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Jobs\StoreCrmHandoffEnvelopeJob;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Acepta el handoff y responde. En producción el cupo y el duplicado viven en
 * Redis. En local pueden vivir en la caché de archivos. La tabla `jobs` no se usa.
 */
final class CrmHandoffIngress
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

        $auth = $this->authorize($rawBody, $timestamp, $signature, $secret);

        if ($auth !== null) {
            return $auth;
        }

        $parsed = CrmHandoffEnvelopeData::fromJson($rawBody);

        if ($parsed['ok'] !== true) {
            return CrmHandoffIngressResult::invalid($parsed['reason']);
        }

        /** @var array<string, mixed> $envelope */
        $envelope = $parsed['envelope'];

        try {
            if ($this->limited($ip)) {
                return CrmHandoffIngressResult::rateLimited();
            }

            $handoffId = (string) $envelope['handoff_id'];
            $store = $this->store();
            $seenKey = 'crm-handoff:id:'.$handoffId;
            $ttl = now()->addDays(max(1, (int) config('crm-inbox.idempotency_ttl_days', 7)));

            if (! $store->add($seenKey, 1, $ttl)) {
                return CrmHandoffIngressResult::duplicate();
            }

            StoreCrmHandoffEnvelopeJob::dispatch($envelope);
        } catch (Throwable $exception) {
            try {
                if (isset($seenKey)) {
                    $this->store()->forget($seenKey);
                }
            } catch (Throwable) {
            }

            Log::warning('CRM handoff: no se pudo aceptar el sobre', [
                'handoff_id' => $envelope['handoff_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return CrmHandoffIngressResult::unavailable();
        }

        return CrmHandoffIngressResult::accepted();
    }

    private function authorize(string $rawBody, ?string $timestamp, ?string $signature, string $secret): ?CrmHandoffIngressResult
    {
        $timestamp = trim((string) $timestamp);
        $signature = trim((string) $signature);

        if ($timestamp === '' || ! ctype_digit($timestamp)) {
            return CrmHandoffIngressResult::unauthorized('missing_timestamp');
        }

        if ($signature === '') {
            return CrmHandoffIngressResult::unauthorized('missing_signature');
        }

        $tolerance = max(30, (int) config('crm-inbox.tolerance', 300));
        $skew = abs(time() - (int) $timestamp);

        if ($skew > $tolerance) {
            return CrmHandoffIngressResult::unauthorized('stale_timestamp');
        }

        if (! CrmHandoffSignature::matches($timestamp, $rawBody, $secret, $signature)) {
            return CrmHandoffIngressResult::unauthorized('bad_signature');
        }

        return null;
    }

    private function limited(string $ip): bool
    {
        $limit = max(1, (int) config('crm-inbox.rate_per_minute', 600));
        $store = $this->store();
        $key = 'crm-handoff:rate:'.hash('sha256', $ip).':'.date('YmdHi');

        $store->add($key, 0, 70);
        $count = (int) $store->increment($key);

        return $count > $limit;
    }

    /**
     * Producción usa Redis (`crm-inbox`). `file` y `array` solo en local o en pruebas.
     */
    public static function resolveCacheStore(mixed $configured, bool $allowLocal): string
    {
        $configured = is_string($configured) && $configured !== '' ? $configured : 'crm-inbox';

        if ($configured === 'crm-inbox') {
            return 'crm-inbox';
        }

        if ($allowLocal && in_array($configured, ['file', 'array'], true)) {
            return $configured;
        }

        return 'crm-inbox';
    }

    private function store(): Repository
    {
        $name = self::resolveCacheStore(
            config('crm-inbox.cache_store'),
            app()->runningUnitTests() || app()->environment('local'),
        );

        return Cache::store($name);
    }
}
