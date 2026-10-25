<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateRanking;
use App\Support\Administration\Dashboard\AdministrationPeopleMetrics;
use App\Support\Metrics\Corporate\CorporateMetrics;

class AdministrationPeopleByDepartmentRanking extends CorporateRanking
{
    public function getHeading(): string
    {
        return 'Plantilla por departamento';
    }

    public function getDescription(): string
    {
        return AdministrationPeopleMetrics::canSeeAmounts()
            ? 'Personas activas por departamento y su costo mensual registrado.'
            : 'Personas activas por departamento.';
    }

    protected function isMoney(): bool
    {
        return false;
    }

    protected function noun(): string
    {
        return 'personas';
    }

    protected function dimension(): string
    {
        return '';
    }

    protected function icon(): string
    {
        return 'heroicon-o-building-office';
    }

    protected function emptyText(): string
    {
        return 'No hay colaboradores activos.';
    }

    protected function ranking(): array
    {
        $showAmounts = AdministrationPeopleMetrics::canSeeAmounts();
        $rows = [];

        foreach (AdministrationPeopleMetrics::byDepartment() as $department) {
            $rows[] = [
                'label' => $department['label'],
                'value' => $department['people'],
                'key' => $department['label'],
                'meta' => $showAmounts && $department['payroll'] > 0 ? CorporateMetrics::money($department['payroll']).'/mes' : null,
            ];
        }

        return ['rows' => $rows, 'others' => 0, 'total' => array_sum(array_column($rows, 'value'))];
    }
}
