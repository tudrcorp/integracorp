<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Support\Operations\Providers\ProviderNetworkAnalytics;

/**
 * General frente a preferencial (bajo costo y geográfico), por tipo de proveedor.
 */
class ProviderNetworkAgreements extends ProviderNetworkWidget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.operations.providers.agreements';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 5];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $snapshot = ProviderNetworkAnalytics::snapshot();

        return [
            'matrix' => $snapshot['agreements'],
            'types' => self::types(),
            'agreements' => self::agreements(),
            'totals' => $snapshot['totals'],
        ];
    }
}
