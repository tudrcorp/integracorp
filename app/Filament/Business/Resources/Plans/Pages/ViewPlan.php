<?php

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Filament\Business\Resources\Plans\PlanResource;
use App\Support\ClinicalEntitlements\PlanClinicalCompleteness;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPlan extends ViewRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $complete = $record instanceof \App\Models\Plan && PlanClinicalCompleteness::isComplete($record);

        return [
            Action::make('usoClinico')
                ->label($complete ? 'Uso clínico' : 'Completar uso clínico')
                ->icon(Heroicon::OutlinedHeart)
                ->color($complete ? 'gray' : 'warning')
                ->url(PlanResource::getUrl('uso-clinico', ['record' => $this->getRecord()])),
            EditAction::make(),
        ];
    }
}
