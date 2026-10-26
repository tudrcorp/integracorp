<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Detalle de cualquier corte del panel corporativo: al hacer clic en una barra,
 * fila, segmento o estado se listan los registros que lo componen.
 *
 * Cada dimensión pertenece a una familia (ventas, cuotas, órdenes, afiliaciones,
 * cotizaciones, tickets, personas) que define la consulta y las columnas.
 */
final class CorporateDetail
{
    /** @var array<string, string> dimensión => familia */
    public const DIMENSIONS = [
        'sale_type' => 'sales',
        'sale_plan' => 'sales',
        'sale_agency' => 'sales',
        'sale_agent' => 'sales',
        'sale_state' => 'sales',
        'sale_white_company' => 'sales',
        'receivable_aging' => 'receivables',
        'receivable_agency' => 'receivables',
        'cost_provider' => 'costs',
        'portfolio_status' => 'affiliations',
        'quote_funnel' => 'quotes',
        'ticket_status' => 'tickets',
        'ticket_priority' => 'tickets',
        'ticket_type' => 'tickets',
        'user_person' => 'person_days',
        'user_department' => 'people',
    ];

    /** @var array<string, string> familia => qué lista (plural) */
    public const NOUNS = [
        'sales' => 'pagos',
        'receivables' => 'cuotas pendientes',
        'costs' => 'órdenes',
        'affiliations' => 'afiliaciones',
        'quotes' => 'cotizaciones',
        'tickets' => 'tickets',
        'person_days' => 'días con conexión',
        'people' => 'personas',
    ];

    /**
     * @return list<string>
     */
    public static function dimensions(): array
    {
        return array_keys(self::DIMENSIONS);
    }

    public static function family(string $dimension): string
    {
        return self::DIMENSIONS[$dimension] ?? 'sales';
    }

    public static function noun(string $dimension): string
    {
        return self::NOUNS[self::family($dimension)];
    }

    /**
     * @return list<array{label: string, key: string}>
     */
    public static function columns(string $dimension): array
    {
        return match (self::family($dimension)) {
            'sales' => [
                ['label' => 'Fecha', 'key' => 'date'],
                ['label' => 'Afiliación', 'key' => 'code'],
                ['label' => 'Titular / empresa', 'key' => 'name'],
                ['label' => 'Tipo', 'key' => 'type'],
                ['label' => 'Agencia', 'key' => 'agency'],
                ['label' => 'Recibo', 'key' => 'reference'],
                ['label' => 'Monto', 'key' => 'amount'],
            ],
            'receivables' => [
                ['label' => 'Fecha de pago', 'key' => 'date'],
                ['label' => 'Afiliación', 'key' => 'code'],
                ['label' => 'Titular / empresa', 'key' => 'name'],
                ['label' => 'Agencia', 'key' => 'agency'],
                ['label' => 'Frecuencia', 'key' => 'type'],
                ['label' => 'Atraso', 'key' => 'reference'],
                ['label' => 'Monto', 'key' => 'amount'],
            ],
            'costs' => [
                ['label' => 'Fecha', 'key' => 'date'],
                ['label' => 'Orden', 'key' => 'code'],
                ['label' => 'Proveedor', 'key' => 'name'],
                ['label' => 'Servicio', 'key' => 'type'],
                ['label' => 'Factura', 'key' => 'reference'],
                ['label' => 'Costo', 'key' => 'amount'],
            ],
            'affiliations' => [
                ['label' => 'Emisión', 'key' => 'date'],
                ['label' => 'Afiliación', 'key' => 'code'],
                ['label' => 'Titular / empresa', 'key' => 'name'],
                ['label' => 'Tipo', 'key' => 'type'],
                ['label' => 'Agencia', 'key' => 'agency'],
                ['label' => 'Estatus', 'key' => 'status'],
                ['label' => 'Monto anual', 'key' => 'amount'],
            ],
            'quotes' => [
                ['label' => 'Fecha', 'key' => 'date'],
                ['label' => 'Cotización', 'key' => 'code'],
                ['label' => 'Cliente', 'key' => 'name'],
                ['label' => 'Agencia', 'key' => 'agency'],
                ['label' => 'Estatus', 'key' => 'status'],
            ],
            'person_days' => [
                ['label' => 'Fecha', 'key' => 'date'],
                ['label' => 'Primera conexión', 'key' => 'first'],
                ['label' => 'Última conexión', 'key' => 'last'],
                ['label' => 'Activo', 'key' => 'active'],
                ['label' => 'Inactivo', 'key' => 'idle'],
                ['label' => 'Uso de jornada', 'key' => 'work_usage'],
                ['label' => 'Horas extra', 'key' => 'overtime'],
                ['label' => 'Registros creados', 'key' => 'records'],
            ],
            'people' => [
                ['label' => 'Persona', 'key' => 'name'],
                ['label' => 'Días conectado', 'key' => 'days'],
                ['label' => 'Horas activas', 'key' => 'active'],
                ['label' => 'Promedio por día', 'key' => 'avg_day'],
                ['label' => 'Uso de jornada', 'key' => 'work_usage'],
                ['label' => 'Horas extra', 'key' => 'overtime'],
                ['label' => 'Registros creados', 'key' => 'records'],
            ],
            default => [
                ['label' => 'Fecha', 'key' => 'date'],
                ['label' => 'Ticket', 'key' => 'code'],
                ['label' => 'Descripción', 'key' => 'name'],
                ['label' => 'Prioridad', 'key' => 'type'],
                ['label' => 'Creado por', 'key' => 'agency'],
                ['label' => 'Estatus', 'key' => 'status'],
            ],
        };
    }

    public static function label(string $dimension, string $value): string
    {
        return match ($dimension) {
            'sale_type' => 'Ingresos · '.$value,
            'sale_plan' => 'Ingresos · '.$value,
            'sale_agency' => 'Ingresos de la agencia '.$value,
            'sale_agent' => 'Ingresos del agente · '.((int) $value === 0 ? 'venta directa' : (DB::table('agents')->where('id', (int) $value)->value('name') ?? '#'.$value)),
            'sale_state' => 'Ingresos · '.$value,
            'sale_white_company' => 'Ingresos · '.(DB::table('white_companies')->where('id', (int) $value)->value('name') ?? 'empresa aliada'),
            'receivable_aging' => 'Cuotas vencidas · '.$value,
            'receivable_agency' => 'Cuotas pendientes de la agencia '.$value,
            'cost_provider' => 'Costo médico · '.((int) $value === 0 ? 'sin proveedor' : (DB::table('suppliers')->where('id', (int) $value)->value('name') ?? 'proveedor')),
            'portfolio_status' => 'Afiliaciones · '.$value,
            'quote_funnel' => 'Cotizaciones · '.match ($value) {
                'all' => 'emitidas',
                'advanced' => 'aprobadas o en curso',
                default => 'convertidas en afiliación',
            },
            'ticket_status' => 'Tickets · '.$value,
            'ticket_priority' => 'Tickets de prioridad '.mb_strtolower($value),
            'ticket_type' => 'Tickets · '.$value,
            'user_person' => 'Actividad de '.(CorporateUsersMetrics::directory()[(int) $value]['name'] ?? 'la persona'),
            'user_department' => 'Personas del área '.$value,
            default => 'Detalle',
        };
    }

    /**
     * Si el corte depende del período (las fotos a hoy no).
     */
    public static function isSnapshot(string $dimension): bool
    {
        return in_array(self::family($dimension), ['receivables'], true) || $dimension === 'portfolio_status';
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function fetch(Period $period, string $dimension, string $value, int $limit = 200): array
    {
        if (! array_key_exists($dimension, self::DIMENSIONS)) {
            return ['rows' => [], 'total' => 0];
        }

        return match (self::family($dimension)) {
            'sales' => self::sales($period, $dimension, $value, $limit),
            'receivables' => self::receivables($dimension, $value, $limit),
            'costs' => self::costs($period, $value, $limit),
            'affiliations' => self::affiliations($value, $limit),
            'quotes' => self::quotes($period, $value, $limit),
            'person_days' => CorporateUsersMetrics::personDays($period, (int) $value, $limit),
            'people' => CorporateUsersMetrics::departmentPeople($period, $value, $limit),
            default => self::tickets($period, $dimension, $value, $limit),
        };
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private static function sales(Period $period, string $dimension, string $value, int $limit): array
    {
        $query = CorporateFinanceMetrics::salesBetween($period->from, $period->to);

        match ($dimension) {
            'sale_type' => $query->whereRaw(CorporateFinanceMetrics::saleTypeSql().' = ?', [$value]),
            'sale_plan' => self::filterPlan($query, $value),
            'sale_agency' => $value === 'SIN AGENCIA'
                ? $query->where(static fn (Builder $q) => $q->whereNull('sales.code_agency')->orWhereRaw("TRIM(sales.code_agency) = ''"))
                : $query->whereRaw('TRIM(sales.code_agency) = ?', [$value]),
            'sale_agent' => (int) $value === 0 ? $query->whereNull('sales.agent_id') : $query->where('sales.agent_id', (int) $value),
            'sale_white_company' => $query->whereIn(DB::raw('UPPER(TRIM(sales.code_agency))'), CorporateFinanceMetrics::whiteCompanyAgencyCodes((int) $value) ?: ['__ninguna__']),
            'sale_state' => self::filterSaleState($query, $value),
            default => null,
        };

        return self::page($query, $limit, 'sales.created_at', [
            'sales.id', 'sales.created_at', 'sales.affiliation_code', 'sales.affiliate_full_name', 'sales.type', 'sales.code_agency', 'sales.invoice_number', 'sales.total_amount',
        ], static fn (object $row): array => [
            'id' => 's'.$row->id,
            'date' => self::date($row->created_at),
            'code' => $row->affiliation_code,
            'name' => $row->affiliate_full_name,
            'type' => $row->type,
            'agency' => $row->code_agency,
            'reference' => $row->invoice_number,
            'amount' => CorporateMetrics::money((float) $row->total_amount, 2),
        ]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private static function receivables(string $dimension, string $value, int $limit): array
    {
        $today = CarbonImmutable::today();
        $query = CorporateFinanceMetrics::pendingCollectableQuery()->toBase();

        if ($dimension === 'receivable_aging') {
            $bounds = CorporateFinanceMetrics::AGING[$value] ?? null;

            if ($bounds === null) {
                return ['rows' => [], 'total' => 0];
            }

            [$condition, $bindings] = CorporateFinanceMetrics::agingCondition($today, $bounds[0], $bounds[1]);
            $query->whereRaw($condition, $bindings);
        } else {
            $value === 'SIN AGENCIA'
                ? $query->where(static fn (Builder $q) => $q->whereNull('collections.code_agency')->orWhereRaw("TRIM(collections.code_agency) = ''"))
                : $query->whereRaw('TRIM(collections.code_agency) = ?', [$value]);
        }

        return self::page($query, $limit, 'collections.filter_next_payment_date', [
            'collections.id', 'collections.filter_next_payment_date', 'collections.affiliation_code', 'collections.affiliate_full_name', 'collections.code_agency', 'collections.payment_frequency', 'collections.total_amount',
        ], static function (object $row) use ($today): array {
            $due = $row->filter_next_payment_date ? CarbonImmutable::parse($row->filter_next_payment_date) : null;
            $late = $due !== null && $due->lessThan($today) ? (int) $due->diffInDays($today) : 0;

            return [
                'id' => 'c'.$row->id,
                'date' => $due?->format('d/m/Y'),
                'code' => $row->affiliation_code,
                'name' => $row->affiliate_full_name,
                'agency' => $row->code_agency,
                'type' => $row->payment_frequency,
                'reference' => $late > 0 ? $late.' '.($late === 1 ? 'día' : 'días') : 'Al día',
                'amount' => CorporateMetrics::money((float) $row->total_amount, 2),
            ];
        }, ascending: true);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private static function costs(Period $period, string $value, int $limit): array
    {
        $query = CorporateFinanceMetrics::costOrdersBetween($period->from, $period->to)
            ->leftJoin('suppliers', 'suppliers.id', '=', 'operation_service_orders.supplier_id');

        (int) $value === 0
            ? $query->whereNull('operation_service_orders.supplier_id')
            : $query->where('operation_service_orders.supplier_id', (int) $value);

        return self::page($query, $limit, 'operation_service_orders.created_at', [
            'operation_service_orders.id', 'operation_service_orders.created_at', 'operation_service_orders.order_number', 'operation_service_orders.service_type',
            'operation_service_orders.invoice_number', 'operation_service_orders.supplier_external', 'suppliers.name as supplier_name',
            DB::raw(CorporateFinanceMetrics::COST_SQL.' as cost'),
        ], static fn (object $row): array => [
            'id' => 'o'.$row->id,
            'date' => self::date($row->created_at),
            'code' => $row->order_number,
            'name' => $row->supplier_name ?? $row->supplier_external,
            'type' => $row->service_type,
            'reference' => $row->invoice_number,
            'amount' => CorporateMetrics::money((float) $row->cost, 2),
        ]);
    }

    /**
     * Afiliaciones (individuales y corporativas) con un estatus, a hoy.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private static function affiliations(string $value, int $limit): array
    {
        $status = static fn (Builder $q, string $table) => $value === 'SIN ESTATUS'
            ? $q->where(static fn (Builder $w) => $w->whereNull("{$table}.status")->orWhereRaw("TRIM({$table}.status) = ''"))
            : $q->whereRaw("UPPER(TRIM({$table}.status)) = ?", [$value]);

        $individual = $status(DB::table('affiliations'), 'affiliations')
            ->selectRaw("CONCAT('a', affiliations.id) as row_id, affiliations.created_at, affiliations.code, affiliations.full_name_ti as name, 'Individual' as kind, affiliations.code_agency, affiliations.status, affiliations.total_amount");
        $corporate = $status(DB::table('affiliation_corporates'), 'affiliation_corporates')
            ->selectRaw("CONCAT('c', affiliation_corporates.id) as row_id, affiliation_corporates.created_at, affiliation_corporates.code, affiliation_corporates.name_corporate as name, 'Corporativa' as kind, affiliation_corporates.code_agency, affiliation_corporates.status, affiliation_corporates.total_amount");

        $total = (clone $individual)->count() + (clone $corporate)->count();
        $rows = DB::query()->fromSub($individual->unionAll($corporate), 'portfolio')->orderByDesc('created_at')->limit($limit)->get();

        return [
            'rows' => $rows->map(static fn (object $row): array => [
                'id' => $row->row_id,
                'date' => self::date($row->created_at),
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->kind,
                'agency' => $row->code_agency,
                'status' => $row->status,
                'amount' => CorporateMetrics::money((float) $row->total_amount, 2),
            ])->all(),
            'total' => $total,
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private static function quotes(Period $period, string $value, int $limit): array
    {
        $query = CorporateCommercialMetrics::quotesBetween('individual_quotes', $period->from, $period->to);

        if ($value === 'advanced') {
            $query->whereIn(DB::raw('UPPER(TRIM(individual_quotes.status))'), CorporateCommercialMetrics::ADVANCED);
        } elseif ($value !== 'all') {
            $query->whereRaw('UPPER(TRIM(individual_quotes.status)) = ?', [CorporateCommercialMetrics::EXECUTED]);
        }

        return self::page($query, $limit, 'individual_quotes.created_at', [
            'individual_quotes.id', 'individual_quotes.created_at', 'individual_quotes.code', 'individual_quotes.full_name', 'individual_quotes.code_agency', 'individual_quotes.status',
        ], static fn (object $row): array => [
            'id' => 'q'.$row->id,
            'date' => self::date($row->created_at),
            'code' => $row->code,
            'name' => $row->full_name,
            'agency' => $row->code_agency,
            'status' => $row->status,
        ]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private static function tickets(Period $period, string $dimension, string $value, int $limit): array
    {
        $query = CorporateOrganizationMetrics::createdBetween($period->from, $period->to);

        match ($dimension) {
            'ticket_status' => $query->whereRaw("COALESCE(NULLIF(UPPER(TRIM(help_desks.status)), ''), 'SIN ESTATUS') = ?", [$value]),
            'ticket_priority' => $query->whereRaw("COALESCE(NULLIF(UPPER(TRIM(help_desks.priority)), ''), 'SIN PRIORIDAD') = ?", [$value]),
            default => ($type = array_search($value, CorporateOrganizationMetrics::TYPES, true)) !== false
                ? $query->where('help_desks.ticket_type', $type)
                : $query->where(static fn (Builder $q) => $q->whereNull('help_desks.ticket_type')->orWhereNotIn('help_desks.ticket_type', array_keys(CorporateOrganizationMetrics::TYPES))),
        };

        return self::page($query, $limit, 'help_desks.created_at', [
            'help_desks.id', 'help_desks.created_at', 'help_desks.uid', 'help_desks.description', 'help_desks.priority', 'help_desks.created_by', 'help_desks.status',
        ], static fn (object $row): array => [
            'id' => 't'.$row->id,
            'date' => self::date($row->created_at),
            'code' => $row->uid ?? '#'.$row->id,
            'name' => mb_strimwidth(trim(strip_tags((string) $row->description)), 0, 120, '…'),
            'type' => $row->priority,
            'agency' => $row->created_by,
            'status' => $row->status,
        ]);
    }

    private static function filterPlan(Builder $query, string $value): void
    {
        if ($value === CorporateFinanceMetrics::CORPORATE_PLAN_LABEL) {
            $query->whereRaw("UPPER(sales.type) LIKE '%CORPORATIV%'");

            return;
        }

        $query->whereRaw("UPPER(COALESCE(sales.type, '')) NOT LIKE '%CORPORATIV%'")
            ->leftJoin('plans', 'plans.id', '=', 'sales.plan_id')
            ->whereRaw("UPPER(COALESCE(NULLIF(TRIM(plans.description), ''), 'SIN PLAN')) = ?", [$value]);
    }

    private static function filterSaleState(Builder $query, string $value): void
    {
        $ids = CorporateMapMetrics::stateIdsFor($value) ?: [0];

        $query->leftJoin('affiliations', 'affiliations.code', '=', 'sales.affiliation_code')
            ->leftJoin('affiliation_corporates', 'affiliation_corporates.code', '=', 'sales.affiliation_code')
            ->whereIn(DB::raw('COALESCE(affiliations.state_id_ti, affiliation_corporates.state_id, 0)'), $ids);
    }

    /**
     * @param  list<mixed>  $columns
     * @param  callable(object): array<string, mixed>  $map
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private static function page(Builder $query, int $limit, string $orderColumn, array $columns, callable $map, bool $ascending = false): array
    {
        $total = (clone $query)->count();
        $rows = $query->select($columns)->orderBy($orderColumn, $ascending ? 'asc' : 'desc')->limit($limit)->get();

        return ['rows' => $rows->map($map)->values()->all(), 'total' => $total];
    }

    private static function date(mixed $value): ?string
    {
        return $value ? CarbonImmutable::parse((string) $value)->format('d/m/Y') : null;
    }
}
