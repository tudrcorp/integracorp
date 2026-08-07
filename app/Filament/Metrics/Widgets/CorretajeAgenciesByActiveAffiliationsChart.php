<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\CorretajeAgenciesAffiliationsByTypeChart;
use App\Services\IntegracorpApi\CorretajeAgenciesMetricsClient;

class CorretajeAgenciesByActiveAffiliationsChart extends CorretajeAgenciesAffiliationsByTypeChart
{
    protected string $view = 'filament.metrics.widgets.corretaje-agencies-affiliations-by-type-chart';

    protected function overviewHeading(): string
    {
        return 'Afiliaciones activas por tipo de agencia';
    }

    protected function overviewDescription(): string
    {
        return 'ACTIVA · code_agency = owner_code · agent_id nulo';
    }

    protected function resolveAffiliationsPayload(): array
    {
        return app(CorretajeAgenciesMetricsClient::class)->byActiveAffiliations();
    }

    protected function resolveAffiliationsDetailPayload(int $agencyTypeId): array
    {
        return app(CorretajeAgenciesMetricsClient::class)->byActiveAffiliationsByAgency($agencyTypeId);
    }

    protected function chartWireKeyPrefix(): string
    {
        return 'metrics-agencies-by-active-affiliations';
    }

    protected function affiliationsLabel(): string
    {
        return 'afiliaciones activas';
    }
}
