<?php

declare(strict_types=1);

namespace App\Support\QrCenter;

use App\Models\QrScan;
use Illuminate\Support\Carbon;

/**
 * Uso de los QR y de las páginas alojadas, sin contar robots (vistas previas de
 * WhatsApp, buscadores). Todo sale de una consulta agrupada por tabla, no por fila.
 */
final class QrCenterStats
{
    public const DAYS = 30;

    /**
     * Lecturas y visitantes únicos de los últimos 30 días, por destino.
     *
     * @return array<int, array{scans: int, visitors: int, mobile: int}>
     */
    public static function byDestination(): array
    {
        return self::grouped('qr_destination_id', QrScan::KIND_SCAN);
    }

    /**
     * Visitas y visitantes únicos de los últimos 30 días, por página alojada.
     *
     * @return array<int, array{scans: int, visitors: int, mobile: int}>
     */
    public static function byHostedPage(): array
    {
        return self::grouped('qr_hosted_page_id', QrScan::KIND_VIEW);
    }

    /**
     * Lecturas por día de un destino, de más antiguo a más reciente, con los días sin lecturas en cero.
     *
     * @return list<array{day: string, scans: int}>
     */
    public static function dailyForDestination(int $destinationId): array
    {
        $since = self::since();
        $counts = QrScan::query()
            ->where('qr_destination_id', $destinationId)
            ->where('kind', QrScan::KIND_SCAN)
            ->where('is_bot', false)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($day = $since->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->toDateString();
            $series[] = ['day' => $key, 'scans' => (int) ($counts[$key] ?? 0)];
        }

        return $series;
    }

    /**
     * @return array<int, array{scans: int, visitors: int, mobile: int}>
     */
    private static function grouped(string $column, string $kind): array
    {
        return QrScan::query()
            ->whereNotNull($column)
            ->where('kind', $kind)
            ->where('is_bot', false)
            ->where('created_at', '>=', self::since())
            ->groupBy($column)
            ->selectRaw($column.' as owner_id, count(*) as scans, count(distinct visitor_hash) as visitors, sum(case when device = ? then 1 else 0 end) as mobile', ['movil'])
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->owner_id => [
                'scans' => (int) $row->scans,
                'visitors' => (int) $row->visitors,
                'mobile' => (int) $row->mobile,
            ]])
            ->all();
    }

    private static function since(): Carbon
    {
        return now()->subDays(self::DAYS - 1)->startOfDay();
    }
}
