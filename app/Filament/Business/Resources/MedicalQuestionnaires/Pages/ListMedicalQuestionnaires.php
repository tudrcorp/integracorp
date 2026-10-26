<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\MedicalQuestionnaires\Pages;

use App\Filament\Business\Resources\MedicalQuestionnaires\MedicalQuestionnaireResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicalQuestionnaires extends ListRecords
{
    protected static string $resource = MedicalQuestionnaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo cuestionario'),
        ];
    }
}
