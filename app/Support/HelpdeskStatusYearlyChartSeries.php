<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class HelpdeskStatusYearlyChartSeries
{
    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            'PENDIENTE POR INICIAR',
            'EN PROCESO',
            'TERMINADO',
        ];
    }

    /**
     * @return list<string>
     */
    public static function monthLabels(): array
    {
        return [
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $records
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function chartJsDataFromRecords(Collection $records, int $year): array
    {
        $statusToColor = [
            'PENDIENTE POR INICIAR' => 'rgba(245, 158, 11, 0.82)',
            'EN PROCESO' => 'rgba(37, 99, 235, 0.82)',
            'TERMINADO' => 'rgba(16, 185, 129, 0.82)',
        ];

        $statusToHover = [
            'PENDIENTE POR INICIAR' => 'rgba(245, 158, 11, 0.95)',
            'EN PROCESO' => 'rgba(37, 99, 235, 0.95)',
            'TERMINADO' => 'rgba(16, 185, 129, 0.95)',
        ];

        $series = collect(self::statuses())
            ->mapWithKeys(fn (string $status): array => [$status => array_fill(0, 12, 0)])
            ->all();

        foreach ($records as $record) {
            $status = (string) ($record->status ?? '');
            if (! array_key_exists($status, $series)) {
                continue;
            }

            $createdAt = $record->created_at ?? null;
            if (! $createdAt) {
                continue;
            }

            $date = $createdAt instanceof Carbon ? $createdAt : Carbon::parse((string) $createdAt);
            if ((int) $date->year !== $year) {
                continue;
            }

            $monthIndex = ((int) $date->month) - 1;
            if ($monthIndex < 0 || $monthIndex > 11) {
                continue;
            }

            $series[$status][$monthIndex]++;
        }

        $datasets = [];
        foreach ($series as $status => $values) {
            $datasets[] = [
                'label' => $status,
                'data' => $values,
                'backgroundColor' => $statusToColor[$status] ?? 'rgba(107, 114, 128, 0.82)',
                'hoverBackgroundColor' => $statusToHover[$status] ?? 'rgba(107, 114, 128, 0.95)',
                'borderColor' => 'rgba(255, 255, 255, 0.85)',
                'borderWidth' => 1.2,
                'borderRadius' => 10,
                'borderSkipped' => false,
            ];
        }

        return [
            'labels' => self::monthLabels(),
            'datasets' => $datasets,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $records
     * @return array<int, array{colaborador: string, colaborador_label: string, totals: array<string, int>, total: int}>
     */
    public static function detailRowsFromRecords(Collection $records): array
    {
        $statuses = self::statuses();

        $rows = [];

        foreach ($records as $record) {
            $status = (string) ($record->status ?? '');
            if (! in_array($status, $statuses, true)) {
                continue;
            }

            $colaboradores = $record->rrhhColaboradores ?? null;
            if (! $colaboradores) {
                continue;
            }

            foreach ($colaboradores as $colaborador) {
                $fullName = trim((string) ($colaborador->fullName ?? ''));
                $fullName = $fullName !== '' ? $fullName : 'Sin nombre';
                $label = self::shortCollaboratorLabel($fullName);

                if (! array_key_exists($fullName, $rows)) {
                    $rows[$fullName] = [
                        'colaborador' => $fullName,
                        'colaborador_label' => $label,
                        'totals' => array_fill_keys($statuses, 0),
                        'total' => 0,
                    ];
                }

                $rows[$fullName]['totals'][$status] = (int) ($rows[$fullName]['totals'][$status] ?? 0) + 1;
                $rows[$fullName]['total']++;
            }
        }

        return collect($rows)
            ->sort(function (array $left, array $right): int {
                $leftDone = (int) ($left['totals']['TERMINADO'] ?? 0);
                $rightDone = (int) ($right['totals']['TERMINADO'] ?? 0);

                if ($leftDone !== $rightDone) {
                    return $rightDone <=> $leftDone;
                }

                $leftTotal = (int) ($left['total'] ?? 0);
                $rightTotal = (int) ($right['total'] ?? 0);
                if ($leftTotal !== $rightTotal) {
                    return $rightTotal <=> $leftTotal;
                }

                return strcmp((string) ($left['colaborador'] ?? ''), (string) ($right['colaborador'] ?? ''));
            })
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $records
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function detailChartDataFromRecords(Collection $records): array
    {
        $rows = self::detailRowsFromRecords($records);
        $statuses = self::statuses();

        $labels = array_map(
            static fn (array $row): string => (string) ($row['colaborador_label'] ?? $row['colaborador'] ?? 'Sin nombre'),
            $rows
        );

        $statusToColor = [
            'PENDIENTE POR INICIAR' => 'rgba(245, 158, 11, 0.82)',
            'EN PROCESO' => 'rgba(37, 99, 235, 0.82)',
            'TERMINADO' => 'rgba(16, 185, 129, 0.82)',
        ];

        $statusToHover = [
            'PENDIENTE POR INICIAR' => 'rgba(245, 158, 11, 0.95)',
            'EN PROCESO' => 'rgba(37, 99, 235, 0.95)',
            'TERMINADO' => 'rgba(16, 185, 129, 0.95)',
        ];

        $datasets = [];
        foreach ($statuses as $status) {
            $datasets[] = [
                'label' => $status,
                'data' => array_map(
                    static fn (array $row): int => (int) (($row['totals'][$status] ?? 0)),
                    $rows
                ),
                'backgroundColor' => $statusToColor[$status] ?? 'rgba(107, 114, 128, 0.82)',
                'hoverBackgroundColor' => $statusToHover[$status] ?? 'rgba(107, 114, 128, 0.95)',
                'borderColor' => 'rgba(255, 255, 255, 0.85)',
                'borderWidth' => 1.2,
                'borderRadius' => 10,
                'borderSkipped' => false,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private static function shortCollaboratorLabel(string $fullName): string
    {
        $normalizedName = Str::squish($fullName);
        $parts = preg_split('/\s+/', $normalizedName) ?: [];

        if (count($parts) <= 1) {
            return Str::limit($normalizedName, 18, '...');
        }

        $firstAndSecond = $parts[0].' '.$parts[1];
        if (mb_strlen($firstAndSecond) <= 18) {
            return $firstAndSecond;
        }

        $secondInitial = mb_strtoupper(mb_substr($parts[1], 0, 1));

        return Str::limit($parts[0].' '.$secondInitial.'.', 18, '...');
    }
}
