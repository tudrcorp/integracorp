<?php

namespace App\Filament\Telemedicina\Widgets;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Models\ObservationCase;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TelemedicineCaseTableDash extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    /**
     * Última consulta del caso: abre el asistente de creación (mismo flujo que «Hacer seguimiento»),
     * con paso 1 cargado desde caso y paciente en sesión, luego el wizard definido en el formulario.
     */
    public function redirectToConsultationCreateWizard(int $consultationId): mixed
    {
        $user = Auth::user();

        if ($user === null || $user->doctor_id === null) {
            Notification::make()
                ->title('Sesión inválida')
                ->danger()
                ->send();

            return null;
        }

        $consultation = TelemedicineConsultationPatient::query()->find($consultationId);

        if ($consultation === null) {
            Notification::make()
                ->title('Consulta no encontrada')
                ->danger()
                ->send();

            return null;
        }

        $case = TelemedicineCase::query()
            ->whereKey($consultation->telemedicine_case_id)
            ->where('telemedicine_doctor_id', $user->doctor_id)
            ->first();

        if ($case === null) {
            Notification::make()
                ->title('No autorizado')
                ->danger()
                ->send();

            return null;
        }

        if ($case->status === 'ALTA MEDICA') {
            Notification::make()
                ->title('El caso está en alta médica')
                ->warning()
                ->send();

            return null;
        }

        $last = TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderByDesc('id')
            ->first();

        if ($last === null || $last->id !== $consultation->id) {
            Notification::make()
                ->title('Solo la última consulta permite actualizar con un nuevo registro')
                ->warning()
                ->send();

            return null;
        }

        $patient = TelemedicinePatient::query()->whereKey($consultation->telemedicine_patient_id)->first();

        if ($patient === null) {
            Notification::make()
                ->title('Paciente no encontrado')
                ->danger()
                ->send();

            return null;
        }

        session()->forget('case');
        session()->forget('patient');
        session()->forget('exit_record');
        session()->forget('action');
        session()->forget('status');
        session()->forget('consultation');

        $exitRecord = TelemedicineHistoryPatient::query()
            ->where('telemedicine_patient_id', $patient->id)
            ->exists();

        session(['case' => $case]);
        session(['patient' => $patient]);
        session(['exit_record' => $exitRecord]);

        $consultationForSession = TelemedicineConsultationPatient::query()
            ->whereKey($last->id)
            ->with([
                'telemedicineServiceList',
                'telemedicineServiceListDrift',
                'telemedicinePriority',
            ])
            ->first();

        if ($consultationForSession !== null) {
            session(['consultation' => $consultationForSession]);
        }

        $this->unmountAction();

        return $this->redirect(
            route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id])
        );
    }

    public function table(Table $table): Table
    {
        $openCaseConsultationsAction = Action::make('openCaseConsultations')
            ->label('Consultas del caso')
            ->modalHeading(fn (TelemedicineCase $record): string => 'Consultas del caso '.$record->code)
            ->modalDescription('Orden: más recientes arriba. «Ver detalle» abre la ficha; «Actualizar» solo en la última consulta (si el caso no está en alta médica).')
            ->modalIcon(Heroicon::OutlinedClipboardDocumentList)
            ->modalIconColor('primary')
            ->modalContent(function (TelemedicineCase $record): View {
                $consultations = TelemedicineConsultationPatient::query()
                    ->where('telemedicine_case_id', $record->id)
                    ->with('telemedicineServiceList')
                    ->orderByDesc('id')
                    ->get();

                $lastConsultation = $consultations->first();
                $lastConsultationId = $lastConsultation?->id;

                $viewUrls = [];

                foreach ($consultations as $consultation) {
                    $viewUrls[$consultation->id] = TelemedicineConsultationPatientResource::getUrl('view', ['record' => $consultation]);
                }

                return view('filament.telemedicina.widgets.case-consultations-modal', [
                    'caseCode' => $record->code,
                    'consultations' => $consultations,
                    'lastConsultationId' => $lastConsultationId,
                    'canEditLast' => $record->status !== 'ALTA MEDICA',
                    'viewUrls' => $viewUrls,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalWidth('3xl')
            ->closeModalByClickingAway(false);

        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Pacientes asignados')
            ->description('Toca el número de caso para ver consultas. Usa ⋯ para historia, consulta o seguimiento.')
            ->emptyStateHeading('Sin casos asignados')
            ->emptyStateDescription('Cuando te asignen pacientes, aparecerán aquí con el mismo estilo de lista de iOS.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->recordActionsColumnLabel('')
            ->query(fn (): Builder => TelemedicineCase::query()->where('telemedicine_doctor_id', Auth::user()->doctor_id)->where('status', '!=', 'ALTA MEDICA'))
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios',
            ])
            ->modifyUngroupedRecordActionsUsing(function (Action $action): void {
                if ($action->getName() === 'openCaseConsultations') {
                    $action->extraAttributes([
                        'class' => 'hidden',
                        'aria-hidden' => 'true',
                    ]);
                }
            })
            ->columns([
                TextColumn::make('code')
                    ->label('Nro. de caso')
                    ->alignStart()
                    ->badge()
                    ->icon('healthicons-f-health-literacy')
                    ->color('success')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->tooltip('Consultas de este caso')
                    ->action($openCaseConsultationsAction)
                    ->extraCellAttributes([
                        'class' => 'py-3',
                    ])
                    ->extraAttributes([
                        'class' => 'cursor-pointer underline decoration-dotted underline-offset-2 hover:opacity-90 active:opacity-75',
                    ]),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->badge()
                    ->icon('healthicons-f-boy-1015y')
                    ->color('primary')
                    ->searchable()
                    ->wrap()
                    ->extraCellAttributes(['class' => 'py-3 max-w-[14rem] sm:max-w-xs']),
                TextColumn::make('patient_age')
                    ->label('Edad')
                    ->description(fn ($record): string => $record->patient_sex)
                    ->suffix(' años')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('patient_phone')
                    ->label('Teléfono')
                    ->iconColor('primary')
                    ->icon('heroicon-s-phone')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('created_at')
                    ->label('Asignación')
                    ->badge()
                    ->icon('heroicon-s-calendar')
                    ->color('primary')
                    ->date()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->icon('heroicon-s-check-circle')
                    ->color('warning')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('priority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->extraCellAttributes(['class' => 'py-3'])
                    ->color(function (string $state): string {
                        return match ($state) {
                            'NO URGENTE' => 'no-urgente',
                            'ESTANDAR' => 'estandar',
                            'URGENCIA' => 'urgencia',
                            'EMERGENCIA' => 'emergencia',
                            'CRITICO' => 'critico',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'NO URGENTE' => 'healthicons-f-health',
                            'ESTANDAR' => 'healthicons-f-health',
                            'URGENCIA' => 'healthicons-f-health',
                            'EMERGENCIA' => 'heroicon-c-shield-exclamation',
                            'CRITICO' => 'heroicon-c-shield-exclamation',

                        };
                    })
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->description(fn (TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable()
                    ->extraCellAttributes(['class' => 'py-3']),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                $openCaseConsultationsAction,
                ActionGroup::make([

                    // ...Actions History
                    Action::make('view_history')
                        ->label('Historia Clínica')
                        ->icon('heroicon-s-book-open')
                        ->color('primary')
                        ->action(function (TelemedicineCase $record) {
                            // dd($record);
                            $history = TelemedicineHistoryPatient::where('telemedicine_patient_id', $record->telemedicine_patient_id)->first();

                            // dd($record, $history, TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first());

                            if (isset($history)) {
                                return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.view', ['record' => $history->id]);
                            } else {
                                // Si no tiene historia, redirigir a crear historia
                                session()->put('patient', TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first());

                                return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.create', ['record' => $record->telemedicine_patient_id]);
                            }
                        }),

                    // ...Actions consultation
                    Action::make('consultation')
                        ->label('Consulta Inicial')
                        ->icon('healthicons-f-call-centre')
                        ->color('success')
                        ->disabled(function (TelemedicineCase $record) {
                            $case = TelemedicineConsultationPatient::where('telemedicine_case_code', $record->code)->exists();
                            // dd($record->status);
                            if ($case && $record->status == 'ATENDIDO') {
                                return true;
                            }

                            return false;
                        })
                        ->action(function (TelemedicineCase $record) {

                            $case = TelemedicineCase::where('code', $record->code)->first();
                            $patient = TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first();
                            $exit_record = TelemedicineHistoryPatient::where('telemedicine_patient_id', $record->telemedicine_patient_id)->exists();

                            session()->forget('case');
                            session()->forget('patient');
                            // session()->forget('exit_record');
                            session()->forget('redCode');
                            session()->forget('consultation');

                            // Almacenamos en la variable de sesion del usuario la informacion del caso y del paciente
                            session(['case' => $case]);
                            session(['patient' => $patient]);
                            // session(['exit_record' => $exit_record]);

                            return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);

                        })
                        ->hidden(function (TelemedicineCase $record) {
                            return $record->status != 'ASIGNADO';
                        }),

                    // ...Actions follow up
                    Action::make('add_follow_up')
                        ->label('Hacer Seguimiento')
                        ->icon('healthicons-f-health-literacy')
                        ->color('success')
                        ->action(function (TelemedicineCase $record) {
                            $case = TelemedicineCase::where('code', $record->code)->first();
                            $patient = TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first();
                            $exit_record = TelemedicineHistoryPatient::where('telemedicine_patient_id', $record->telemedicine_patient_id)->exists();

                            session()->forget('case');
                            session()->forget('patient');
                            session()->forget('exit_record');
                            session()->forget('consultation');

                            // Almacenamos en la variable de sesion del usuario la informacion del caso y del paciente
                            session(['case' => $case]);
                            session(['patient' => $patient]);
                            session(['exit_record' => $exit_record]);

                            Log::info(session()->get('case'));
                            Log::info(session()->get('patient'));
                            Log::info(session()->get('exit_record'));

                            return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
                        })
                        ->hidden(function (TelemedicineCase $record) {
                            return $record->status != 'EN SEGUIMIENTO';
                        }),

                    Action::make('view_last')
                        ->label('Ver ultimo Seguimiento')
                        ->icon('heroicon-s-eye')
                        ->color('')
                        ->action(function (TelemedicineCase $record) {

                            $last = TelemedicineConsultationPatient::where('telemedicine_case_id', $record->id)->latest()->first();

                            return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.view', ['record' => $last->id]);

                        })
                        ->hidden(function (TelemedicineCase $record) {
                            $last = TelemedicineConsultationPatient::where('telemedicine_case_id', $record->id)->latest()->first();
                            if ($last == null) {
                                return true;
                            }

                            return false;
                        }),

                    Action::make('addObservation')
                        ->label('Agregar Observaciones')
                        ->icon('heroicon-s-hand-raised')
                        ->color('warning')
                        ->modalWidth(Width::Large)
                        ->modalHeading('Observaciones del caso')
                        ->modalDescription('Registra una nota asociada a este caso. Quedará vinculada al historial para el equipo clínico y operaciones.')
                        ->modalSubmitActionLabel('Registrar observación')
                        ->modalIcon('heroicon-s-hand-raised')
                        ->modalIconColor('warning')
                        ->modalSubmitAction(
                            fn (Action $action) => $action
                                ->color('warning')
                                ->extraAttributes([
                                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                                ])
                        )
                        ->modalCancelAction(
                            fn (Action $action) => $action
                                ->color('gray')
                                ->extraAttributes([
                                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                                ])
                        )
                        ->modalCancelActionLabel('Cancelar')
                        ->extraModalWindowAttributes([
                            'class' => 'fi-telemedicine-observation-modal-window',
                        ], merge: false)
                        ->form([
                            Textarea::make('observation')
                                ->label('Texto de la observación')
                                ->placeholder('Contexto clínico, acuerdos, seguimiento pendiente o notas administrativas…')
                                ->helperText('Mínimo 2 caracteres. Evita datos sensibles innecesarios; usa lenguaje profesional.')
                                ->required()
                                ->minLength(2)
                                ->maxLength(5000)
                                ->rows(6)
                                ->columnSpanFull(),
                        ])
                        ->action(function (TelemedicineCase $record, array $data) {

                            try {
                                $observation = new ObservationCase;
                                $observation->description = $data['observation'];
                                $observation->telemedicine_case_id = $record->id;
                                $observation->created_by = Auth::user()->id;

                                $observation->save();

                                Notification::make()
                                    ->body('Las observaciones fueron registradas exitosamente.')
                                    ->success()
                                    ->send();

                            } catch (\Throwable $th) {
                                Log::error($th->getMessage());
                                Notification::make()
                                    ->body('Ocurrió un error al registrar las observaciones.')
                                    ->danger()
                                    ->send();
                            }

                        })
                        ->hidden(function (TelemedicineCase $record) {
                            return $record->status == 'EJECUTADA' || $record->status == 'APROBADA';
                        }),

                ])
                    ->icon(Heroicon::OutlinedEllipsisHorizontalCircle)
                    ->iconSize(IconSize::Large)
                    ->color('gray')
                    ->tooltip('Opciones del caso'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ])
            ->poll('5s');
    }
}
