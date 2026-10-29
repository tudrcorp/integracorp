<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Support\Operations\Providers\ProviderNetworkAnalytics;

/**
 * Estatus del convenio por tipo de proveedor, con los nombres del catálogo de Operaciones.
 */
class ProviderNetworkStatus extends ProviderNetworkWidget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.operations.providers.status';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 7];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $snapshot = ProviderNetworkAnalytics::snapshot();

        return [
            'matrix' => $snapshot['status'],
            'other' => $snapshot['status_other'],
            'types' => self::types(),
            'statuses' => self::statuses(),
            'totals' => $snapshot['totals'],
        ];
    }
}
