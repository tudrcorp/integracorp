<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets;

use App\Support\IndicadoresDeDesempeno\ColaboradorDailyActivitiesCounter;
use Filament\Widgets\Widget;

class ColaboradorActivitiesSpeedometerWidget extends Widget
{
    protected string $view = 'filament.operations.colaborador-activities-speedometer';

    protected int|string|array $columnSpan = 'full';

    public ?string $selectedCollaborator = null;

    public ?string $activityDate = null;

    public function mount(): void
    {
        $this->activityDate = now()->toDateString();
        $this->ensureCollaboratorSelection();
    }

    public function updatedSelectedCollaborator(): void
    {
        $this->ensureCollaboratorSelection();
    }

    /**
     * @return array<string, string>
     */
    public function getCollaboratorOptions(): array
    {
        return ColaboradorDailyActivitiesCounter::collaboratorOptions();
    }

    /**
     * @return array{
     *     total: int,
     *     tickets: int,
     *     observaciones: int,
     *     actualizaciones: int,
     *     nuevos_proveedores: int,
     *     cartas_aceptacion: int
     * }
     */
    public function getActivityBreakdown(): array
    {
        $collaborator = $this->resolvedCollaborator();

        if ($collaborator === null || $this->activityDate === null) {
            return [
                'total' => 0,
                'tickets' => 0,
                'observaciones' => 0,
                'actualizaciones' => 0,
                'nuevos_proveedores' => 0,
                'cartas_aceptacion' => 0,
            ];
        }

        return ColaboradorDailyActivitiesCounter::breakdownForCollaboratorOnDate($collaborator, $this->activityDate);
    }

    /**
     * @return array{level: string, label: string, color: string, description: string}
     */
    public function getPerformanceMeta(): array
    {
        return ColaboradorDailyActivitiesCounter::performanceMeta($this->getActivityBreakdown()['total']);
    }

    public function getNeedleRotation(): float
    {
        return ColaboradorDailyActivitiesCounter::needleRotationDegrees($this->getActivityBreakdown()['total']);
    }

    public function getGaugeMax(): int
    {
        return ColaboradorDailyActivitiesCounter::GAUGE_MAX;
    }

    private function resolvedCollaborator(): ?string
    {
        $collaborator = trim((string) $this->selectedCollaborator);

        return $collaborator !== '' ? $collaborator : null;
    }

    private function ensureCollaboratorSelection(): void
    {
        $options = $this->getCollaboratorOptions();

        if ($options === []) {
            $this->selectedCollaborator = null;

            return;
        }

        if ($this->resolvedCollaborator() === null || ! array_key_exists($this->selectedCollaborator, $options)) {
            $this->selectedCollaborator = array_key_first($options);
        }
    }
}
