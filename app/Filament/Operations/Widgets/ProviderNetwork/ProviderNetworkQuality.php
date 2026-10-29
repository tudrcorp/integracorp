<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Support\Operations\Providers\ProviderNetworkAnalytics;

/**
 * Datos por completar: proveedores sin ubicación, sin estatus, sin tipo de convenio o con un
 * estatus fuera del catálogo. Es la lista de trabajo del equipo para que el dashboard sea fiel.
 */
class ProviderNetworkQuality extends ProviderNetworkWidget
{
    protected static ?int $sort = 8;

    protected string $view = 'filament.operations.providers.quality';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $snapshot = ProviderNetworkAnalytics::snapshot();

        return [
            'quality' => $snapshot['quality'],
            'other' => $snapshot['status_other'],
            'types' => self::types(),
        ];
    }
}
