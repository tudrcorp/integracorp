<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Metrics\Pages\Cartera;
use App\Filament\Metrics\Pages\Comercial;
use App\Filament\Metrics\Pages\Finanzas;
use App\Filament\Metrics\Pages\OperacionesYSalud;
use App\Filament\Metrics\Pages\Organizacion;
use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Metrics\Corporate\CorporateInsights;
use Filament\Widgets\Widget;
use Throwable;

/**
 * Lectura automática del período: lo que pide atención primero.
 */
class CorporateInsightsWidget extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.metrics.corporate.insights';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    /** @var array<string, class-string> */
    public const PAGES = [
        'finance' => Finanzas::class,
        'commercial' => Comercial::class,
        'portfolio' => Cartera::class,
        'operations' => OperacionesYSalud::class,
        'organization' => Organizacion::class,
    ];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $urls = [];

        foreach (self::PAGES as $key => $page) {
            try {
                $urls[$key] = $page::canAccess() ? $page::getUrl() : null;
            } catch (Throwable) {
                $urls[$key] = null;
            }
        }

        return [
            'insights' => CorporateInsights::for($period, 7),
            'urls' => $urls,
            'periodKey' => $period->cacheKey(),
        ];
    }
}
