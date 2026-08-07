<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\CorretajeAgenciesAffiliationsByTypeChart;
use App\Services\IntegracorpApi\CorretajeAgenciesMetricsClient;

class CorretajeAgenciesByActiveCorporateAffiliationsChart extends CorretajeAgenciesAffiliationsByTypeChart
{
    protected string $view = 'filament.metrics.widgets.corretaje-agencies-affiliations-by-type-chart';

    protected function overviewHeading(): string
    {
        return 'Afiliaciones corporativas activas por tipo de agencia';
    }

    protected function overviewDescription(): string
    {
        return 'ACTIVA · code_agency = owner_code · agent_id nulo · affiliation_corporates';
    }

    protected function resolveAffiliationsPayload(): array
    {
        return app(CorretajeAgenciesMetricsClient::class)->byActiveCorporateAffiliations();
    }

    protected function resolveAffiliationsDetailPayload(int $agencyTypeId): array
    {
        return app(CorretajeAgenciesMetricsClient::class)->byActiveCorporateAffiliationsByAgency($agencyTypeId);
    }

    protected function chartWireKeyPrefix(): string
    {
        return 'metrics-agencies-by-active-corporate-affiliations';
    }

    protected function affiliationsLabel(): string
    {
        return 'afiliaciones corporativas';
    }
}
