<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use Filament\Widgets\Widget;

/**
 * Lectura automática de las personas: lo que la directiva debería mirar primero.
 */
class UsersInsightsWidget extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.metrics.corporate.insights';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();

        return [
            'heading' => 'Lectura de las personas',
            'description' => 'Lo que pide atención primero. Reglas fijas sobre el uso, la jornada y la productividad.',
            'insights' => CorporateUsersMetrics::insights($period),
            'urls' => [],
            'periodKey' => $period->cacheKey(),
        ];
    }
}
