<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use Carbon\Carbon;

final class IndicadoresDeDesempenoTimeBuckets
{
    /**
     * @return list<string>
     */
    public static function monthLabels(): array
    {
        return ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    }

    public static function monthLabel(int $month): string
    {
        return self::monthLabels()[$month - 1] ?? (string) $month;
    }

    public static function weeksInMonth(int $year, int $month): int
    {
        $days = Carbon::create($year, $month, 1)->daysInMonth;

        return (int) ceil($days / 7);
    }

    public static function weekBucketFromDay(int $dayOfMonth): int
    {
        return (int) floor(($dayOfMonth - 1) / 7) + 1;
    }

    /**
     * @return list<string>
     */
    public static function weekLabels(int $year, int $month): array
    {
        $labels = [];
        $weeks = self::weeksInMonth($year, $month);

        for ($week = 1; $week <= $weeks; $week++) {
            $labels[] = "Semana {$week}";
        }

        return $labels;
    }

    /**
     * @return array{from: string, to: string}
     */
    public static function weekDateRange(int $year, int $month, int $week): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $startDay = (($week - 1) * 7) + 1;
        $endDay = min($week * 7, $daysInMonth);

        return [
            'from' => Carbon::create($year, $month, $startDay)->toDateString(),
            'to' => Carbon::create($year, $month, $endDay)->toDateString(),
        ];
    }

    /**
     * @param  array<int, int>  $countsByMonth  month (1-12) => total
     * @return list<int>
     */
    public static function fillMonthlyTotals(array $countsByMonth): array
    {
        $totals = [];

        for ($month = 1; $month <= 12; $month++) {
            $totals[] = (int) ($countsByMonth[$month] ?? 0);
        }

        return $totals;
    }

    /**
     * @param  array<int, int>  $countsByWeek  week (1-N) => total
     * @return list<int>
     */
    public static function fillWeeklyTotals(int $year, int $month, array $countsByWeek): array
    {
        $totals = [];
        $weeks = self::weeksInMonth($year, $month);

        for ($week = 1; $week <= $weeks; $week++) {
            $totals[] = (int) ($countsByWeek[$week] ?? 0);
        }

        return $totals;
    }

    /**
     * @param  array<int, int>  $countsByBucket
     * @return array<int, int>
     */
    public static function incrementBucket(array $countsByBucket, int $bucket): array
    {
        $countsByBucket[$bucket] = ($countsByBucket[$bucket] ?? 0) + 1;

        return $countsByBucket;
    }
}
