<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Captacion;

use App\Filament\Metrics\Widgets\Corporate\CorporateRanking;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;

class CaptacionCollaboratorRanking extends CorporateRanking
{
    public function getHeading(): string
    {
        return 'Prospectos registrados por colaborador';
    }

    public function getDescription(): string
    {
        return 'Quién dio de alta los prospectos del período. Clic para verlos con su estatus.';
    }

    protected function dimension(): string
    {
        return 'collaborator';
    }

    protected function isMoney(): bool
    {
        return false;
    }

    protected function noun(): string
    {
        return 'prospectos';
    }

    protected function emptyText(): string
    {
        return 'No se registraron prospectos en este período.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-user';
    }

    protected function ranking(): array
    {
        return ProspectDashboardMetrics::byCollaborator($this->period(), 10);
    }
}
