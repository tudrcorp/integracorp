<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Business\Pages\UserActivityMonitor;
use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use App\Support\UserActivity\UserActivityClock;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Throwable;

/**
 * Comparativa del personal: cada persona frente al promedio de su área y de la
 * empresa. Se ordena y se busca en el navegador (ya viene calculada).
 */
class UsersComparisonTable extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.metrics.corporate.users-comparison';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $data = CorporateUsersMetrics::comparison($period);
        $monitorUrl = $this->monitorUrl($period->from, $period->to);
        $today = UserActivityClock::now()->startOfDay();

        $rows = array_map(static function (array $row) use ($monitorUrl, $today): array {
            $seen = $row['last_seen'] !== null ? CarbonImmutable::parse($row['last_seen']) : null;
            $seenDays = $seen !== null ? (int) $seen->startOfDay()->diffInDays($today) : null;

            return [
                'id' => $row['user_id'],
                'name' => $row['name'],
                'department' => $row['department'],
                'days' => $row['days'],
                'active' => $row['active'],
                'active_text' => CorporateUsersMetrics::hours($row['active']),
                'avg_day' => $row['avg_day'],
                'avg_day_text' => $row['days'] > 0 ? CorporateUsersMetrics::hours($row['avg_day']) : '—',
                'vs_area' => $row['vs_area'],
                'vs_company' => $row['vs_company'],
                'work_usage' => $row['work_usage'],
                'overtime' => $row['overtime'],
                'overtime_text' => $row['overtime'] > 0 ? CorporateUsersMetrics::hours($row['overtime']) : '—',
                'records' => $row['records'],
                'records_per_hour' => $row['records_per_hour'],
                'change' => $row['change'],
                'last_seen' => $seenDays,
                'last_seen_text' => match (true) {
                    $seen === null => 'Sin registro',
                    $seenDays === 0 => 'Hoy',
                    $seenDays === 1 => 'Ayer',
                    default => $seen->format('d/m/Y'),
                },
                'monitor_url' => $monitorUrl !== null ? $monitorUrl.'&q='.rawurlencode($row['name']) : null,
            ];
        }, $data['rows']);

        return [
            'rows' => $rows,
            'companyAvgDay' => CorporateUsersMetrics::hours($data['company_avg_day']),
            'companyRecordsPerHour' => $data['company_records_per_hour'],
            'compare' => $period->compare,
            'previousLabel' => $period->previousLabel(),
            'periodKey' => $period->cacheKey(),
        ];
    }

    /**
     * Enlace al reporte de Actividad de usuarios (Negocios) en el mismo rango,
     * solo si quien mira puede entrar.
     */
    private function monitorUrl(CarbonImmutable $from, CarbonImmutable $to): ?string
    {
        try {
            if (! UserActivityMonitor::canAccess()) {
                return null;
            }

            return UserActivityMonitor::getUrl(['vista' => 'report', 'desde' => $from->toDateString(), 'hasta' => $to->toDateString()], panel: 'business');
        } catch (Throwable) {
            return null;
        }
    }
}
