<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Administration\Dashboard\AdministrationPeopleMetrics;
use Filament\Widgets\Widget;

/**
 * Próximos cumpleaños y aniversarios laborales (30 días).
 */
class AdministrationUpcomingDates extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.administration.dashboard.upcoming-dates';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['events' => array_slice(AdministrationPeopleMetrics::upcomingDates(), 0, 8)];
    }
}
