<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Support\Operations\Providers\ProviderNetworkAnalytics;

/**
 * Cartas de aceptación: cuántos las tienen y cuántos con convenio activo todavía no.
 */
class ProviderNetworkLetters extends ProviderNetworkWidget
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.operations.providers.letters';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 5];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $snapshot = ProviderNetworkAnalytics::snapshot();

        return [
            'letters' => $snapshot['letters'],
            'types' => self::types(),
        ];
    }
}
