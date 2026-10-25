<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;

/**
 * Pacientes con más servicios en el período (con cuántos casos los generaron).
 */
class OperationsTopPatientsRanking extends OperationsRankingWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string
    {
        return 'Pacientes con más servicios';
    }

    public function getDescription(): string
    {
        return 'Los 10 pacientes que más servicios requirieron. Haga clic para ver sus servicios.';
    }

    protected function dimension(): string
    {
        return 'patient';
    }

    protected function icon(): string
    {
        return 'heroicon-o-user-group';
    }

    protected function ranking(): array
    {
        $patients = OperationsDashboardAnalytics::topPatients($this->period(), 10);
        $total = OperationsDashboardAnalytics::kpis($this->period())['current']['services'];

        return [
            'rows' => array_map(static fn (array $patient): array => [
                'label' => $patient['label'],
                'value' => $patient['value'],
                'key' => $patient['key'],
                'meta' => $patient['cases'] === 1 ? '1 caso' : $patient['cases'].' casos',
            ], $patients),
            'others' => 0,
            'total' => (int) $total,
        ];
    }
}
