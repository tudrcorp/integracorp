<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys\Pages;

use App\Filament\Operations\Resources\QualityControlSurveys\Actions\QualityControlSurveyActions;
use App\Filament\Operations\Resources\QualityControlSurveys\QualityControlSurveyResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

abstract class ViewQualityControlSurvey extends ViewRecord
{
    public function getTitle(): string|Htmlable
    {
        return 'Encuesta de '.$this->getRecord()->getAttribute('patient_name');
    }

    protected function getHeaderActions(): array
    {
        /** @var class-string<QualityControlSurveyResource> $resource */
        $resource = static::getResource();

        return [
            QualityControlSurveyActions::addNote($resource),
            ActionGroup::make([
                QualityControlSurveyActions::previewPdf($resource),
                QualityControlSurveyActions::emailPdf($resource),
                QualityControlSurveyActions::whatsAppPdf($resource),
            ])
                ->label('PDF')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('info')
                ->button(),
            EditAction::make()->label('Editar'),
            EditQualityControlSurvey::deleteSurveyAction(),
        ];
    }
}
