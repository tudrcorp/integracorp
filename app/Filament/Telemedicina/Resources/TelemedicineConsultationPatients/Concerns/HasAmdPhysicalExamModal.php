<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns;

use App\Models\TelemedicineAmdPhysicalExam;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamRegistrar;
use App\Support\Telemedicine\TelemedicineAmdPhysicalExamReport;
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
 *
 * Se anexa al informe de la consulta (Informe Médico o de Seguimiento, ver
 * {@see TelemedicineAmdPhysicalExamReport}); la UI se lo dice al médico antes
 * y después de guardar.
 */
trait HasAmdPhysicalExamModal
{
    public ?int $pendingAmdPhysicalExamId = null;

    protected function amdPhysicalExamAction(): Action
    {
        return Action::make('amdPhysicalExam')
            ->label('Examen físico AMD')
            ->modalHeading('Examen físico AMD')
            ->modalDescription(fn (): HtmlString => $this->amdPhysicalExamModalDescription())
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

                $consultation = $this->resolveConsultationForInformAmd();

                if (! $consultation instanceof TelemedicineConsultationPatient) {
                    $report = $this->amdPhysicalExamReportLabel() ?? 'informe de la consulta';

                    Notification::make()
                        ->title('Examen físico guardado')
                        ->body(trim("Al guardar la consulta se anexará al {$report} y quedará en la bitácora del caso. ".$this->amdNextStepMessage()))
                        ->success()
                        ->send();

                    return;
                }

                $this->refreshReportWithAmdPhysicalExam($consultation);
            });
    }

    /**
     * Informe al que se anexa el examen de esta consulta, o null si todavía no
     * se sabe si es la consulta inicial o un seguimiento.
     */
    public function amdPhysicalExamReportLabel(): ?string
    {
        $consultation = $this->resolveConsultationForInformAmd();
        $status = $consultation instanceof TelemedicineConsultationPatient
            ? $consultation->status
            : ($this->form->getRawState()['status'] ?? null);

        return TelemedicineAmdPhysicalExamReport::labelForStatus(is_string($status) ? $status : null);
    }

    protected function amdPhysicalExamModalDescription(): HtmlString
    {
        $report = e($this->amdPhysicalExamReportLabel() ?? 'informe de esta consulta');
        $when = $this->resolveConsultationForInformAmd() instanceof TelemedicineConsultationPatient
            ? 'Al guardar, el informe se actualiza solo con el examen incluido.'
            : 'Se incluirá al guardar la consulta.';

        return new HtmlString(
            '<div class="space-y-2">'
            .'<p class="text-sm text-gray-500 dark:text-gray-400">Cada sistema trae el hallazgo normal ya escrito: edite sólo lo que encuentre alterado. Los signos vitales son opcionales.</p>'
            .'<p class="flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-950/40 dark:text-sky-100">'
            .svg('heroicon-o-paper-clip', 'mt-0.5 h-4 w-4 shrink-0 text-sky-600 dark:text-sky-300')->toHtml()
            .'<span>Este examen se anexa al <strong>'.$report.'</strong>. '.e($when).'</span>'
            .'</p>'
            .'</div>'
        );
    }

    /**
     * Encola el informe de la consulta ya guardada para que salga con el examen.
     * El examen ya quedó guardado: si el informe no se puede encolar se avisa
     * cómo incluirlo, sin deshacer nada.
     */
    protected function refreshReportWithAmdPhysicalExam(TelemedicineConsultationPatient $consultation): void
    {
        $user = auth()->user();

        try {
            $report = $user instanceof User
                ? TelemedicineAmdPhysicalExamReport::queueReportRefresh($consultation, $user)
                : null;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Examen físico guardado, informe sin actualizar')
                ->body('El examen quedó guardado en la bitácora del caso, pero el informe no se pudo actualizar. Use «Generar documentos» en el caso para incluirlo.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Examen físico guardado')
            ->body(trim(($report !== null
                ? "Estamos actualizando el {$report} con el examen anexado. Le avisaremos cuando esté listo en los documentos del caso."
                : 'Quedó en la bitácora del caso. Se anexará al informe cuando la consulta tenga los datos para emitirlo.')
                .' '.$this->amdNextStepMessage()))
            ->success()
            ->send();
    }

    /**
     * Qué le falta de la visita AMD, para cerrar cada mensaje con el siguiente
     * paso. Vacío si la página no calcula el avance.
     */
    protected function amdNextStepMessage(): string
    {
        return method_exists($this, 'amdVisitProgress') ? $this->amdVisitProgress()->nextStepMessage() : '';
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
