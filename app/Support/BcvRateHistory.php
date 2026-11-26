<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BcvHistoricalRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tasa oficial BCV por fecha (Bs. por US$), guardada en `bcv_official_rates`.
 *
 * - Fuente: histórico público de ve.dolarapi.com (la misma API de la tasa del día), desde 2023.
 * - Regla: la tasa de una fecha es la publicada ese día; si ese día no hubo publicación (fin de
 *   semana o feriado), la última publicada antes, hasta {@see MAX_GAP_DAYS} días atrás.
 * - Si la API no responde, se usa lo ya guardado. Solo se consulta la API cuando falta una fecha
 *   posterior a la última guardada, y como mucho una vez cada {@see SYNC_COOLDOWN_MINUTES} minutos.
 */
final class BcvRateHistory
{
    public const HISTORY_URL = 'https://ve.dolarapi.com/v1/historicos/dolares/oficial';

    /** Feriados largos (Carnaval, Semana Santa, diciembre) caben de sobra; más que esto ya no es confiable. */
    public const MAX_GAP_DAYS = 10;

    public const SYNC_COOLDOWN_MINUTES = 10;

    /**
     * Trae el histórico y guarda las fechas nuevas o corregidas. Devuelve cuántas filas quedaron
     * escritas, o null si la API no respondió bien (lo guardado no se toca).
     */
    public static function sync(): ?int
    {
        try {
            $response = Http::timeout(20)->connectTimeout(5)->acceptJson()->get(self::HISTORY_URL);
        } catch (Throwable $exception) {
            Log::warning('BCV: no se pudo leer el histórico de tasas', ['error' => $exception->getMessage()]);

            return null;
        }

        $rows = $response->json();

        if (! $response->successful() || ! is_array($rows) || ! array_is_list($rows)) {
            Log::warning('BCV: respuesta inválida del histórico de tasas', ['status' => $response->status()]);

            return null;
        }

        $now = now();
        $records = [];

        foreach ($rows as $row) {
            $date = is_array($row) && is_string($row['fecha'] ?? null) ? $row['fecha'] : null;
            $rate = is_array($row) ? ($row['promedio'] ?? null) : null;

            if ($date === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || ! is_numeric($rate) || (float) $rate <= 0) {
                continue;
            }

            $records[$date] = ['rate_date' => $date, 'rate' => number_format((float) $rate, 8, '.', ''), 'source' => 'dolarapi', 'created_at' => $now, 'updated_at' => $now];
        }

        if ($records === []) {
            return null;
        }

        foreach (array_chunk(array_values($records), 500) as $chunk) {
            BcvHistoricalRate::query()->upsert($chunk, ['rate_date'], ['rate', 'source', 'updated_at']);
        }

        return count($records);
    }

    /**
     * @return array{rate: string, published: string}|null published = fecha de publicación usada (Y-m-d)
     */
    public static function forDate(string $date): ?array
    {
        return self::forDates([$date])[$date] ?? null;
    }

    /**
     * Tasas de varias fechas con una sola consulta.
     *
     * @param  list<string>  $dates  'Y-m-d'
     * @return array<string, array{rate: string, published: string}|null>
     */
    public static function forDates(array $dates): array
    {
        $dates = array_values(array_unique(array_filter($dates, fn (mixed $date): bool => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)));

        if ($dates === []) {
            return [];
        }

        sort($dates);
        $latest = self::latestStored();

        if ($latest === null || end($dates) > $latest) {
            self::syncThrottled();
        }

        $from = CarbonImmutable::parse($dates[0])->subDays(self::MAX_GAP_DAYS)->toDateString();
        $published = BcvHistoricalRate::query()
            ->whereBetween('rate_date', [$from, end($dates)])
            ->orderBy('rate_date')
            ->get(['rate_date', 'rate'])
            ->mapWithKeys(fn (BcvHistoricalRate $row): array => [$row->rate_date->toDateString() => (string) $row->rate])
            ->all();

        $result = [];

        foreach ($dates as $date) {
            $result[$date] = null;
            $cursor = CarbonImmutable::parse($date);

            for ($i = 0; $i <= self::MAX_GAP_DAYS; $i++) {
                $key = $cursor->subDays($i)->toDateString();

                if (isset($published[$key])) {
                    $result[$date] = ['rate' => $published[$key], 'published' => $key];

                    break;
                }
            }
        }

        return $result;
    }

    public static function latestStored(): ?string
    {
        $latest = BcvHistoricalRate::query()->max('rate_date');

        return $latest !== null ? CarbonImmutable::parse($latest)->toDateString() : null;
    }

    /**
     * Sincroniza solo si no se intentó en los últimos minutos (también tras un fallo): pantallas y
     * importaciones no esperan a la API en cada apertura.
     */
    private static function syncThrottled(): void
    {
        try {
            if (! Cache::add('bcv-rate-history:sync-attempt', 1, now()->addMinutes(self::SYNC_COOLDOWN_MINUTES))) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        self::sync();
    }
}
