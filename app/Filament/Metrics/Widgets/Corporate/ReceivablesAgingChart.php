<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Antigüedad de la deuda vencida, a hoy (no depende del período).
 */
class ReceivablesAgingChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Antigüedad de lo vencido';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Foto a hoy: cuotas pendientes cuya fecha de pago ya pasó. Clic para ver las cuotas.';
    }

    protected function values(): array
    {
        return CorporateFinanceMetrics::receivables()['aging'];
    }

    protected function isMoney(): bool
    {
        return true;
    }

    protected function shareText(): string
    {
        return ' de lo vencido';
    }

    protected function detailDimension(): string
    {
        return 'receivable_aging';
    }
}
