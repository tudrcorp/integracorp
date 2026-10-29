<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Support\Operations\Providers\ProviderNetworkAnalytics;

/**
 * Productividad del equipo de proveedores en el período, por persona.
 */
class ProviderNetworkProductivity extends ProviderNetworkWidget
{
    protected static ?int $sort = 7;

    protected string $view = 'filament.operations.providers.productivity';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();

        return [
            ...ProviderNetworkAnalytics::productivity($period),
            'periodLabel' => $period->label(),
            'previousLabel' => $period->previousLabel(),
            'compare' => $period->compare,
            'unknown' => ProviderNetworkAnalytics::UNKNOWN_USER,
        ];
    }
}
