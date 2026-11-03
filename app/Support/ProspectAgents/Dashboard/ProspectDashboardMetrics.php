<?php

declare(strict_types=1);

namespace App\Support\ProspectAgents\Dashboard;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\HelpDesk;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use App\Support\HelpdeskPlainText;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Operations\Dashboard\DashboardGrouping;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cifras del Dashboard de Captación. Los cortes del período cuentan prospectos por su
 * fecha de alta (`created_at`), como los gráficos de siempre; el embudo por estatus es
 * foto a hoy. Los prospectos eliminados (eliminación lógica) no cuentan en nada.
 *
 * Todo GROUP BY pasa por {@see DashboardGrouping}: producción rechaza agrupar por alias.
 */
final class ProspectDashboardMetrics
{
    public const CACHE_SECONDS = 60;

    public const NO_COLLABORATOR = 'Sin colaborador';

    public const NO_STATE = 'Sin estado';

    public const NO_CLASSIFICATION = 'Sin clasificación';

    public const NO_VALUE = 'Sin dato';

    public const NO_ASSIGNEE = 'Sin responsable';

    private const VERSION_KEY = 'prospect-dashboard:version';

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    /**
     * Altas del período, del anterior y su serie para la tarjeta.
     *
     * @return array{current: int, previous: int, sparkline: list<int>}
     */
    public static function registrations(Period $period): array
    {
        return self::remember('registrations', $period, static function () use ($period): array {
            $trend = self::registrationsTrend($period);

            return [
                'current' => (int) array_sum($trend['current']),
                'previous' => (int) array_sum($trend['previous']),
                'sparkline' => array_map('intval', $trend['current']),
            ];
        });
    }

    /**
     * @return array{labels: list<string>, current: list<int|float>, previous: list<int|float>, previous_labels: list<string>}
     */
    public static function registrationsTrend(Period $period): array
    {
        return self::remember('registrations_trend', $period, static fn (): array => CorporateMetrics::trend(
            $period,
            static fn (CarbonImmutable $from, CarbonImmutable $to): array => CorporateMetrics::daily(
                self::createdBetween($from, $to)->toBase(),
                'prospect_agents.created_at',
                $from,
                $to,
            ),
        ));
    }

    /**
     * Embudo a hoy: total y los cinco estatus (los valores viejos se suman a su estatus actual).
     *
     * @return array{total: int, statuses: array<string, int>}
     */
    public static function funnelSnapshot(): array
    {
        return self::remember('funnel_snapshot', null, static function (): array {
            $raw = DashboardGrouping::by(
                ProspectAgent::query()->toBase(),
                ['grp_status' => "COALESCE(NULLIF(TRIM(prospect_agents.status), ''), '')"],
            )->selectRaw('COUNT(*) as total')->pluck('total', 'grp_status');

            $statuses = array_fill_keys(array_keys(ProspectAgentLabels::statusOptions()), 0);
            $total = 0;

            foreach ($raw as $status => $count) {
                $total += (int) $count;
                /** MySQL compara sin mayúsculas: «Captación» contaba como «captación». Aquí también. */
                $canonical = ProspectAgentLabels::normalizeStatus(mb_strtolower(trim((string) $status)));

                if ($canonical !== null && array_key_exists($canonical, $statuses)) {
                    $statuses[$canonical] += (int) $count;
                }
            }

            return ['total' => $total, 'statuses' => $statuses];
        });
    }

    /**
     * Prospectos registrados por colaborador (quien creó el registro).
     *
     * @return array{rows: list<array{label: string, value: int, key: string, meta?: string}>, others: int, total: int}
     */
    public static function byCollaborator(Period $period, int $limit = 10): array
    {
        return self::remember('collaborator_'.$limit, $period, static function () use ($period, $limit): array {
            $counts = self::countBy($period, "COALESCE(NULLIF(TRIM(prospect_agents.created_by), ''), '".self::NO_COLLABORATOR."')");

            return self::rankedRows($counts, $limit);
        });
    }

    /**
     * @return array<string, int>
     */
    public static function byState(Period $period): array
    {
        return self::remember('state', $period, static function () use ($period): array {
            $query = self::createdBetween($period->from, $period->to)
                ->toBase()
                ->leftJoin('states', 'states.id', '=', 'prospect_agents.state_id');

            return self::sorted(self::groupCount($query, "COALESCE(NULLIF(TRIM(states.definition), ''), '".self::NO_STATE."')"));
        });
    }

    /**
     * @return array<string, int>
     */
    public static function byType(Period $period): array
    {
        return self::remember('type', $period, static function () use ($period): array {
            return self::labelled(
                self::countBy($period, "COALESCE(NULLIF(TRIM(prospect_agents.type), ''), '')"),
                static fn (string $key): string => $key === '' ? self::NO_VALUE : ProspectAgentLabels::typeLabel($key),
            );
        });
    }

    /**
     * @return array<string, int>
     */
    public static function byReference(Period $period): array
    {
        return self::remember('reference', $period, static function () use ($period): array {
            return self::labelled(
                self::countBy($period, "COALESCE(NULLIF(TRIM(prospect_agents.reference_by), ''), '')"),
                static fn (string $key): string => $key === '' ? self::NO_VALUE : ProspectAgentLabels::referenceLabel($key),
            );
        });
    }

    /**
     * Las primeras `$limit` clasificaciones (texto libre).
     *
     * @return array<string, int>
     */
    public static function byClassification(Period $period, int $limit = 15): array
    {
        return self::remember('classification_'.$limit, $period, static function () use ($period, $limit): array {
            $counts = self::sorted(self::countBy($period, "COALESCE(NULLIF(TRIM(prospect_agents.classification), ''), '".self::NO_CLASSIFICATION."')"));

            return array_slice($counts, 0, $limit, true);
        });
    }

    /**
     * Respuesta de cada responsable: tareas y tickets de trabajo (Helpdesk) que se le
     * asignaron en el período y cuántos de ellos ya resolvió (tarea RESUELTA o ticket
     * TERMINADO). Los tickets cancelados o revertidos no cuentan: no hay nada que responder.
     *
     * @return array{rows: list<array{key: string, label: string, assigned: int, resolved: int, pending: int, rate: float}>, totals: array{assigned: int, resolved: int}}
     */
    public static function assignmentsByColaborador(Period $period, int $limit = 12): array
    {
        return self::remember('assignments_'.$limit, $period, static function () use ($period, $limit): array {
            [$from, $to] = CorporateMetrics::range($period->from, $period->to);
            $counts = [];

            $tasks = DashboardGrouping::by(
                self::liveTasks()->toBase()->whereBetween('prospect_agent_tasks.created_at', [$from, $to]),
                ['grp_key' => 'COALESCE(prospect_agent_tasks.rrhh_colaborador_id, 0)'],
                ['row_resolved' => "CASE WHEN UPPER(TRIM(prospect_agent_tasks.status)) = 'RESUELTA' THEN 1 ELSE 0 END"],
            )->selectRaw('COUNT(*) as total, COALESCE(SUM(g.row_resolved), 0) as resolved')->get();

            $tickets = DashboardGrouping::by(
                DB::table('help_desk_rrhh_colaborador')
                    ->join('help_desks', 'help_desks.id', '=', 'help_desk_rrhh_colaborador.help_desk_id')
                    ->whereIn('help_desks.id', self::prospectTickets()->select('help_desks.id'))
                    ->whereNotIn('help_desks.status', [HelpdeskTaskStatusOptions::STATUS_CANCELLED, HelpdeskTaskStatusOptions::STATUS_REVERTED])
                    ->whereBetween('help_desks.created_at', [$from, $to]),
                ['grp_key' => 'help_desk_rrhh_colaborador.rrhh_colaborador_id'],
                ['row_resolved' => "CASE WHEN help_desks.status = '".HelpdeskTaskStatusOptions::STATUS_DONE."' THEN 1 ELSE 0 END"],
            )->selectRaw('COUNT(*) as total, COALESCE(SUM(g.row_resolved), 0) as resolved')->get();

            foreach ([...$tasks, ...$tickets] as $row) {
                $key = (string) (int) $row->grp_key;
                $counts[$key]['assigned'] = ($counts[$key]['assigned'] ?? 0) + (int) $row->total;
                $counts[$key]['resolved'] = ($counts[$key]['resolved'] ?? 0) + (int) $row->resolved;
            }

            $names = RrhhColaborador::query()
                ->whereKey(array_map('intval', array_keys($counts)))
                ->pluck('fullName', 'id')
                ->all();

            $rows = [];

            foreach ($counts as $key => $count) {
                /** PHP vuelve entero una clave numérica: la clave del detalle viaja como texto. */
                $key = (string) $key;
                $assigned = (int) $count['assigned'];
                $resolved = min($assigned, (int) $count['resolved']);

                $rows[] = [
                    'key' => $key,
                    'label' => $key === '0' ? self::NO_ASSIGNEE : (trim((string) ($names[(int) $key] ?? '')) ?: 'Colaborador #'.$key),
                    'assigned' => $assigned,
                    'resolved' => $resolved,
                    'pending' => $assigned - $resolved,
                    'rate' => $assigned > 0 ? round($resolved * 100 / $assigned, 1) : 0.0,
                ];
            }

            usort($rows, static fn (array $a, array $b): int => ($b['assigned'] <=> $a['assigned']) ?: ($b['pending'] <=> $a['pending']) ?: strcmp($a['label'], $b['label']));

            return [
                'rows' => array_slice($rows, 0, $limit),
                'totals' => [
                    'assigned' => (int) array_sum(array_column($rows, 'assigned')),
                    'resolved' => (int) array_sum(array_column($rows, 'resolved')),
                ],
            ];
        });
    }

    /**
     * @return list<string>
     */
    public static function detailDimensions(): array
    {
        return ['collaborator', 'state', 'type', 'reference', 'classification', 'assignee'];
    }

    public static function detailLabel(string $dimension, string $value): string
    {
        return match ($dimension) {
            'collaborator' => 'Registrados por '.$value,
            'state' => 'Estado: '.$value,
            'type' => 'Tipo: '.$value,
            'reference' => 'Referido por: '.$value,
            'classification' => 'Clasificación: '.$value,
            'assignee' => 'Asignado a '.self::assigneeName($value),
            default => $value,
        };
    }

    /**
     * @return list<array{label: string, key: string}>
     */
    public static function detailColumns(string $dimension): array
    {
        if ($dimension === 'assignee') {
            return [
                ['label' => 'Asignado', 'key' => 'date'],
                ['label' => 'Prospecto', 'key' => 'case_code'],
                ['label' => 'Qué es', 'key' => 'kind'],
                ['label' => 'Actividad', 'key' => 'task'],
                ['label' => 'Estatus', 'key' => 'status'],
                ['label' => 'Resuelto', 'key' => 'resolved'],
            ];
        }

        return [
            ['label' => 'Alta', 'key' => 'date'],
            ['label' => 'Prospecto', 'key' => 'case_code'],
            ['label' => 'Tipo', 'key' => 'type'],
            ['label' => 'Estatus', 'key' => 'status'],
            ['label' => 'Estado', 'key' => 'place'],
            ['label' => 'Registrado por', 'key' => 'created_by'],
        ];
    }

    /**
     * Prospectos (o tareas) detrás de una barra o fila del dashboard.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function detail(Period $period, string $dimension, string $value, int $limit = 200): array
    {
        if ($dimension === 'assignee') {
            return self::assigneeDetail($period, $value, $limit);
        }

        $query = self::createdBetween($period->from, $period->to);

        match ($dimension) {
            'collaborator' => $value === self::NO_COLLABORATOR
                ? $query->where(fn (Builder $q): Builder => $q->whereNull('prospect_agents.created_by')->orWhereRaw("TRIM(prospect_agents.created_by) = ''"))
                : $query->whereRaw('TRIM(prospect_agents.created_by) = ?', [$value]),
            'state' => $value === self::NO_STATE
                ? $query->where(fn (Builder $q): Builder => $q->whereNull('prospect_agents.state_id')->orWhereDoesntHave('state'))
                : $query->whereHas('state', fn (Builder $q): Builder => $q->whereRaw('TRIM(definition) = ?', [$value])),
            'type' => self::whereLabelled($query, 'type', $value, static fn (string $key): string => $key === '' ? self::NO_VALUE : ProspectAgentLabels::typeLabel($key)),
            'reference' => self::whereLabelled($query, 'reference_by', $value, static fn (string $key): string => $key === '' ? self::NO_VALUE : ProspectAgentLabels::referenceLabel($key)),
            'classification' => $value === self::NO_CLASSIFICATION
                ? $query->where(fn (Builder $q): Builder => $q->whereNull('prospect_agents.classification')->orWhereRaw("TRIM(prospect_agents.classification) = ''"))
                : $query->whereRaw('TRIM(prospect_agents.classification) = ?', [$value]),
            default => $query->whereRaw('1 = 0'),
        };

        $total = (clone $query)->count();

        $rows = $query->with('state:id,definition')
            ->orderByDesc('prospect_agents.created_at')
            ->limit($limit)
            ->get(['prospect_agents.id', 'prospect_agents.name', 'prospect_agents.type', 'prospect_agents.status', 'prospect_agents.state_id', 'prospect_agents.created_by', 'prospect_agents.created_at'])
            ->map(static fn (ProspectAgent $prospect): array => [
                'id' => 'p'.$prospect->getKey(),
                'date' => $prospect->created_at?->format('d/m/Y'),
                'case_id' => (int) $prospect->getKey(),
                'case_code' => trim((string) $prospect->name) ?: 'Prospecto #'.$prospect->getKey(),
                'type' => ProspectAgentLabels::typeLabel($prospect->type),
                'status' => ProspectAgentLabels::statusLabel(ProspectAgentLabels::normalizeStatus(mb_strtolower(trim((string) $prospect->status)))),
                'place' => $prospect->state?->definition,
                'created_by' => $prospect->created_by,
            ])
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    public static function assigneeName(string $key): string
    {
        if ($key === '0') {
            return self::NO_ASSIGNEE;
        }

        return trim((string) RrhhColaborador::query()->whereKey((int) $key)->value('fullName')) ?: 'Colaborador #'.$key;
    }

    /**
     * Tareas y tickets de trabajo asignados a un colaborador en el período (los mismos que
     * cuenta {@see self::assignmentsByColaborador()}), con su estado de resolución.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private static function assigneeDetail(Period $period, string $key, int $limit): array
    {
        [$from, $to] = CorporateMetrics::range($period->from, $period->to);
        $colaboradorId = is_numeric($key) ? (int) $key : -1;

        $tasks = self::liveTasks()
            ->whereBetween('prospect_agent_tasks.created_at', [$from, $to])
            ->when(
                $colaboradorId === 0,
                fn (Builder $q): Builder => $q->whereNull('prospect_agent_tasks.rrhh_colaborador_id'),
                fn (Builder $q): Builder => $q->where('prospect_agent_tasks.rrhh_colaborador_id', $colaboradorId),
            );

        $tickets = self::prospectTickets()
            ->whereNotIn('help_desks.status', [HelpdeskTaskStatusOptions::STATUS_CANCELLED, HelpdeskTaskStatusOptions::STATUS_REVERTED])
            ->whereBetween('help_desks.created_at', [$from, $to])
            ->whereHas('rrhhColaboradores', fn (Builder $q): Builder => $q->where('rrhh_colaboradors.id', $colaboradorId));

        $total = (clone $tasks)->count() + (clone $tickets)->count();

        $taskRows = $tasks->with('prospect_agent:id,name')
            ->orderByDesc('prospect_agent_tasks.created_at')
            ->limit($limit)
            ->get()
            ->map(static fn (ProspectAgentTask $task): array => [
                'id' => 't'.$task->getKey(),
                'sort' => $task->created_at?->timestamp ?? 0,
                'date' => $task->created_at?->format('d/m/Y'),
                'case_id' => $task->prospect_agent_id !== null ? (int) $task->prospect_agent_id : null,
                'case_code' => trim((string) $task->prospect_agent?->name) ?: 'Prospecto #'.$task->prospect_agent_id,
                'kind' => 'Tarea N.º '.$task->getKey(),
                'task' => $task->task,
                'status' => $task->status,
                'resolved' => mb_strtoupper(trim((string) $task->status)) === 'RESUELTA' ? 'Sí' : 'No',
            ])
            ->all();

        $ticketRows = $tickets->with('prospectAgents:id,name')
            ->orderByDesc('help_desks.created_at')
            ->limit($limit)
            ->get()
            ->map(static function (HelpDesk $ticket): array {
                $prospects = $ticket->prospectAgents;
                $first = $prospects->first();

                return [
                    'id' => 'h'.$ticket->getKey(),
                    'sort' => $ticket->created_at?->timestamp ?? 0,
                    'date' => $ticket->created_at?->format('d/m/Y'),
                    'case_id' => $prospects->count() === 1 && $first !== null ? (int) $first->getKey() : null,
                    'case_code' => $prospects->count() === 1 && $first !== null
                        ? (trim((string) $first->name) ?: 'Prospecto #'.$first->getKey())
                        : $prospects->count().' prospectos',
                    'kind' => 'Ticket Helpdesk N.º '.$ticket->getKey(),
                    'task' => trim((string) preg_replace('/\s+/u', ' ', HelpdeskPlainText::fromHtml((string) preg_replace('#<(/p|/li|br\s*/?)>#i', ' $0', (string) $ticket->description)))),
                    'status' => $ticket->status,
                    'resolved' => $ticket->status === HelpdeskTaskStatusOptions::STATUS_DONE ? 'Sí' : 'No',
                ];
            })
            ->all();

        $rows = collect([...$taskRows, ...$ticketRows])
            ->sortByDesc('sort')
            ->take($limit)
            ->map(static function (array $row): array {
                unset($row['sort']);

                return $row;
            })
            ->values()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Filtra por los valores guardados cuya etiqueta visible es `$label` (puede haber varios: claves viejas).
     *
     * @param  Builder<ProspectAgent>  $query
     * @param  Closure(string): string  $labelFor
     */
    private static function whereLabelled(Builder $query, string $column, string $label, Closure $labelFor): Builder
    {
        $raw = ProspectAgent::query()
            ->toBase()
            ->distinct()
            ->pluck($column)
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->unique()
            ->filter(static fn (string $value): bool => $labelFor($value) === $label)
            ->values()
            ->all();

        if ($raw === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($column, $raw): void {
            $nonEmpty = array_values(array_filter($raw, static fn (string $value): bool => $value !== ''));

            if ($nonEmpty !== []) {
                $q->orWhereIn(DB::raw('TRIM(prospect_agents.'.$column.')'), $nonEmpty);
            }

            if (in_array('', $raw, true)) {
                $q->orWhereNull('prospect_agents.'.$column)->orWhereRaw('TRIM(prospect_agents.'.$column.") = ''");
            }
        });
    }

    /**
     * Tickets del Helpdesk creados con «Ticket de trabajo» sobre algún prospecto vivo.
     *
     * @return Builder<HelpDesk>
     */
    public static function prospectTickets(): Builder
    {
        return HelpDesk::query()->whereIn(
            'help_desks.id',
            DB::table('help_desk_prospect_agent')
                ->join('prospect_agents', 'prospect_agents.id', '=', 'help_desk_prospect_agent.prospect_agent_id')
                ->whereNull('prospect_agents.deleted_at')
                ->select('help_desk_prospect_agent.help_desk_id'),
        );
    }

    /**
     * Prospectos dados de alta en el rango (sin los eliminados: el scope de SoftDeletes ya los quita).
     *
     * @return Builder<ProspectAgent>
     */
    public static function createdBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return ProspectAgent::query()->whereBetween('prospect_agents.created_at', CorporateMetrics::range($from, $to));
    }

    /**
     * Tareas de prospectos que siguen vivos (no eliminados).
     *
     * @return Builder<ProspectAgentTask>
     */
    public static function liveTasks(): Builder
    {
        return ProspectAgentTask::query()
            ->whereIn('prospect_agent_tasks.prospect_agent_id', ProspectAgent::query()->select('prospect_agents.id'));
    }

    /**
     * @return array<string, int>
     */
    private static function countBy(Period $period, string $expression): array
    {
        return self::groupCount(self::createdBetween($period->from, $period->to)->toBase(), $expression);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array<string, int>
     */
    private static function groupCount($query, string $expression): array
    {
        return DashboardGrouping::by($query, ['grp_key' => $expression])
            ->selectRaw('COUNT(*) as total')
            ->pluck('total', 'grp_key')
            ->mapWithKeys(static fn (mixed $total, mixed $key): array => [(string) $key => (int) $total])
            ->all();
    }

    /**
     * Pasa claves internas a su etiqueta visible, sumando las que coinciden, de mayor a menor.
     *
     * @param  array<string, int>  $counts
     * @param  Closure(string): string  $label
     * @return array<string, int>
     */
    private static function labelled(array $counts, Closure $label): array
    {
        $out = [];

        foreach ($counts as $key => $count) {
            $name = $label((string) $key);
            $out[$name] = ($out[$name] ?? 0) + $count;
        }

        return self::sorted($out);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private static function sorted(array $counts): array
    {
        uksort($counts, static fn (string $a, string $b): int => ($counts[$b] <=> $counts[$a]) ?: strcmp($a, $b));

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{rows: list<array{label: string, value: int, key: string}>, others: int, total: int}
     */
    private static function rankedRows(array $counts, int $limit): array
    {
        $sorted = self::sorted($counts);
        $rows = [];

        foreach (array_slice($sorted, 0, $limit, true) as $label => $value) {
            $rows[] = ['label' => (string) $label, 'value' => $value, 'key' => (string) $label];
        }

        return [
            'rows' => $rows,
            'others' => (int) array_sum(array_slice($sorted, $limit, null, true)),
            'total' => (int) array_sum($sorted),
        ];
    }

    private static function remember(string $metric, ?Period $period, Closure $callback): mixed
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0);

        return Cache::remember(
            'prospect-dashboard:'.$version.':'.$metric.':'.($period?->cacheKey() ?? 'snapshot'),
            self::CACHE_SECONDS,
            $callback,
        );
    }
}
