<?php

declare(strict_types=1);

namespace App\Support\Operations\Providers;

use App\Enums\ProviderNetworkType;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Datos del Dashboard de proveedores.
 *
 * La red completa (unos mil registros) se lee con tres consultas de columnas mínimas y se resume en
 * memoria; el resultado queda en caché hasta que alguien crea, edita o elimina un proveedor
 * ({@see self::flush()} desde el observer). La productividad depende del período y se cachea por
 * período con la misma versión.
 */
final class ProviderNetworkAnalytics
{
    public const CACHE_SECONDS = 600;

    private const VERSION_KEY = 'provider-network-dashboard:version';

    public const UNKNOWN_USER = 'Sin usuario registrado';

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    /**
     * Una fila compacta por proveedor vigente.
     *
     * @return list<array{type: string, id: int, name: string, status: string, status_raw: string, agreement: string, class: string, geo: ?string, abroad: ?string, letter: ?bool, created_by: string, created_at: ?string}>
     */
    public static function rows(): array
    {
        return self::remember('rows', static function (): array {
            $rows = [];
            $classifications = DB::table('supplier_clasificacions')->pluck('description', 'id')->all();

            $suppliers = DB::table('suppliers')->whereNull('deleted_at')->get([
                'id', 'name', 'razon_social', 'status_sistema', 'status_convenio', 'supplier_clasificacion_id', 'state_id', 'carta_acceptance', 'created_by', 'created_at',
            ]);

            foreach ($suppliers as $supplier) {
                $rows[] = self::row(ProviderNetworkType::Juridico, (array) $supplier, [
                    'class' => ProviderNetworkCatalog::label($classifications[(int) $supplier->supplier_clasificacion_id] ?? null, 'Sin clasificación'),
                    'geo' => ProviderLocation::fromStateId($supplier->state_id),
                    'abroad' => null,
                ]);
            }

            $naturals = DB::table('doctor_nurses')->whereNull('deleted_at')->get([
                'id', 'name', 'razon_social', 'status_sistema', 'status_sistena', 'status_convenio', 'speciality', 'supplier_clasificacion_id', 'state', 'carta_acceptance', 'created_by', 'created_at',
            ]);

            foreach ($naturals as $natural) {
                $rows[] = self::row(ProviderNetworkType::Natural, (array) $natural, [
                    'class' => ProviderNetworkCatalog::label($natural->speciality ?: ($classifications[(int) $natural->supplier_clasificacion_id] ?? null), 'Sin especialidad'),
                    'geo' => ProviderLocation::fromStateName($natural->state),
                    'abroad' => null,
                ]);
            }

            $allies = DB::table('corporate_allies')->whereNull('deleted_at')->get([
                'id', 'company_name', 'status_agreement', 'type_agreement', 'supplier_category', 'country_id', 'state_id', 'created_by', 'created_at',
            ]);

            foreach ($allies as $ally) {
                $inVenezuela = $ally->country_id === null || (int) $ally->country_id === ProviderLocation::VENEZUELA_ID;
                $row = (array) $ally;
                $row['name'] = $ally->company_name;

                $rows[] = self::row(ProviderNetworkType::Aliado, $row, [
                    'class' => ProviderNetworkCatalog::label($ally->supplier_category, 'Sin categoría'),
                    'geo' => $inVenezuela ? ProviderLocation::fromStateId($ally->state_id) : null,
                    'abroad' => $inVenezuela ? null : ProviderLocation::countryName($ally->country_id),
                ]);
            }

            return $rows;
        });
    }

    /**
     * Todo lo que es foto a hoy: totales, estatus, convenios, clasificaciones, cartas, mapa y calidad.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(?string $type = null): array
    {
        return self::remember('snapshot:'.($type ?? 'all'), static function () use ($type): array {
            $rows = array_values(array_filter(self::rows(), static fn (array $row): bool => $type === null || $row['type'] === $type));
            $types = array_map(static fn (ProviderNetworkType $case): string => $case->value, ProviderNetworkType::cases());
            $zero = array_fill_keys($types, 0);

            $totals = $zero;
            $active = $zero;
            $status = [];
            $statusOther = [];
            $agreements = [];
            $classes = [];
            $letters = ['with' => $zero, 'without' => $zero, 'active_without' => $zero];
            $map = ['all' => [], 'active' => [], 'without_letter' => []] + array_fill_keys($types, []);
            $stateDetail = [];
            $abroad = [];
            $quality = ['no_location' => $zero, 'no_status' => $zero, 'no_agreement' => $zero, 'other_status' => $zero];

            foreach ($rows as $row) {
                $t = $row['type'];
                $isActive = ProviderNetworkCatalog::isActiveStatus($row['status']);
                $totals[$t]++;
                $active[$t] += $isActive ? 1 : 0;
                $status[$row['status']][$t] = ($status[$row['status']][$t] ?? 0) + 1;
                $agreements[$row['agreement']][$t] = ($agreements[$row['agreement']][$t] ?? 0) + 1;
                $classes[$t][$row['class']] = ($classes[$t][$row['class']] ?? 0) + 1;

                if ($row['status'] === ProviderNetworkCatalog::STATUS_OTHER) {
                    $statusOther[$row['status_raw']] = ($statusOther[$row['status_raw']] ?? 0) + 1;
                    $quality['other_status'][$t]++;
                }

                if ($row['status'] === ProviderNetworkCatalog::STATUS_NONE) {
                    $quality['no_status'][$t]++;
                }

                if ($row['agreement'] === ProviderNetworkCatalog::AGREEMENT_NONE && $t !== ProviderNetworkType::Aliado->value) {
                    $quality['no_agreement'][$t]++;
                }

                if ($row['letter'] !== null) {
                    $letters[$row['letter'] ? 'with' : 'without'][$t]++;
                    $letters['active_without'][$t] += $isActive && ! $row['letter'] ? 1 : 0;
                }

                if ($row['abroad'] !== null) {
                    $abroad[$row['abroad']][$t] = ($abroad[$row['abroad']][$t] ?? 0) + 1;

                    continue;
                }

                if ($row['geo'] === null) {
                    $quality['no_location'][$t]++;

                    continue;
                }

                $geo = $row['geo'];
                $map['all'][$geo] = ($map['all'][$geo] ?? 0) + 1;
                $map[$t][$geo] = ($map[$t][$geo] ?? 0) + 1;
                $map['active'][$geo] = ($map['active'][$geo] ?? 0) + ($isActive ? 1 : 0);

                if ($row['letter'] === false) {
                    $map['without_letter'][$geo] = ($map['without_letter'][$geo] ?? 0) + 1;
                }

                $stateDetail[$geo]['types'][$t] = ($stateDetail[$geo]['types'][$t] ?? 0) + 1;
                $stateDetail[$geo]['status'][$row['status']] = ($stateDetail[$geo]['status'][$row['status']] ?? 0) + 1;
                $stateDetail[$geo]['agreements'][$row['agreement']] = ($stateDetail[$geo]['agreements'][$row['agreement']] ?? 0) + 1;
            }

            foreach ($map as $layer => $values) {
                arsort($values);
                $map[$layer] = $values;
            }

            foreach ($classes as $t => $values) {
                arsort($values);
                $classes[$t] = $values;
            }

            arsort($statusOther);

            return [
                'total' => array_sum($totals),
                'totals' => $totals,
                'active' => $active,
                'status' => self::ordered($status, array_keys(ProviderNetworkCatalog::STATUSES)),
                'status_other' => $statusOther,
                'agreements' => self::ordered($agreements, array_keys(ProviderNetworkCatalog::AGREEMENTS)),
                'classes' => $classes,
                'letters' => $letters,
                'map' => $map,
                'states' => $stateDetail,
                'abroad' => $abroad,
                'quality' => $quality,
            ];
        });
    }

    /**
     * Productividad del equipo en el período: altas por usuario (de los propios registros) y lo que
     * registra la bitácora (actualizaciones, estatus, convenios logrados, cartas).
     *
     * @return array{users: list<array<string, mixed>>, totals: array<string, int>, previous: array<string, int>, since: ?string, trend: array<string, int>}
     */
    public static function productivity(OperationsDashboardPeriod $period): array
    {
        return self::remember('productivity:'.$period->cacheKey(), static function () use ($period): array {
            $current = self::activityBetween($period->from->startOfDay(), $period->to->endOfDay());
            $previous = self::activityBetween($period->previousFrom->startOfDay(), $period->previousTo->endOfDay());
            $since = DB::table('provider_activity_events')->min('created_at');

            return [
                'users' => $current['users'],
                'totals' => $current['totals'],
                'previous' => $previous['totals'],
                'since' => $since !== null ? Carbon::parse($since)->format('d/m/Y') : null,
                'trend' => self::createdTrend($period),
            ];
        });
    }

    /**
     * Proveedores que cumplen un filtro del dashboard, para el detalle.
     *
     * @param  array{type?: ?string, status?: ?string, agreement?: ?string, geo?: ?string, letter?: ?string, class?: ?string, abroad?: ?string, active?: ?bool}  $filters
     * @return list<array<string, mixed>>
     */
    public static function detail(array $filters, int $limit = 300): array
    {
        $out = [];

        foreach (self::rows() as $row) {
            if (! self::matches($row, $filters)) {
                continue;
            }

            $out[] = $row;

            if (count($out) >= $limit) {
                break;
            }
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function count(array $filters): int
    {
        return count(array_filter(self::rows(), static fn (array $row): bool => self::matches($row, $filters)));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $filters
     */
    private static function matches(array $row, array $filters): bool
    {
        foreach (['type', 'status', 'agreement', 'geo', 'class', 'abroad'] as $key) {
            if (($filters[$key] ?? null) !== null && $row[$key] !== $filters[$key]) {
                return false;
            }
        }

        if (($filters['missing_location'] ?? null) === true && ($row['geo'] !== null || $row['abroad'] !== null)) {
            return false;
        }

        if (($filters['active'] ?? null) === true && ! ProviderNetworkCatalog::isActiveStatus($row['status'])) {
            return false;
        }

        if (($filters['preferential'] ?? null) === true && ! (ProviderNetworkCatalog::AGREEMENTS[$row['agreement']]['preferential'] ?? false)) {
            return false;
        }

        return match ($filters['letter'] ?? null) {
            'with' => $row['letter'] === true,
            'without' => $row['letter'] === false,
            'active_without' => $row['letter'] === false && ProviderNetworkCatalog::isActiveStatus($row['status']),
            default => true,
        };
    }

    /**
     * @return array{users: list<array<string, mixed>>, totals: array<string, int>}
     */
    private static function activityBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $users = [];
        $blank = ['created' => 0, 'created_juridico' => 0, 'created_natural' => 0, 'created_aliado' => 0, 'updated' => 0, 'status' => 0, 'won' => 0, 'agreement' => 0, 'letters' => 0, 'deleted' => 0];

        foreach (self::rows() as $row) {
            if ($row['created_at'] === null || ! Carbon::parse($row['created_at'])->betweenIncluded($from, $to)) {
                continue;
            }

            $name = $row['created_by'];
            $users[$name] ??= $blank;
            $users[$name]['created']++;
            $users[$name]['created_'.$row['type']]++;
        }

        $events = DB::table('provider_activity_events')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('action', [ProviderActivityLog::UPDATED, ProviderActivityLog::STATUS, ProviderActivityLog::AGREEMENT, ProviderActivityLog::LETTER, ProviderActivityLog::DELETED])
            ->select(['id', 'user_name', 'action', 'after'])
            ->lazyById(2000, 'id');

        foreach ($events as $event) {
            $name = trim((string) $event->user_name) ?: self::UNKNOWN_USER;
            $users[$name] ??= $blank;

            match ($event->action) {
                ProviderActivityLog::UPDATED => $users[$name]['updated']++,
                ProviderActivityLog::AGREEMENT => $users[$name]['agreement']++,
                ProviderActivityLog::LETTER => $users[$name]['letters']++,
                ProviderActivityLog::DELETED => $users[$name]['deleted']++,
                default => null,
            };

            if ($event->action === ProviderActivityLog::STATUS) {
                $users[$name]['status']++;
                $users[$name]['won'] += ProviderNetworkCatalog::isActiveStatus((string) $event->after) ? 1 : 0;
            }
        }

        $list = [];

        foreach ($users as $name => $values) {
            $list[] = ['user' => $name, ...$values, 'score' => $values['created'] + $values['updated'] + $values['status'] + $values['letters'] + $values['agreement']];
        }

        usort($list, static fn (array $a, array $b): int => [$b['score'], $a['user']] <=> [$a['score'], $b['user']]);

        $totals = $blank;
        foreach ($list as $line) {
            foreach (array_keys($blank) as $key) {
                $totals[$key] += $line[$key];
            }
        }

        return ['users' => $list, 'totals' => $totals];
    }

    /**
     * Altas por día, semana o mes del período, según su largo.
     *
     * @return array<string, int>
     */
    private static function createdTrend(OperationsDashboardPeriod $period): array
    {
        $format = match ($period->granularity()) {
            OperationsDashboardPeriod::GRANULARITY_MONTH => 'Y-m',
            OperationsDashboardPeriod::GRANULARITY_WEEK => 'o-\\WW',
            default => 'Y-m-d',
        };
        $buckets = [];

        for ($day = $period->from; $day->lte($period->to); $day = $day->addDay()) {
            $buckets[$day->format($format)] ??= 0;
        }

        foreach (self::rows() as $row) {
            if ($row['created_at'] === null) {
                continue;
            }

            $date = Carbon::parse($row['created_at']);

            if ($date->betweenIncluded($period->from->startOfDay(), $period->to->endOfDay())) {
                $key = $date->format($format);
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }
        }

        return $buckets;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array{class: string, geo: ?string, abroad: ?string}  $location
     * @return array<string, mixed>
     */
    private static function row(ProviderNetworkType $type, array $source, array $location): array
    {
        $status = ProviderNetworkCatalog::status($type, $source);
        $name = trim((string) ($source['name'] ?? '')) ?: trim((string) ($source['razon_social'] ?? '')) ?: 'Proveedor #'.$source['id'];

        return [
            'type' => $type->value,
            'id' => (int) $source['id'],
            'name' => $name,
            'status' => $status['key'],
            'status_raw' => $status['raw'],
            'agreement' => ProviderNetworkCatalog::agreement($type, $source),
            'class' => $location['class'],
            'geo' => $location['geo'],
            'abroad' => $location['abroad'],
            'letter' => ProviderNetworkCatalog::hasLetter($type, $source),
            'created_by' => trim((string) ($source['created_by'] ?? '')) ?: self::UNKNOWN_USER,
            'created_at' => $source['created_at'] !== null ? (string) $source['created_at'] : null,
        ];
    }

    /**
     * @param  array<string, array<string, int>>  $values
     * @param  list<string>  $order
     * @return array<string, array<string, int>>
     */
    private static function ordered(array $values, array $order): array
    {
        $out = [];

        foreach ($order as $key) {
            if (isset($values[$key])) {
                $out[$key] = $values[$key];
            }
        }

        return $out;
    }

    private static function remember(string $key, Closure $callback): mixed
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0);

        return Cache::remember('provider-network-dashboard:'.$version.':'.$key, self::CACHE_SECONDS, $callback);
    }
}
