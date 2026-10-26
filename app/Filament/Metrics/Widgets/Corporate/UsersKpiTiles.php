<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;

/**
 * Usuarios: el uso del sistema por el personal (internos y médicos) y lo que
 * produce, con las cuentas del sistema a hoy.
 */
class UsersKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $data = CorporateUsersMetrics::kpis($this->period());
        $current = $data['current'];
        $previous = $data['previous'];

        return [
            self::tile('people', 'Personas conectadas', (int) $current['people'], (int) $previous['people'], 'heroicon-o-user-group', 'Personal (internos y médicos) con al menos un minuto activo en el período.', $data['sparklines']['people']),
            self::tile('hours', 'Horas activas', (float) $current['hours'], (float) $previous['hours'], 'heroicon-o-cursor-arrow-rays', 'Tiempo en que el personal tecleó, movió el mouse, tocó o desplazó en el sistema. No cuenta el sistema abierto sin usar.', $data['sparklines']['hours'], decimals: 1, suffix: ' h'),
            self::tile('avg_day_hours', 'Promedio por persona y día', (float) $current['avg_day_hours'], (float) $previous['avg_day_hours'], 'heroicon-o-scale', 'Horas activas divididas entre los días en que cada persona se conectó.', [], decimals: 1, suffix: ' h'),
            self::tile('work_usage', 'Uso de la jornada', (float) ($current['work_usage'] ?? 0), (float) ($previous['work_usage'] ?? 0), 'heroicon-o-briefcase', 'Tiempo activo y en reuniones dentro del horario, sobre la jornada esperada (sin el almuerzo declarado). La variación está en puntos porcentuales.', [], isPercent: true),
            self::tile('overtime_hours', 'Horas extra', (float) $current['overtime_hours'], (float) $previous['overtime_hours'], 'heroicon-o-moon', 'Tiempo activo fuera de horario, en fin de semana o feriado.', [], decimals: 1, suffix: ' h'),
            self::tile('records', 'Registros creados', (int) $current['records'], (int) $previous['records'], 'heroicon-o-document-plus', 'Afiliaciones, cotizaciones, coordinaciones, órdenes de servicio y consultas de telemedicina creadas por el personal en el período.', []),
        ];
    }

    protected function context(): array
    {
        $snapshot = CorporateUsersMetrics::kpis($this->period())['snapshot'];

        return [
            ['label' => 'cuentas activas', 'value' => $snapshot['accounts'], 'icon' => 'heroicon-m-users'],
            ['label' => 'del personal', 'value' => $snapshot['staff'], 'icon' => 'heroicon-m-building-office'],
            ['label' => 'de la red comercial', 'value' => $snapshot['network'], 'icon' => 'heroicon-m-briefcase'],
            ['label' => 'conectadas hoy', 'value' => $snapshot['online_today'], 'icon' => 'heroicon-m-signal'],
            ['label' => 'cuentas internas sin uso en '.CorporateUsersMetrics::IDLE_ACCOUNT_DAYS.' días', 'value' => $snapshot['idle_accounts'], 'icon' => 'heroicon-m-no-symbol'],
        ];
    }
}
