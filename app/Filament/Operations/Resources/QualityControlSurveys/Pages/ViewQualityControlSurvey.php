<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class ViewQualityControlSurvey extends ViewRecord
{
    public function getTitle(): string|Htmlable
    {
        return 'Encuesta de '.$this->getRecord()->getAttribute('patient_name');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Editar'),
            EditQualityControlSurvey::deleteSurveyAction(),
        ];
    }
}
