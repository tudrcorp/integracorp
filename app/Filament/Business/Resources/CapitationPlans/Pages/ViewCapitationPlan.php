<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CapitationPlans\Pages;

use App\Filament\Business\Resources\CapitationPlans\Actions\CapitationPlanActions;
use App\Filament\Business\Resources\CapitationPlans\CapitationPlanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewCapitationPlan extends ViewRecord
{
    protected static string $resource = CapitationPlanResource::class;

    public function getTitle(): string|Htmlable
    {
        return (string) $this->getRecord()->getAttribute('name');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Editar'),
            CapitationPlanActions::pdf(),
            CapitationPlanActions::send(),
            CapitationPlanActions::delete(),
        ];
    }
}
