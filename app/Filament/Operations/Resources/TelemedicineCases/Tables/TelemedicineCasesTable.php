<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\Tables;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\CaseFollowUpChatManager;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TelemedicineCasesTable
{
    public static function configure(Table $table): Table
    {
        $openPatientSummary = Action::make('openPatientSummaryFromCase')
            ->label('Paciente')
            ->modalHeading(fn (TelemedicineCase $record): string => 'Paciente — caso '.$record->code)
            ->modalDescription('Identificación y contacto, ubicación del expediente y, si aplica, plan y datos de afiliación.')
            ->modalIcon(Heroicon::OutlinedUserCircle)
            ->modalIconColor('primary')
            ->modalContent(fn (TelemedicineCase $record): View => view(
                'filament.operations.telemedicine-cases.patient-summary-modal',
                ['record' => $record]
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalWidth(Width::FiveExtraLarge)
            ->closeModalByClickingAway(false);

        return $table
            ->query(
                TelemedicineCase::query()
                    ->where('status', '!=', 'ALTA MEDICA')
                    ->orderBy('created_at', 'desc')
            )
            ->modifyQueryUsing(function (Builder $query): Builder {
                if (in_array('ATENMEDI', Auth::user()?->departament ?? [], true)) {
                    $query->where('managed_by', 'ATENMEDI');
                }

                OperationsSupplierScope::applyToQuery($query);

                return $query;
            })
            ->heading('Casos de Telemedicina')
            ->description('Listado de casos de Telemedicina, desde aqui puedes ver el detalle del caso registrar y seguimientos')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('managed_by')
                    ->label('Pertenece a')
                    ->icon('heroicon-o-building-office-2')
                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('code')
                    ->label('Numero de Caso')
                    ->badge()
                    ->color('primary')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->tooltip('Ver resumen del paciente en este caso')
                    ->action($openPatientSummary)
                    ->extraAttributes([
                        'class' => 'cursor-pointer underline decoration-dotted underline-offset-2 hover:opacity-90 active:opacity-75',
                    ]),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->description(fn ($record): string => filled($record->telemedicineDoctor?->full_name)
                        ? 'Asignado a Dr(a):'.$record->telemedicineDoctor->full_name
                        : 'Sin médico asignado')
                    ->sortable(),
                TextColumn::make('patient_age')
                    ->label('Edad')
                    ->searchable(),
                TextColumn::make('patient_sex')
                    ->label('Sexo')
                    ->searchable(),
                TextColumn::make('patient_phone')
                    ->label('Numero de Teléfono')
                    ->searchable(),
                TextColumn::make('patient_address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('assigned_by')
                    ->label('Asignado por:')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(function (?string $state): string {
                        return match ($state) {
                            'ASIGNADO' => 'primary',
                            'EN SEGUIMIENTO' => 'warning',
                            'ALTA MEDICA' => 'success',
                            'TPA/RETAIL' => 'info',
                            default => 'gray',
                        };
                    })
                    ->icon(function (?string $state): string {
                        return match ($state) {
                            'ASIGNADO' => 'healthicons-f-i-note-action',
                            'EN SEGUIMIENTO' => 'healthicons-f-i-note-action',
                            'ALTA MEDICA' => 'healthicons-f-i-documents-accepted',
                            'TPA/RETAIL' => 'heroicon-s-clipboard-document-check',
                            default => 'healthicons-f-i-note-action',
                        };
                    })
                    ->searchable(),
                TextColumn::make('priority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn (?string $state): string => TelemedicinePriorityFilamentBadge::color((string) $state))
                    ->icon(fn (?string $state): string => TelemedicinePriorityFilamentBadge::icon((string) $state))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn (TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Ultima Actualización')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn (TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
            ])
            ->recordClasses(fn ($record): array => [TelemedicinePriorityFilamentBadge::recordRowClasses($record->priority?->name)])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('openCaseFollowUpChat')
                    ->label('Chat')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('info')
                    ->tooltip('Abrir chat de seguimiento para este caso')
                    ->visible(fn (TelemedicineCase $record): bool => $record->status === CaseFollowUpChatManager::FOLLOW_UP_STATUS
                        && CaseFollowUpChatManager::canAccessCase(Auth::user(), $record))
                    ->action(function (TelemedicineCase $record, $livewire): void {
                        $livewire->dispatch('operations-case-chat-open', caseId: $record->id);
                    }),
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver detalle')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('primary'),
                    Action::make('openCaseFollowUpChatFromMenu')
                        ->label('Chat de seguimiento')
                        ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                        ->color('info')
                        ->visible(fn (TelemedicineCase $record): bool => $record->status === CaseFollowUpChatManager::FOLLOW_UP_STATUS
                            && CaseFollowUpChatManager::canAccessCase(Auth::user(), $record))
                        ->action(function (TelemedicineCase $record, $livewire): void {
                            $livewire->dispatch('operations-case-chat-open', caseId: $record->id);
                        }),
                ])
                    ->icon(Heroicon::OutlinedEllipsisHorizontalCircle)
                    ->tooltip('Más acciones')
                    ->button()
                    ->label('Más'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('reasignar_caso')
                        ->label('Reasignar a TDG')
                        ->color('success')
                        ->icon('heroicon-s-check-circle')
                        ->modalHeading('Confirmar reasignación de casos')
                        ->modalDescription('Los casos seleccionados pasarán de ATENMEDI a TDG. Debes indicar el motivo de la reasignación; quedará registrado en la bitácora de cada caso.')
                        ->modalIcon(Heroicon::OutlinedArrowsRightLeft)
                        ->modalIconColor('warning')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalSubmitActionLabel('Sí, reasignar a TDG')
                        ->modalCancelActionLabel('Cancelar')
                        ->deselectRecordsAfterCompletion()
                        ->closeModalByClickingAway(false)
                        ->form([
                            Textarea::make('reassignment_observation')
                                ->label('Motivo de la reasignación')
                                ->placeholder('Ej.: Coordinación con TDG por complejidad del caso, solicitud del cliente, escalamiento operativo…')
                                ->helperText('Campo obligatorio. Mínimo 10 caracteres. Se guardará en la bitácora de observaciones de cada caso seleccionado.')
                                ->required()
                                ->minLength(10)
                                ->maxLength(5000)
                                ->rows(4)
                                ->columnSpanFull()
                                ->validationMessages([
                                    'required' => 'Debes indicar el motivo de la reasignación.',
                                    'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                                ]),
                        ])
                        ->action(function (Collection $records, array $data) {
                            try {
                                $observationText = trim((string) ($data['reassignment_observation'] ?? ''));
                                $bitacoraDescription = TelemedicineCaseTdgReassignmentCoordination::OBSERVATION_PREFIX."\n".'Motivo: '.$observationText;
                                $userId = Auth::id();
                                $userName = (string) (Auth::user()?->name ?? 'SISTEMA');

                                $records->each(function (TelemedicineCase $record) use ($bitacoraDescription, $userId, $userName): void {
                                    $record->update([
                                        'managed_by' => 'TDG',
                                    ]);

                                    ObservationCase::query()->create([
                                        'telemedicine_case_id' => $record->id,
                                        'description' => $bitacoraDescription,
                                        'created_by' => $userId !== null ? (string) $userId : null,
                                    ]);

                                    $latestConsultation = $record->consultations()
                                        ->with([
                                            'telemedicineServiceList:id,name',
                                            'telemedicineServiceListDrift:id,name',
                                        ])
                                        ->latest('id')
                                        ->first();

                                    $mainServiceName = $latestConsultation?->telemedicineServiceList?->name ?? 'NO ESPECIFICADO';
                                    $derivedServiceName = $latestConsultation?->telemedicineServiceListDrift?->name ?? 'NO ESPECIFICADO';
                                    $patient = $record->telemedicinePatient;
                                    $holderName = (string) ($patient?->full_name ?? $record->patient_name ?? 'NO ESPECIFICADO');
                                    $holderIdentification = (string) ($patient?->nro_identificacion ?? 'NO ESPECIFICADO');
                                    $patientRelationship = 'TITULAR';
                                    $contractor = $patient?->afilliation_id === null ? 'CORPORATIVO' : 'INDIVIDUAL';

                                    $coordination = OperationCoordinationService::query()->create([
                                        'telemedicine_patient_id' => $record->telemedicine_patient_id,
                                        'telemedicine_case_id' => $record->id,
                                        'telemedicine_doctor_id' => $record->telemedicine_doctor_id,
                                        'telemedicine_consultation_patient_id' => $latestConsultation?->id,
                                        'date_solicitud' => now(),
                                        'date_service' => now(),
                                        'reference_number' => $latestConsultation?->code_reference ?? $record->code,
                                        'status' => 'PENDIENTE',
                                        'holder' => $holderName,
                                        'ci_holder' => $holderIdentification,
                                        'patient' => (string) ($record->patient_name ?? $holderName),
                                        'ci_patient' => $holderIdentification,
                                        'relationship_patient' => $patientRelationship,
                                        'contractor' => $contractor,
                                        'symptoms_diagnosis' => (string) ($latestConsultation?->diagnostic_impression ?? $record->reason ?? 'NO ESPECIFICADO'),
                                        'servicie' => $mainServiceName,
                                        'specific_service' => $derivedServiceName,
                                        'bill_price' => 0.00,
                                        'observations' => $bitacoraDescription,
                                        'created_by' => $userName,
                                        'updated_by' => $userName,
                                        'managed_by' => 'TDG',
                                        'supplier_id' => $record->supplier_id,
                                    ]);

                                    TelemedicineCaseTdgReassignmentCoordination::seedAmdManagementItemFromCaseReassignment(
                                        $coordination,
                                        $record,
                                        $latestConsultation,
                                    );
                                });

                                $casesCount = $records->count();
                                Notification::make()
                                    ->title($casesCount === 1 ? 'Caso reasignado a TDG exitosamente' : 'Casos reasignados a TDG exitosamente')
                                    ->body($casesCount === 1
                                        ? 'La gestión del caso ahora corresponde a TDG y el motivo quedó en la bitácora.'
                                        : "La gestión de {$casesCount} casos ahora corresponde a TDG y el motivo quedó en la bitácora de cada uno.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $th) {
                                Notification::make()
                                    ->title('Error al reasignar el caso')
                                    ->body('No se pudo completar la reasignación. Intenta nuevamente.')
                                    ->danger()
                                    ->send();

                                throw $th;
                            }
                        })->hidden(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament ?? [], true)),
                    BulkAction::make('reasignar_doctor')
                        ->label('Reasignar Doctor')
                        ->color('success')
                        ->icon('heroicon-s-check-circle')
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar reasignación de doctor')
                        ->modalDescription('Selecciona el doctor TDG que asumirá los casos elegidos. Esta acción actualizará el médico tratante de todos los casos seleccionados.')
                        ->modalIcon(Heroicon::OutlinedUserPlus)
                        ->modalIconColor('warning')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalSubmitActionLabel('Sí, reasignar doctor')
                        ->modalCancelActionLabel('Cancelar')
                        ->deselectRecordsAfterCompletion()
                        ->closeModalByClickingAway(false)
                        ->form([
                            Select::make('doctor_id')
                                ->label('Seleccione el Doctor')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->placeholder('Selecciona un doctor TDG')
                                ->helperText('Solo se muestran doctores gestionados por TDG.')
                                ->options(TelemedicineDoctor::where('managed_by', 'TDG')->pluck('full_name', 'id')),
                        ])
                        ->action(function (Collection $records, array $data) {
                            try {
                                $records->each(function (TelemedicineCase $record) use ($data) {
                                    $record->update([
                                        'telemedicine_doctor_id' => $data['doctor_id'],
                                    ]);
                                });

                                $doctorName = TelemedicineDoctor::query()
                                    ->whereKey($data['doctor_id'])
                                    ->value('full_name') ?? 'seleccionado';
                                $casesCount = $records->count();

                                Notification::make()
                                    ->title($casesCount === 1 ? 'Caso reasignado exitosamente' : 'Casos reasignados exitosamente')
                                    ->body($casesCount === 1
                                        ? "El caso ahora está asignado al Dr(a). {$doctorName}."
                                        : "Los {$casesCount} casos ahora están asignados al Dr(a). {$doctorName}.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $th) {
                                Notification::make()
                                    ->title('Error al reasignar el caso')
                                    ->body('No se pudo completar la reasignación del doctor. Intenta nuevamente.')
                                    ->danger()
                                    ->send();

                                throw $th;
                            }
                        })
                        ->hidden(fn (): bool => in_array('ATENMEDI', Auth::user()?->departament ?? [], true)),
                ]),
            ]);
    }
}
