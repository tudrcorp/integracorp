<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Doble check: WhatsApp avisa (Evolution → bot → aquí) que una respuesta del analista llegó al
 * teléfono del cliente o que el cliente la leyó.
 *
 * Misma puerta que el resto del CRM (firma HMAC, reloj, cupo por minuto). Se aplica en el acto con
 * dos UPDATE por índice: es idempotente y nunca retrocede (leído no vuelve a entregado; la primera
 * hora gana). No despierta a toda la bandeja: solo sube el pulso de los casos tocados, así se
 * repinta únicamente el chat de quien lo tiene abierto.
 */
final class CrmHandoffDeliveryIngress
{
    public const MAX_ITEMS = 100;

    public function accept(string $rawBody, ?string $timestamp, ?string $signature, string $ip): CrmHandoffIngressResult
    {
        $secret = (string) config('crm-inbox.secret', '');

        if ($secret === '') {
            return CrmHandoffIngressResult::unavailable();
        }

        if (strlen($rawBody) > 32768) {
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

        $statuses = self::parse($rawBody);

        if ($statuses === null) {
            return CrmHandoffIngressResult::invalid('invalid_statuses');
        }

        try {
            if ($this->limited($ip)) {
                return CrmHandoffIngressResult::rateLimited();
            }

            self::apply($statuses);
        } catch (Throwable $exception) {
            Log::warning('CRM handoff: no se pudieron guardar los estados de entrega', ['error' => $exception->getMessage()]);

            return CrmHandoffIngressResult::unavailable();
        }

        return CrmHandoffIngressResult::accepted();
    }

    /**
     * @return array{delivered: list<string>, read: list<string>}|null
     */
    public static function parse(string $rawBody): ?array
    {
        $json = json_decode($rawBody, true);

        if (! is_array($json) || ! is_array($json['statuses'] ?? null) || ! array_is_list($json['statuses'])) {
            return null;
        }

        $result = ['delivered' => [], 'read' => []];

        foreach (array_slice($json['statuses'], 0, self::MAX_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = is_string($item['message_id'] ?? null) ? trim($item['message_id']) : '';
            $status = $item['status'] ?? null;

            if ($id === '' || strlen($id) > 191 || preg_match('/^[A-Za-z0-9._:-]+$/', $id) !== 1 || ! in_array($status, ['delivered', 'read'], true)) {
                continue;
            }

            $result[$status][$id] = $id;
        }

        $result['delivered'] = array_values(array_diff_key($result['delivered'], $result['read']));
        $result['read'] = array_values($result['read']);

        return $result;
    }

    /**
     * @param  array{delivered: list<string>, read: list<string>}  $statuses
     * @return int filas cambiadas
     */
    public static function apply(array $statuses, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $ids = [...$statuses['delivered'], ...$statuses['read']];

        if ($ids === []) {
            return 0;
        }

        $changed = 0;

        if ($statuses['read'] !== []) {
            $changed += CrmHandoffMessage::query()
                ->whereIn('provider_message_id', $statuses['read'])
                ->where('direction', 'out')
                ->whereNull('read_at')
                ->toBase()
                /** MySQL asigna de izquierda a derecha: delivered_at toma la hora de lectura si no tenía. */
                ->update(['read_at' => $now, 'delivered_at' => DB::raw('COALESCE(delivered_at, read_at)'), 'updated_at' => $now]);
        }

        if ($statuses['delivered'] !== []) {
            $changed += CrmHandoffMessage::query()
                ->whereIn('provider_message_id', $statuses['delivered'])
                ->where('direction', 'out')
                ->whereNull('delivered_at')
                ->toBase()
                ->update(['delivered_at' => $now, 'updated_at' => $now]);
        }

        if ($changed > 0) {
            CrmHandoffMessage::query()
                ->whereIn('provider_message_id', $ids)
                ->whereNotNull('handoff_id')
                ->distinct()
                ->pluck('handoff_id')
                ->each(fn (mixed $handoffId) => CrmInboxPulse::bumpThread((string) $handoffId));
        }

        return $changed;
    }

    private function limited(string $ip): bool
    {
        $limit = max(1, (int) config('crm-inbox.rate_per_minute', 600));
        $store = Cache::store(CrmHandoffIngress::resolveCacheStore(
            config('crm-inbox.cache_store'),
            app()->runningUnitTests() || app()->environment('local'),
        ));
        $key = 'crm-handoff:status-rate:'.hash('sha256', $ip).':'.date('YmdHi');

        $store->add($key, 0, 70);

        return (int) $store->increment($key) > $limit;
    }
}
