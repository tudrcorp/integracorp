<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use Filament\Widgets\Widget;

/**
 * Ranking con barras proporcionales: cada fila abre el detalle de su corte.
 */
abstract class OperationsRankingWidget extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.operations.dashboard.ranking';

    abstract public function getHeading(): string;

    abstract public function getDescription(): string;

    /**
     * Dimensión del detalle que abre cada fila.
     */
    abstract protected function dimension(): string;

    /**
     * @return array{rows: list<array{label: string, value: int, key?: string, meta?: string}>, others: int, total: int}
     */
    abstract protected function ranking(): array;

    protected function emptyText(): string
    {
        return 'No hay servicios en este período.';
    }

    /**
     * Qué se cuenta (plural), para «Otros: N …».
     */
    protected function noun(): string
    {
        return 'servicios';
    }

    /**
     * Si los valores son dinero (USD).
     */
    protected function isMoney(): bool
    {
        return false;
    }

    protected function icon(): string
    {
        return 'heroicon-o-bars-3-bottom-left';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $ranking = $this->ranking();
        $max = max(array_column($ranking['rows'], 'value') ?: [0]);
        $total = max(1, (int) $ranking['total']);

        return [
            'heading' => $this->getHeading(),
            'description' => $this->getDescription(),
            'dimension' => $this->dimension(),
            'icon' => $this->icon(),
            'emptyText' => $this->emptyText(),
            'noun' => $this->noun(),
            'money' => $this->isMoney(),
            'periodKey' => $this->period()->cacheKey(),
            'others' => (int) $ranking['others'],
            'rows' => array_map(static fn (array $row): array => $row + [
                'width' => $max > 0 ? round($row['value'] * 100 / $max, 1) : 0,
                'percent' => number_format($row['value'] * 100 / $total, 1, ',', '.').' %',
                'key' => $row['key'] ?? $row['label'],
                'meta' => $row['meta'] ?? null,
            ], $ranking['rows']),
        ];
    }
}
