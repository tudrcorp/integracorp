<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Jobs\StoreCrmHandoffMediaJob;
use App\Models\CrmHandoffAttachment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Puerta de los archivos del cliente. Verifica la firma igual que la de mensajes, valida el archivo y lo
 * guarda en el disco privado en la misma petición: así a la cola solo viajan los datos, nunca 20 MB.
 * El registro en la base (mensaje + adjunto) lo hace {@see StoreCrmHandoffMediaJob}.
 */
final class CrmHandoffMediaIngress
{
    public function accept(string $rawBody, ?string $timestamp, ?string $signature, string $ip): CrmHandoffIngressResult
    {
        $secret = (string) config('crm-inbox.secret', '');

        if ($secret === '') {
            return CrmHandoffIngressResult::unavailable();
        }

        if (strlen($rawBody) > CrmHandoffMediaData::maxBodyBytes()) {
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

        if (abs(time() - (int) $timestamp) > max(30, (int) config('crm-inbox.tolerance', 300))) {
            return CrmHandoffIngressResult::unauthorized('stale_timestamp');
        }

        if (! CrmHandoffSignature::matches($timestamp, $rawBody, $secret, $signature)) {
            return CrmHandoffIngressResult::unauthorized('bad_signature');
        }

        if ($this->limited($ip)) {
            return CrmHandoffIngressResult::rateLimited();
        }

        $parsed = CrmHandoffMediaData::fromJson($rawBody);
        unset($rawBody);

        if ($parsed['ok'] !== true) {
            return $parsed['reason'] === 'file_too_large'
                ? CrmHandoffIngressResult::tooLarge()
                : CrmHandoffIngressResult::invalid($parsed['reason']);
        }

        $media = $parsed['media'];
        $disk = (string) config('crm-inbox.media.disk', 'local');
        $path = null;

        try {
            $store = Cache::store(CrmHandoffIngress::resolveCacheStore(
                config('crm-inbox.cache_store'),
                app()->runningUnitTests() || app()->environment('local'),
            ));
            $seenKey = 'crm-handoff:media:'.$media['message_id'];
            $ttl = now()->addDays(max(1, (int) config('crm-inbox.idempotency_ttl_days', 7)));

            if (! $store->add($seenKey, 1, $ttl) || CrmHandoffAttachment::query()->where('message_id', $media['message_id'])->exists()) {
                return CrmHandoffIngressResult::duplicate();
            }

            $path = trim((string) config('crm-inbox.media.directory', 'crm/attachments'), '/')
                .'/'.now()->format('Y/m').'/'.Str::uuid()->toString().'.'.$media['extension'];

            if (! Storage::disk($disk)->put($path, $media['content'])) {
                throw new \RuntimeException('No se pudo escribir el archivo.');
            }

            unset($media['content']);

            StoreCrmHandoffMediaJob::dispatch([...$media, 'disk' => $disk, 'path' => $path]);
        } catch (Throwable $exception) {
            try {
                if ($path !== null) {
                    Storage::disk($disk)->delete($path);
                }

                if (isset($seenKey, $store)) {
                    $store->forget($seenKey);
                }
            } catch (Throwable) {
            }

            Log::warning('CRM handoff: no se pudo aceptar el archivo', [
                'message_id' => $media['message_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return CrmHandoffIngressResult::unavailable();
        }

        return CrmHandoffIngressResult::accepted();
    }

    private function limited(string $ip): bool
    {
        $limit = max(1, (int) config('crm-inbox.rate_per_minute', 600));

        try {
            $store = Cache::store(CrmHandoffIngress::resolveCacheStore(
                config('crm-inbox.cache_store'),
                app()->runningUnitTests() || app()->environment('local'),
            ));
            $key = 'crm-handoff:media-rate:'.sha1($ip).':'.now()->format('YmdHi');
            $store->add($key, 0, now()->addMinutes(2));

            return (int) $store->increment($key) > $limit;
        } catch (Throwable) {
            return false;
        }
    }
}
