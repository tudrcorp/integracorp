<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\OperationsRankingWidget;

/**
 * Ranking del panel corporativo. Por defecto, montos en USD.
 */
abstract class CorporateRanking extends OperationsRankingWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    protected function isMoney(): bool
    {
        return true;
    }

    protected function emptyText(): string
    {
        return 'No hay movimientos en este período.';
    }
}
