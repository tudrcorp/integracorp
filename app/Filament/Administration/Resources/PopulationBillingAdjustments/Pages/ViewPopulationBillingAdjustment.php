<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\PopulationBillingAdjustments\Pages;

use App\Filament\Administration\Resources\PopulationBillingAdjustments\Actions\PopulationBillingAdjustmentActions;
use App\Filament\Administration\Resources\PopulationBillingAdjustments\PopulationBillingAdjustmentResource;
use App\Models\CorporatePopulationUpdate;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPopulationBillingAdjustment extends ViewRecord
{
    protected static string $resource = PopulationBillingAdjustmentResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var CorporatePopulationUpdate $record */
        $record = $this->getRecord();

        return 'Ajuste por población · '.($record->affiliationCorporate?->code ?? '').' · carga del '.($record->applied_at?->format('d/m/Y') ?? '—');
    }

    protected function getHeaderActions(): array
    {
        return [
            PopulationBillingAdjustmentActions::apply(),
            PopulationBillingAdjustmentActions::previewPdf(),
            PopulationBillingAdjustmentActions::downloadPdf(),
            PopulationBillingAdjustmentActions::revert(),
        ];
    }
}
