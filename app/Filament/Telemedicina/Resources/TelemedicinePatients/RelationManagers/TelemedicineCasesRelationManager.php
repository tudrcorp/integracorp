<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\RelationManagers;

use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TelemedicineCasesRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicineCases';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $title = 'Histórico de casos';

    protected static string|BackedEnum|null $icon = 'healthicons-f-health-literacy';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Casos del paciente')
            ->description('Todos los casos de telemedicina vinculados a esta ficha. Abra el detalle para consultas y seguimientos.')
            ->emptyStateHeading('Sin casos')
            ->emptyStateDescription('Este paciente aún no tiene casos registrados en telemedicina.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->recordActionsColumnLabel('')
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios telemedicine-patient-cases-relation-table',
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'telemedicineDoctor',
                'telemedicinePatient',
                'priority',
            ]))
            ->columns([
                TextColumn::make('code')
                    ->label('Nro. de caso')
                    ->badge()
                    ->icon('healthicons-f-health-literacy')
                    ->color('success')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Médico asignado')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->placeholder('—')
                    ->wrap()
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3 max-w-[14rem]']),
                TextColumn::make('patient_age')
                    ->label('Edad')
                    ->suffix(' años')
                    ->description(fn (TelemedicineCase $record): string => (string) ($record->patient_sex ?? '—'))
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('patient_phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->searchable()
                    ->placeholder('—')
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('patient_address')
                    ->label('Dirección')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->wrap()
                    ->searchable()
                    ->tooltip(fn (TelemedicineCase $record): ?string => filled($record->patient_address)
                        ? trim((string) $record->patient_address)
                        : null)
                    ->extraHeaderAttributes([
                        'class' => 'telemedicine-case-address-column min-w-[16rem]',
                    ])
                    ->extraCellAttributes([
                        'class' => 'telemedicine-case-address-column py-3 align-top min-w-[16rem] max-w-[22rem] whitespace-normal',
                    ]),
                TextColumn::make('assigned_by')
                    ->label('Asignado por')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ASIGNADO' => 'primary',
                        'EN SEGUIMIENTO' => 'warning',
                        'ALTA MEDICA' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        'ASIGNADO' => 'healthicons-f-i-note-action',
                        'EN SEGUIMIENTO' => 'healthicons-f-i-note-action',
                        'ALTA MEDICA' => 'healthicons-f-i-documents-accepted',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('priority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (?string $state): string => TelemedicinePriorityFilamentBadge::color($state ?? ''))
                    ->icon(fn (?string $state): string => TelemedicinePriorityFilamentBadge::icon($state ?? ''))
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('created_at')
                    ->label('Asignación')
                    ->date('d/m/Y')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->description(fn (TelemedicineCase $record): string => $record->updated_at?->diffForHumans() ?? '—')
                    ->sortable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable()
                    ->extraCellAttributes(['class' => 'py-3']),
            ])
            ->recordClasses(fn (TelemedicineCase $record): array => [
                TelemedicinePriorityFilamentBadge::recordRowClasses($record->priority?->name),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver detalle')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('primary')
                        ->url(fn (TelemedicineCase $record): string => TelemedicineCaseResource::getUrl('view', [
                            'record' => $record->getKey(),
                        ]).'?from=patient'),
                    Action::make('view_consultations')
                        ->label('Ver consultas')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->color('gray')
                        ->url(fn (TelemedicineCase $record): string => TelemedicineCaseResource::getUrl('view', [
                            'record' => $record->getKey(),
                        ]).'?relation=consultations&from=patient'),
                    Action::make('add_follow_up')
                        ->label('Hacer seguimiento')
                        ->icon('healthicons-f-health-literacy')
                        ->color('success')
                        ->action(function (TelemedicineCase $record): mixed {
                            $case = TelemedicineCase::query()->whereKey($record->getKey())->first();
                            $patient = TelemedicinePatient::query()->whereKey($record->telemedicine_patient_id)->first();

                            if ($case === null || $patient === null) {
                                return null;
                            }

                            $exitRecord = TelemedicineHistoryPatient::query()
                                ->where('telemedicine_patient_id', $record->telemedicine_patient_id)
                                ->exists();

                            session()->forget(['case', 'patient', 'exit_record']);

                            session([
                                'case' => $case,
                                'patient' => $patient,
                                'exit_record' => $exitRecord,
                            ]);

                            return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
                        })
                        ->hidden(fn (TelemedicineCase $record): bool => $record->status !== 'EN SEGUIMIENTO'),
                ])
                    ->icon(Heroicon::OutlinedEllipsisHorizontalCircle)
                    ->tooltip('Acciones del caso')
                    ->button()
                    ->label('Más'),
            ]);
    }
}
