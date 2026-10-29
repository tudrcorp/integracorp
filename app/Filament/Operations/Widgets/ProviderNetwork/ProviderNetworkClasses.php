<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Support\Operations\Providers\ProviderNetworkAnalytics;

/**
 * Tipos de proveedor: clasificación de los jurídicos, especialidad de los naturales y categoría
 * de los aliados.
 */
class ProviderNetworkClasses extends ProviderNetworkWidget
{
    protected static ?int $sort = 6;

    protected string $view = 'filament.operations.providers.classes';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 7];

    public const TOP = 10;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $snapshot = ProviderNetworkAnalytics::snapshot();
        $groups = [];

        foreach (self::types() as $type => $meta) {
            $values = $snapshot['classes'][$type] ?? [];
            $top = array_slice($values, 0, self::TOP, true);
            $rest = array_sum($values) - array_sum($top);

            $groups[$type] = [
                'title' => match ($type) {
                    'juridico' => 'Clasificación de jurídicos',
                    'natural' => 'Especialidad de naturales',
                    default => 'Categoría de aliados',
                },
                'items' => $top,
                'rest' => $rest,
                'distinct' => count($values),
                'total' => array_sum($values),
            ];
        }

        return ['groups' => $groups, 'types' => self::types()];
    }
}
