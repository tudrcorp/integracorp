<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\MedicalQuestionnaires\Pages;

use App\Filament\Business\Resources\MedicalQuestionnaires\MedicalQuestionnaireResource;
use App\Models\MedicalQuestionnaire;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireAudit;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditMedicalQuestionnaire extends EditRecord
{
    protected static string $resource = MedicalQuestionnaireResource::class;

    /**
     * Preguntas tal como estaban al abrir la página, para la auditoría del cambio.
     *
     * @var list<array<string, mixed>>
     */
    public array $questionsBefore = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('PDF de preguntas')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn (MedicalQuestionnaire $record): string => route('business.medical-questionnaires.pdf', $record))
                ->openUrlInNewTab(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->questionsBefore = MedicalQuestionnaireAudit::questionsSnapshot($this->getRecord());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, 'updated_by' => Auth::user()?->name];
    }

    protected function afterSave(): void
    {
        MedicalQuestionnaireAudit::log($this->getRecord(), $this->questionsBefore, 'AUDIT_BUSINESS_MEDICAL_QUESTIONNAIRE_UPDATED');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Cuestionario guardado: los formularios de afiliación ya muestran las preguntas nuevas.';
    }
}
