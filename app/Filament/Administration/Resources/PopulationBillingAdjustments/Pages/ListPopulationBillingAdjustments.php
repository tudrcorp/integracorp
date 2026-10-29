<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\PopulationBillingAdjustments\Pages;

use App\Filament\Administration\Resources\PopulationBillingAdjustments\PopulationBillingAdjustmentResource;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingTray;
use App\Support\Filament\SummaryCards;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListPopulationBillingAdjustments extends ListRecords
{
    protected static string $resource = PopulationBillingAdjustmentResource::class;

    protected static ?string $title = 'Ajustes por población';

    public function getSubheading(): string|Htmlable|null
    {
        $summary = PopulationBillingTray::summary();

        return SummaryCards::collapsible(
            label: 'Resumen de ajustes por población',
            description: 'Cuando un grupo corporativo suma o resta personas, aquí se factura la diferencia: las altas pagan por días el período ya facturado y las cuotas por pagar cambian de monto. Las bajas no generan crédito.',
            icon: 'heroicon-o-users',
            hint: 'Por ajustar: '.SummaryCards::count($summary['pending_count'], 'carga', 'cargas'),
            cards: [
                [
                    'label' => 'Por ajustar',
                    'value' => SummaryCards::count($summary['pending_count'], 'carga', 'cargas'),
                    'detail' => 'Población aplicada sin facturar la diferencia',
                    'color' => SummaryCards::AMBER,
                ],
                [
                    'label' => 'Ajustes aplicados',
                    'value' => SummaryCards::count($summary['adjusted_count'], 'ajuste', 'ajustes'),
                    'detail' => 'Avisos emitidos: '.PopulationBillingCalculator::money($summary['charged_cents']),
                    'color' => SummaryCards::BLUE,
                ],
                [
                    'label' => 'Avisos por ajuste sin cobrar',
                    'value' => PopulationBillingCalculator::money($summary['pending_charge_cents']),
                    'detail' => 'Se cobran con el registro de pagos de la afiliación',
                    'color' => SummaryCards::RED,
                ],
            ],
        );
    }
}
