<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns;

use App\Models\TelemedicineAmdPhysicalExam;
use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamRegistrar;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamTemplate;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Examen físico AMD: signos vitales (opcionales) y exploración por sistemas
 * con el texto normal ya escrito. Uno por consulta; reabrirlo edita el mismo.
 */
trait HasAmdPhysicalExamModal
{
    public ?int $pendingAmdPhysicalExamId = null;

    protected function amdPhysicalExamAction(): Action
    {
        return Action::make('amdPhysicalExam')
            ->label('Examen físico AMD')
            ->modalHeading('Examen físico AMD')
            ->modalDescription(new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Cada sistema trae el hallazgo normal ya escrito: edite sólo lo que encuentre alterado. Los signos vitales son opcionales.</p>'))
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Guardar examen físico')
            ->form([
                Section::make('Signos vitales')
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 5])
                            ->schema(collect(TelemedicineAmdPhysicalExamTemplate::VITALS)
                                ->map(fn (array $vital, string $column): TextInput => TextInput::make($column)
                                    ->label($vital[0])
                                    ->suffix($vital[1])
                                    ->maxLength(TelemedicineAmdPhysicalExamTemplate::VITAL_MAX_LENGTH))
                                ->values()
                                ->all()),
                    ])
                    ->columnSpanFull(),
                Section::make('Exploración física por sistemas')
                    ->compact()
                    ->schema(collect(TelemedicineAmdPhysicalExamTemplate::SYSTEMS)
                        ->map(fn (array $system, string $column): Textarea => Textarea::make($column)
                            ->label($system[0])
                            ->rows(2)
                            ->autosize()
                            ->maxLength(3000)
                            // Live con pausa: el enlace de restaurar aparece al editar sin recargar en cada tecla.
                            ->live(debounce: 600)
                            ->hintAction(
                                Action::make('restore_'.$column)
                                    ->label('Restaurar texto por defecto')
                                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                                    ->visible(fn (Get $get): bool => TelemedicineAmdPhysicalExamTemplate::isEdited($column, $get($column)))
                                    ->action(fn (Set $set) => $set($column, TelemedicineAmdPhysicalExamTemplate::defaultFor($column))),
                            ))
                        ->values()
                        ->all())
                    ->columnSpanFull(),
            ])
            ->fillForm(fn (): array => $this->amdPhysicalExamFormDefaults())
            ->action(function (array $data): void {
                $formState = $this->form->getRawState();

                if ((int) ($formState['telemedicine_service_list_id'] ?? 0) !== TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID) {
                    Notification::make()
                        ->title('Servicio no válido')
                        ->body('El examen físico AMD sólo aplica cuando el servicio principal es AMD.')
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    $exam = $this->saveAmdPhysicalExam($data, $formState);
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('No se pudo guardar el examen físico')
                        ->body('Intente nuevamente. Si el problema continúa, avise a soporte.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Examen físico guardado')
                    ->body($exam->telemedicine_consultation_patient_id !== null
                        ? 'Quedó en la bitácora del caso y en el Informe Médico Largo.'
                        : 'Al guardar la consulta quedará en la bitácora del caso y en el Informe Médico Largo.')
                    ->success()
                    ->send();
            });
    }

    public function hasAmdPhysicalExam(): bool
    {
        return $this->currentAmdPhysicalExam() instanceof TelemedicineAmdPhysicalExam;
    }

    /**
     * @return array<string, string|null>
     */
    protected function amdPhysicalExamFormDefaults(): array
    {
        $exam = $this->currentAmdPhysicalExam();

        if (! $exam instanceof TelemedicineAmdPhysicalExam) {
            return TelemedicineAmdPhysicalExamTemplate::defaults();
        }

        return collect(TelemedicineAmdPhysicalExamTemplate::columns())
            ->mapWithKeys(fn (string $column): array => [$column => $exam->getAttribute($column)])
            ->all();
    }

    protected function currentAmdPhysicalExam(): ?TelemedicineAmdPhysicalExam
    {
        $consultation = $this->resolveConsultationForInformAmd();

        if ($consultation instanceof TelemedicineConsultationPatient) {
            return TelemedicineAmdPhysicalExamRegistrar::forConsultation((int) $consultation->id);
        }

        $caseId = (int) (property_exists($this, 'case') && $this->case !== null ? $this->case->id : 0);

        return TelemedicineAmdPhysicalExamRegistrar::pendingForCase(
            $caseId,
            $this->pendingAmdPhysicalExamId ?? session()->get(TelemedicineAmdPhysicalExamRegistrar::SESSION_PENDING_EXAM_ID),
            auth()->id(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $formState
     */
    private function saveAmdPhysicalExam(array $data, array $formState): TelemedicineAmdPhysicalExam
    {
        $consultation = $this->resolveConsultationForInformAmd();

        if ($consultation instanceof TelemedicineConsultationPatient) {
            return TelemedicineAmdPhysicalExamRegistrar::save(
                context: [
                    'telemedicine_patient_id' => (int) $consultation->telemedicine_patient_id,
                    'telemedicine_case_id' => (int) $consultation->telemedicine_case_id,
                    'telemedicine_doctor_id' => $consultation->telemedicine_doctor_id,
                ],
                data: $data,
                consultation: $consultation,
            );
        }

        $context = $this->informAmdPendingContext($formState);

        $exam = TelemedicineAmdPhysicalExamRegistrar::save(
            context: [
                'telemedicine_patient_id' => (int) ($context['telemedicine_patient_id'] ?? 0),
                'telemedicine_case_id' => (int) ($context['telemedicine_case_id'] ?? 0),
                'telemedicine_doctor_id' => filled($context['telemedicine_doctor_id'] ?? null) ? (int) $context['telemedicine_doctor_id'] : null,
            ],
            data: $data,
            pendingExamId: $this->pendingAmdPhysicalExamId ?? session()->get(TelemedicineAmdPhysicalExamRegistrar::SESSION_PENDING_EXAM_ID),
        );

        $this->pendingAmdPhysicalExamId = (int) $exam->id;
        session()->put(TelemedicineAmdPhysicalExamRegistrar::SESSION_PENDING_EXAM_ID, (int) $exam->id);

        return $exam;
    }
}
