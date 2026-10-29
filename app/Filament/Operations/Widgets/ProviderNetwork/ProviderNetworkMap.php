<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Support\Metrics\Corporate\CorporateGeography;
use App\Support\Operations\Providers\ProviderNetworkAnalytics;

/**
 * Mapa de Venezuela con los proveedores por estado: una capa por tipo, más «convenio activo» y
 * «sin carta». Al elegir un estado se ve su ficha y se puede abrir el listado.
 */
class ProviderNetworkMap extends ProviderNetworkWidget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.operations.providers.map';

    protected int|string|array $columnSpan = 'full';

    public const STEPS = 7;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $snapshot = ProviderNetworkAnalytics::snapshot();
        $layers = [
            'all' => ['label' => 'Todos los proveedores', 'short' => 'Todos', 'color' => 'var(--ops-series1)'],
            'juridico' => ['label' => 'Proveedores jurídicos', 'short' => 'Jurídicos', 'color' => 'var(--ops-series1)'],
            'natural' => ['label' => 'Proveedores naturales', 'short' => 'Naturales', 'color' => 'var(--ops-series2)'],
            'aliado' => ['label' => 'Aliados corporativos', 'short' => 'Aliados', 'color' => 'var(--ops-violet)'],
            'active' => ['label' => 'Con convenio activo', 'short' => 'Convenio activo', 'color' => 'var(--ops-good)'],
            'without_letter' => ['label' => 'Sin carta de aceptación', 'short' => 'Sin carta', 'color' => 'var(--ops-critical)'],
        ];

        foreach ($layers as $key => $layer) {
            $values = $snapshot['map'][$key] ?? [];
            $layers[$key] += ['values' => (object) $values, 'total' => array_sum($values)];
        }

        $abroad = [];
        foreach ($snapshot['abroad'] as $country => $byType) {
            $abroad[] = ['country' => $country, 'total' => array_sum($byType), 'types' => $byType];
        }

        return [
            'paths' => CorporateGeography::paths(),
            'layers' => $layers,
            'states' => $snapshot['states'],
            'types' => self::types(),
            'statuses' => self::statuses(),
            'agreements' => self::agreements(),
            'abroad' => $abroad,
            'unknown' => array_sum($snapshot['quality']['no_location']),
            'steps' => self::STEPS,
        ];
    }
}
