<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Enums\ProviderNetworkType;
use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Providers\ProviderNetworkCatalog;
use Filament\Widgets\Widget;

/**
 * Base de los widgets del Dashboard de proveedores: misma página, mismos filtros y el mismo
 * detalle al hacer clic (evento `provider-network-detail`).
 */
abstract class ProviderNetworkWidget extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    /**
     * @return array<string, array{label: string, plural: string, color: string}>
     */
    protected static function types(): array
    {
        $out = [];

        foreach (ProviderNetworkType::cases() as $type) {
            $out[$type->value] = ['label' => $type->label(), 'plural' => $type->plural(), 'color' => $type->color()];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, color: string}>
     */
    protected static function statuses(): array
    {
        return array_map(static fn (array $status): array => ['label' => $status['label'], 'color' => $status['color']], ProviderNetworkCatalog::STATUSES);
    }

    /**
     * @return array<string, array{label: string, color: string, preferential: bool}>
     */
    protected static function agreements(): array
    {
        return ProviderNetworkCatalog::AGREEMENTS;
    }
}
