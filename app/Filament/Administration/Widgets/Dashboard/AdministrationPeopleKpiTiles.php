<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateKpiTiles;
use App\Support\Administration\Dashboard\AdministrationPeopleMetrics;

/**
 * RRHH: plantilla y su costo. Sueldos y costo de nómina solo con el permiso
 * «Ver sueldos y costo de nómina»; sin él, la sección muestra solo personas.
 */
class AdministrationPeopleKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $summary = AdministrationPeopleMetrics::summary($this->period());
        $tiles = [
            self::snapshotTile('active', 'Plantilla activa', $summary['active'], 'heroicon-o-user-group', 'Colaboradores con estatus activo, a hoy.'),
            self::tile('hires', 'Ingresos de personal', $summary['hires'], $summary['previous_hires'], 'heroicon-o-user-plus', 'Colaboradores con fecha de ingreso en el período.', []),
        ];

        if (AdministrationPeopleMetrics::canSeeAmounts()) {
            $note = $summary['with_salary'] < $summary['active']
                ? $summary['with_salary'].' de '.$summary['active'].' con sueldo cargado'
                : null;

            $tiles[] = self::tile('payroll', 'Costo mensual', $summary['payroll'], $summary['payroll'], 'heroicon-o-banknotes', 'Suma del sueldo mensual registrado de la plantilla activa (no hay nóminas procesadas en el sistema).', [], decimals: 0, prefix: 'US$ ', note: $note, snapshot: true);
            $tiles[] = self::tile('average_salary', 'Sueldo promedio', (float) ($summary['average_salary'] ?? 0), (float) ($summary['average_salary'] ?? 0), 'heroicon-o-calculator', 'Promedio entre quienes tienen sueldo cargado.', [], decimals: 0, prefix: 'US$ ', note: $note, snapshot: true);
        }

        $tiles[] = self::tile('seniority', 'Antigüedad promedio', (float) ($summary['average_seniority'] ?? 0), (float) ($summary['average_seniority'] ?? 0), 'heroicon-o-clock', 'Años en la empresa, en promedio, según la fecha de ingreso.', [], decimals: 1, suffix: ' años', snapshot: true);
        $tiles[] = self::tile('age', 'Edad promedio', (float) ($summary['average_age'] ?? 0), (float) ($summary['average_age'] ?? 0), 'heroicon-o-cake', 'Edad promedio de la plantilla activa.', [], decimals: 1, suffix: ' años', snapshot: true);

        return $tiles;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $data = parent::getViewData();
        $data['tilesGrid'] = count($data['tiles']) > 4 ? 'md:grid-cols-3 2xl:grid-cols-6' : 'md:grid-cols-2 2xl:grid-cols-4';

        return $data;
    }
}
