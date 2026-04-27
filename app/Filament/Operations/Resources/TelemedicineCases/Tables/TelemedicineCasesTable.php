<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\Tables;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TelemedicineCasesTable
{
    public static function configure(Table $table): Table
    {
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
                    ->searchable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->description(fn ($record): string => 'Asignado a Dr(a):'.$record->telemedicineDoctor->full_name)
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
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ASIGNADO' => 'primary',
                            'EN SEGUIMIENTO' => 'warning',
                            'ALTA MEDICA' => 'success',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'ASIGNADO' => 'healthicons-f-i-note-action',
                            'EN SEGUIMIENTO' => 'healthicons-f-i-note-action',
                            'ALTA MEDICA' => 'healthicons-f-i-documents-accepted',
                        };
                    })
                    ->searchable(),
                TextColumn::make('priority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (string $state): string => TelemedicinePriorityFilamentBadge::color($state))
                    ->icon(fn (string $state): string => TelemedicinePriorityFilamentBadge::icon($state))
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
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('reasignar_caso')
                        ->label('Reasignar Caso')
                        ->color('success')
                        ->icon('heroicon-s-check-circle')
                        ->requiresConfirmation()
                        ->form([
                            Fieldset::make('Reasignar Caso')->schema([
                                Select::make('doctor_id')
                                    ->label('Seleccione el Doctor')
                                    ->required()
                                    ->options(TelemedicineDoctor::all()->pluck('full_name', 'id')),

                            ])->columnSpanFull()->columns(1),
                        ])
                        ->action(function (Collection $records, array $data) {

                            try {
                                $records->each(function (TelemedicineCase $record) use ($data) {
                                    $record->update([
                                        'telemedicine_doctor_id' => $data['doctor_id'],
                                    ]);
                                });

                                Notification::make()
                                    ->title('Caso reasignado exitosamente')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $th) {
                                throw $th;
                                Notification::make()
                                    ->title('Error al reasignar el caso')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
