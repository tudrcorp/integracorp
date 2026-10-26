<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\MedicalQuestionnaires\Pages;

use App\Filament\Business\Resources\MedicalQuestionnaires\MedicalQuestionnaireResource;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireAudit;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMedicalQuestionnaire extends CreateRecord
{
    protected static string $resource = MedicalQuestionnaireResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'created_by' => Auth::user()?->name, 'updated_by' => Auth::user()?->name];
    }

    protected function afterCreate(): void
    {
        MedicalQuestionnaireAudit::log($this->getRecord(), [], 'AUDIT_BUSINESS_MEDICAL_QUESTIONNAIRE_CREATED');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Cuestionario creado: sus preguntas ya aparecen en los formularios de afiliación de ese plan.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
