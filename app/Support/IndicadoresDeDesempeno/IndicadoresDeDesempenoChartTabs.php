<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\ColaboradoresHelpdeskTicketsChart;
use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\SupplierAcceptanceLettersChart;
use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\SupplierNewProviderCreationChart;
use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\SupplierObservationsChart;
use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\SupplierProviderSystemUpdateChart;
use Filament\Widgets\ChartWidget;

final class IndicadoresDeDesempenoChartTabs
{
    public const TAB_HELPDESK_TICKETS = 'helpdesk_tickets';

    public const TAB_OBSERVATIONS = 'observaciones';

    public const TAB_SYSTEM_UPDATES = 'actualizaciones_sistema';

    public const TAB_NEW_PROVIDERS = 'nuevos_proveedores';

    public const TAB_ACCEPTANCE_LETTERS = 'cartas_aceptacion';

    /**
     * @return array<string, array{label: string, widget: class-string<ChartWidget>}>
     */
    public static function definitions(): array
    {
        return [
            self::TAB_HELPDESK_TICKETS => [
                'label' => 'Tickets creados',
                'widget' => ColaboradoresHelpdeskTicketsChart::class,
            ],
            self::TAB_OBSERVATIONS => [
                'label' => 'Observaciones',
                'widget' => SupplierObservationsChart::class,
            ],
            self::TAB_SYSTEM_UPDATES => [
                'label' => 'Actualizaciones en sistema',
                'widget' => SupplierProviderSystemUpdateChart::class,
            ],
            self::TAB_NEW_PROVIDERS => [
                'label' => 'Nuevos proveedores',
                'widget' => SupplierNewProviderCreationChart::class,
            ],
            self::TAB_ACCEPTANCE_LETTERS => [
                'label' => 'Cartas de aceptación',
                'widget' => SupplierAcceptanceLettersChart::class,
            ],
        ];
    }

    public static function defaultTab(): string
    {
        return self::TAB_HELPDESK_TICKETS;
    }

    /**
     * @return class-string<ChartWidget>
     */
    public static function widgetClassForTab(string $tab): string
    {
        $definitions = self::definitions();

        if (! array_key_exists($tab, $definitions)) {
            return $definitions[self::defaultTab()]['widget'];
        }

        return $definitions[$tab]['widget'];
    }
}
