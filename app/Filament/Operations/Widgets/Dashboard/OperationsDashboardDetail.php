<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Throwable;

/**
 * Detalle de cualquier corte del dashboard: se abre al hacer clic en una barra,
 * segmento, fila o celda, sin salir de la página. Muestra hasta 200 servicios
 * del período y enlaza cada uno a su caso.
 */
class OperationsDashboardDetail extends Widget
{
    use InteractsWithOperationsDashboard;

    public const MODAL_ID = 'operations-dashboard-detail';

    public const LIMIT = 200;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 99;

    protected string $view = 'filament.operations.dashboard.detail';

    protected int|string|array $columnSpan = 'full';

    public ?string $dimension = null;

    public ?string $value = null;

    #[On('operations-dashboard-detail')]
    public function openDetail(string $dimension, string $value): void
    {
        if (! in_array($dimension, $this->allowedDimensions(), true)) {
            return;
        }

        $this->dimension = $dimension;
        $this->value = Str::limit(trim($value), 300, '');

        $this->dispatch('open-modal', id: $this->modalId());
    }

    /**
     * Id del modal: propio por widget, para que dos detalles en la misma página
     * (Operaciones y el panel corporativo) no se abran juntos.
     */
    public function modalId(): string
    {
        return self::MODAL_ID;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        if ($this->dimension === null || $this->value === null) {
            return ['title' => null, 'rows' => [], 'total' => 0, 'periodLabel' => null, 'canOpenCases' => false, 'columns' => $this->columns(), 'noun' => $this->noun()];
        }

        $period = $this->period();
        $detail = $this->fetchDetail($this->dimension, $this->value);
        $title = $this->dimensionLabel($this->dimension, $this->value);

        if ($this->dimension === 'patient' && $detail['rows'] !== []) {
            $title = 'Paciente: '.($detail['rows'][0]['patient'] ?? 'sin nombre');
        }

        return [
            'title' => $title,
            'rows' => $detail['rows'],
            'total' => $detail['total'],
            'periodLabel' => $period->label(),
            'canOpenCases' => $this->canOpenCases(),
            'columns' => $this->columns(),
            'noun' => $this->noun(),
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedDimensions(): array
    {
        return OperationsDashboardAnalytics::dimensions();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    protected function fetchDetail(string $dimension, string $value): array
    {
        return OperationsDashboardAnalytics::detail($this->period(), $dimension, $value, self::LIMIT);
    }

    protected function dimensionLabel(string $dimension, string $value): string
    {
        return OperationsDashboardAnalytics::dimensionLabel($dimension, $value);
    }

    /**
     * Qué lista el detalle (plural), para el encabezado.
     */
    protected function noun(): string
    {
        return 'servicios';
    }

    /**
     * Columnas de la tabla: etiqueta y clave de la fila. `case_code` se enlaza al
     * caso y `status` lleva su punto de color.
     *
     * @return list<array{label: string, key: string}>
     */
    protected function columns(): array
    {
        return [
            ['label' => 'Fecha', 'key' => 'date'],
            ['label' => 'Caso', 'key' => 'case_code'],
            ['label' => 'Paciente', 'key' => 'patient'],
            ['label' => 'Servicio', 'key' => 'service'],
            ['label' => 'Tipo', 'key' => 'type'],
            ['label' => 'Estatus', 'key' => 'status'],
            ['label' => 'Ubicación', 'key' => 'place'],
        ];
    }

    public function caseUrl(?int $caseId): ?string
    {
        if ($caseId === null) {
            return null;
        }

        try {
            return TelemedicineCaseResource::getUrl('view', ['record' => $caseId]);
        } catch (Throwable) {
            return null;
        }
    }

    private function canOpenCases(): bool
    {
        try {
            return TelemedicineCaseResource::canAccess();
        } catch (Throwable) {
            return false;
        }
    }
}
