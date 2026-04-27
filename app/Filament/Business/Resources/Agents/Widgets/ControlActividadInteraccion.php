<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use App\Support\AgentActivity\AgentActivityQuery;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ControlActividadInteraccion extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.control-actividad-interaccion';

    public int $filterYear;

    /**
     * Mes seleccionado.
     * - 1..12: mes específico
     * - 0: todo el año
     */
    public int $filterMonth;

    public function mount(): void
    {
        $now = Carbon::now();
        $this->filterYear = (int) $now->year;
        $this->filterMonth = (int) $now->month;
    }

    public function updatedFilterYear(): void
    {
        $this->clampMonthToYear();
    }

    public function updatedFilterMonth(): void
    {
        $this->clampMonthToYear();
    }

    /**
     * @return array<int, int> [year => year]
     */
    public function getAvailableYears(): array
    {
        $nowYear = (int) Carbon::now()->year;

        $mins = collect([
            DB::table('sales')->min('created_at'),
            DB::table('individual_quotes')->min('created_at'),
            DB::table('corporate_quotes')->min('created_at'),
            DB::table('corporate_quote_requests')->min('created_at'),
        ])->filter();

        $minYear = $mins->isNotEmpty()
            ? (int) Carbon::parse($mins->min())->year
            : $nowYear;

        $minYear = min($minYear, $nowYear);

        $years = [];
        for ($y = $nowYear; $y >= $minYear; $y--) {
            $years[$y] = $y;
        }

        return $years;
    }

    /**
     * @return array<int, string> [monthNumber => label]
     */
    public function getAvailableMonths(): array
    {
        $now = Carbon::now();
        $maxMonth = ($this->filterYear === (int) $now->year) ? (int) $now->month : 12;

        $labels = [
            0 => 'Todo el año',
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        // Siempre incluir "Todo el año" (0) y luego meses hasta el máximo permitido.
        $months = array_slice($labels, 1, $maxMonth, true);

        return [0 => $labels[0]] + $months;
    }

    /**
     * Compatibilidad con el patrón de filtros de otros widgets.
     *
     * @return array<int, int>
     */
    public function getChartYearOptions(): array
    {
        return $this->getAvailableYears();
    }

    /**
     * Compatibilidad con el patrón de filtros de otros widgets.
     *
     * @return array<int, string>
     */
    public function getChartMonthOptions(): array
    {
        return $this->getAvailableMonths();
    }

    /**
     * @return array{total: int, activo: int, enRiesgo: int, inactivo: int, activoPct: int, enRiesgoPct: int, inactivoPct: int}
     */
    public function getTrafficLightStats(): array
    {
        $asOf = $this->getAsOfDate();

        $agents = AgentActivityQuery::applyToAgentsTableQuery(DB::table('agents'), $asOf)
            ->addSelect(['agents.id'])
            ->get();

        $total = (int) $agents->count();

        $activo = 0;
        $enRiesgo = 0;
        $inactivo = 0;

        foreach ($agents as $agent) {
            $lastInteractionAt = $agent->last_interaction_at ? Carbon::parse($agent->last_interaction_at) : null;
            $daysSinceInteraction = $lastInteractionAt ? $lastInteractionAt->diffInDays($asOf) : 9999;

            $lastSaleAt = $agent->last_sale_at ? Carbon::parse($agent->last_sale_at) : null;
            $daysSinceSale = $lastSaleAt ? $lastSaleAt->diffInDays($asOf) : 9999;

            // 🟢 Activo: cotización o venta en últimos 30 días (interacción con el sistema).
            if ($daysSinceInteraction <= 30) {
                $activo++;

                continue;
            }

            // 🔴 Inactivo: > 91 días sin producción (ventas) y sin interacción.
            if ($daysSinceInteraction >= 91 && $daysSinceSale >= 91) {
                $inactivo++;

                continue;
            }

            // 🟡 En Riesgo: sin ventas registradas en un período de entre 31 y 90 días.
            // (Si no está activo y no es inactivo, se considera en riesgo).
            $enRiesgo++;
        }

        $pct = static fn (int $n) => $total > 0 ? (int) round(($n / $total) * 100) : 0;

        return [
            'total' => $total,
            'activo' => $activo,
            'enRiesgo' => $enRiesgo,
            'inactivo' => $inactivo,
            'activoPct' => $pct($activo),
            'enRiesgoPct' => $pct($enRiesgo),
            'inactivoPct' => $pct($inactivo),
        ];
    }

    private function clampMonthToYear(): void
    {
        $now = Carbon::now();
        $maxMonth = ($this->filterYear === (int) $now->year) ? (int) $now->month : 12;

        if ($this->filterMonth < 0) {
            $this->filterMonth = 0;
        }

        if ($this->filterMonth > $maxMonth) {
            $this->filterMonth = $maxMonth;
        }
    }

    private function getAsOfDate(): Carbon
    {
        $this->clampMonthToYear();

        $now = Carbon::now();
        $year = (int) $this->filterYear;
        $month = (int) $this->filterMonth;

        // "Todo el año": en el año actual usamos el día de hoy; en años pasados, fin de año.
        if ($month === 0) {
            return $year === (int) $now->year
                ? $now
                : Carbon::create($year, 1, 1)->endOfYear();
        }

        if ($year === (int) $now->year && $month === (int) $now->month) {
            return $now;
        }

        return Carbon::create($year, $month, 1)->endOfMonth();
    }
}
