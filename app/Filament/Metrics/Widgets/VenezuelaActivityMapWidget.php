<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Services\IntegracorpApi\DashboardMetricsClient;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;
use Throwable;

class VenezuelaActivityMapWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = -2;

    protected string $view = 'filament.metrics.widgets.venezuela-activity-map';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array{
     *     years: array{current: int, previous: int, through_month: int},
     *     totals: array{
     *         current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null}
     *     },
     *     states: list<array{
     *         state_id: int,
     *         state: string,
     *         geo_key: string,
     *         current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null}
     *     }>
     * }|null
     */
    private ?array $resolvedPayload = null;

    /**
     * @return array{
     *     years: array{current: int, previous: int, through_month: int},
     *     totals: array{
     *         current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null}
     *     },
     *     states: list<array{
     *         state_id: int,
     *         state: string,
     *         geo_key: string,
     *         current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null}
     *     }>,
     *     mapPaths: list<array{geo_key: string, d: string, cx: float, cy: float}>
     * }
     */
    protected function getViewData(): array
    {
        $payload = $this->resolvePayload();

        return [
            ...$payload,
            'mapPaths' => $this->mapPaths(),
        ];
    }

    /**
     * @return array{
     *     years: array{current: int, previous: int, through_month: int},
     *     totals: array{
     *         current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null}
     *     },
     *     states: list<array{
     *         state_id: int,
     *         state: string,
     *         geo_key: string,
     *         current: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         previous: array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float},
     *         delta: array{agents_pct: float|null, agencies_pct: float|null, affiliations_count_pct: float|null, affiliations_amount_pct: float|null}
     *     }>
     * }
     */
    public function resolvePayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        try {
            $this->resolvedPayload = app(DashboardMetricsClient::class)->venezuelaByState();
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar el mapa de actividad nacional.', [
                'message' => $exception->getMessage(),
            ]);

            $this->resolvedPayload = [
                'years' => [
                    'current' => (int) now()->year,
                    'previous' => (int) now()->year - 1,
                    'through_month' => (int) now()->month,
                ],
                'totals' => [
                    'current' => $this->emptyMetrics(),
                    'previous' => $this->emptyMetrics(),
                    'delta' => [
                        'agents_pct' => 0.0,
                        'agencies_pct' => 0.0,
                        'affiliations_count_pct' => 0.0,
                        'affiliations_amount_pct' => 0.0,
                    ],
                ],
                'states' => [],
            ];
        }

        return $this->resolvedPayload;
    }

    /**
     * @return list<array{geo_key: string, d: string, cx: float, cy: float}>
     */
    private function mapPaths(): array
    {
        /** @var list<array{geo_key: string, d: string, cx: float, cy: float}> */
        return require resource_path('geo/venezuela-states-paths.php');
    }

    /**
     * @return array{agents: int, agencies: int, affiliations_count: int, affiliations_amount: float}
     */
    private function emptyMetrics(): array
    {
        return [
            'agents' => 0,
            'agencies' => 0,
            'affiliations_count' => 0,
            'affiliations_amount' => 0.0,
        ];
    }
}
