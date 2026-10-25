<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\OperationsDashboardDetail;
use App\Support\Metrics\Corporate\CorporateDetail;

/**
 * Detalle de cualquier corte del panel corporativo (pagos, cuotas, órdenes,
 * afiliaciones, cotizaciones o tickets), en un modal propio.
 */
class CorporateDetailWidget extends OperationsDashboardDetail
{
    public const CORPORATE_MODAL_ID = 'corporate-metrics-detail';

    public function modalId(): string
    {
        return self::CORPORATE_MODAL_ID;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $data = parent::getViewData();

        if ($this->dimension !== null && CorporateDetail::isSnapshot($this->dimension)) {
            $data['periodLabel'] = 'a hoy';
        }

        return $data;
    }

    protected function allowedDimensions(): array
    {
        return CorporateDetail::dimensions();
    }

    protected function fetchDetail(string $dimension, string $value): array
    {
        return CorporateDetail::fetch($this->period(), $dimension, $value, self::LIMIT);
    }

    protected function dimensionLabel(string $dimension, string $value): string
    {
        return CorporateDetail::label($dimension, $value);
    }

    protected function noun(): string
    {
        return $this->dimension === null ? 'registros' : CorporateDetail::noun($this->dimension);
    }

    protected function columns(): array
    {
        return $this->dimension === null ? [] : CorporateDetail::columns($this->dimension);
    }
}
