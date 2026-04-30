<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Tables;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TelemedicineCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Casos de telemedicina')
            // ->description('Listado con el mismo estilo que el escritorio. Solo ve sus casos asignados. Los casos salen de la lista cuando el caso está en ALTA MÉDICA (TDG y resto). En ATENMEDI además se ocultan casos con alguna consulta en alta médica o con traslado en ambulancia en alguna consulta.')
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin casos para mostrar')
            ->emptyStateDescription('No hay casos que cumplan los filtros de su perfil, o aún no tiene asignaciones.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->recordActionsColumnLabel('')
            ->modifyQueryUsing(function (Builder $query): Builder {
                return TelemedicineCaseFilamentListQuery::applyTelemedicinaResourceCasesConstraints(
                    $query->with(['telemedicineDoctor', 'telemedicinePatient', 'priority'])
                );
            })
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios',
            ])
            ->columns([
                TextColumn::make('code')
                    ->label('Nro. de caso')
                    ->alignStart()
                    ->badge()
                    ->icon('healthicons-f-health-literacy')
                    ->color('success')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->tooltip('Código del caso')
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->badge()
                    ->icon('healthicons-f-boy-1015y')
                    ->color('primary')
                    ->description(fn (TelemedicineCase $record): string => 'Dr(a). '.($record->telemedicineDoctor?->full_name ?? '—'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('patient_name', 'like', "%{$search}%")
                                ->orWhereHas('telemedicinePatient', function (Builder $patient) use ($search): void {
                                    $patient->where('full_name', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->wrap()
                    ->extraCellAttributes(['class' => 'py-3 max-w-[14rem] sm:max-w-xs']),
                TextColumn::make('patient_age')
                    ->label('Edad')
                    ->description(fn (TelemedicineCase $record): string => (string) ($record->patient_sex ?? '—'))
                    ->suffix(' años')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('patient_phone')
                    ->label('Teléfono')
                    ->iconColor('primary')
                    ->icon('heroicon-s-phone')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('patient_address')
                    ->label('Dirección')
                    ->toggleable()
                    ->wrap()
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3 max-w-[12rem]']),
                TextColumn::make('assigned_by')
                    ->label('Asignado por')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(function (?string $state): string {
                        return match ($state) {
                            'ASIGNADO' => 'primary',
                            'EN SEGUIMIENTO' => 'warning',
                            'ALTA MEDICA' => 'success',
                            default => 'gray',
                        };
                    })
                    ->icon(function (?string $state): string {
                        return match ($state) {
                            'ASIGNADO' => 'healthicons-f-i-note-action',
                            'EN SEGUIMIENTO' => 'healthicons-f-i-note-action',
                            'ALTA MEDICA' => 'healthicons-f-i-documents-accepted',
                            default => 'heroicon-m-question-mark-circle',
                        };
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
                    ->badge()
                    ->icon('heroicon-s-calendar')
                    ->color('primary')
                    ->date()
                    ->sortable()
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
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver detalle')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('primary'),
                    EditAction::make()
                        ->label('Editar')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->color('gray'),
                    Action::make('add_follow_up')
                        ->label('Hacer seguimiento')
                        ->icon('healthicons-f-health-literacy')
                        ->color('success')
                        ->action(function (TelemedicineCase $record): mixed {
                            $case = TelemedicineCase::query()->where('code', $record->code)->first();
                            $patient = TelemedicinePatient::query()->whereKey($record->telemedicine_patient_id)->first();

                            if ($case === null || $patient === null) {
                                return null;
                            }

                            $exitRecord = TelemedicineHistoryPatient::query()
                                ->where('telemedicine_patient_id', $record->telemedicine_patient_id)
                                ->exists();

                            session()->forget('case');
                            session()->forget('patient');
                            session()->forget('exit_record');

                            session(['case' => $case]);
                            session(['patient' => $patient]);
                            session(['exit_record' => $exitRecord]);

                            return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
                        })
                        ->hidden(fn (TelemedicineCase $record): bool => $record->status !== 'EN SEGUIMIENTO'),
                ])
                    ->icon(Heroicon::OutlinedEllipsisHorizontalCircle)
                    ->tooltip('Acciones del caso')
                    ->button()
                    ->label('Más'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
