<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Captacion;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Filament\Operations\Widgets\Dashboard\OperationsDashboardDetail;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;
use Throwable;

/**
 * Detalle de cualquier barra o fila del Dashboard de Captación: los prospectos (o
 * tareas) del corte, cada uno enlazado a su ficha.
 */
class CaptacionDetail extends OperationsDashboardDetail
{
    public const CAPTACION_MODAL_ID = 'captacion-dashboard-detail';

    public function modalId(): string
    {
        return self::CAPTACION_MODAL_ID;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [...parent::getViewData(), 'canOpenCases' => self::canOpenProspects()];
    }

    protected function allowedDimensions(): array
    {
        return ProspectDashboardMetrics::detailDimensions();
    }

    protected function fetchDetail(string $dimension, string $value): array
    {
        return ProspectDashboardMetrics::detail($this->period(), $dimension, $value, self::LIMIT);
    }

    protected function dimensionLabel(string $dimension, string $value): string
    {
        return ProspectDashboardMetrics::detailLabel($dimension, $value);
    }

    protected function noun(): string
    {
        return $this->dimension === 'task_user' ? 'tareas' : 'prospectos';
    }

    protected function columns(): array
    {
        return ProspectDashboardMetrics::detailColumns($this->dimension ?? 'collaborator');
    }

    /**
     * Enlaza a la ficha del prospecto (no a un caso de telemedicina).
     */
    public function caseUrl(?int $caseId): ?string
    {
        if ($caseId === null) {
            return null;
        }

        try {
            return ProspectAgentResource::getUrl('view', ['record' => $caseId]);
        } catch (Throwable) {
            return null;
        }
    }

    private static function canOpenProspects(): bool
    {
        try {
            return ProspectAgentResource::canAccess();
        } catch (Throwable) {
            return false;
        }
    }
}
