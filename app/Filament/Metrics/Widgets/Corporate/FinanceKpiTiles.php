<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;

/**
 * Finanzas: ingresos, ticket, comisiones, costo médico, margen y siniestralidad.
 * Margen y siniestralidad son estimados (el costo médico es el de las órdenes
 * de servicio finalizadas, que existen desde junio de 2026).
 */
class FinanceKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $period = $this->period();
        $data = CorporateFinanceMetrics::kpis($period);
        $current = $data['current'];
        $previous = $data['previous'];
        $notice = CorporateFinanceMetrics::costCoverageNotice($period) !== null ? 'Estimado: costos médicos incompletos' : 'Estimado';

        return [
            self::moneyTile('income', 'Ingresos', (float) $current['income'], (float) $previous['income'], 'heroicon-o-banknotes', 'Pagos registrados en el período (ventas), en USD.', $data['sparklines']['income']),
            self::moneyTile('ticket', 'Ticket promedio', (float) $current['average_ticket'], (float) $previous['average_ticket'], 'heroicon-o-receipt-percent', 'Ingreso promedio por pago registrado.'),
            self::moneyTile('commissions', 'Comisiones', (float) $current['commissions'], (float) $previous['commissions'], 'heroicon-o-user-group', 'Comisiones generadas en el período para agencias, agentes y referidores.'),
            self::moneyTile('cost', 'Costo médico', (float) $current['medical_cost'], (float) $previous['medical_cost'], 'heroicon-o-heart', 'Órdenes de servicio finalizadas en el período: monto facturado o, si no hay factura, el de la orden.', $data['sparklines']['cost'] ?? [], lowerIsBetter: true, note: $notice),
            self::moneyTile('margin', 'Margen estimado', (float) $current['margin'], (float) $previous['margin'], 'heroicon-o-scale', 'Ingresos menos comisiones y costo médico del período.', note: $notice),
            self::tile('loss', 'Siniestralidad', (float) ($current['loss_ratio'] ?? 0), (float) ($previous['loss_ratio'] ?? 0), 'heroicon-o-shield-exclamation', 'Costo médico sobre ingresos. La variación está en puntos porcentuales; bajar es mejor.', [], isPercent: true, lowerIsBetter: true, note: $notice),
        ];
    }

    protected function context(): array
    {
        $receivables = CorporateFinanceMetrics::receivables();

        return [
            ['label' => 'por cobrar', 'value' => $receivables['pending_amount'], 'icon' => 'heroicon-o-clock', 'prefix' => 'US$ '],
            ['label' => 'vencido', 'value' => $receivables['overdue_amount'], 'icon' => 'heroicon-o-exclamation-triangle', 'prefix' => 'US$ '],
            ['label' => 'cuotas pendientes', 'value' => $receivables['pending_count'], 'icon' => 'heroicon-o-document-text'],
        ];
    }
}
