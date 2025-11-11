<?php

namespace App\Filament\Resources\TelemedicineCases\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use App\Models\TelemedicineDoctor;
use Illuminate\Support\Collection;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;

class TelemedicineCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Casos de Telemedicina')
            ->description('Listado de casos de Telemedicina, desde aqui puedes ver el detalle del caso registrar y seguimientos')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Numero de Caso')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->description(fn($record): string => 'Asignado a Dr(a):' . $record->telemedicineDoctor->full_name)
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
                // TextColumn::make('city.definition')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('state.definition')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('country.name')
                //     ->numeric()
                //     ->sortable(),
                TextColumn::make('assigned_by')
                    ->label('Asignado por:')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ASIGNADO'          => 'primary',
                            'EN SEGUIMIENTO'    => 'warning',
                            'ALTA MEDICA'       => 'success',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'ASIGNADO'          => 'healthicons-f-i-note-action',
                            'EN SEGUIMIENTO'    => 'healthicons-f-i-note-action',
                            'ALTA MEDICA'       => 'healthicons-f-i-documents-accepted',
                        };
                    })
                    ->searchable(),
                TextColumn::make('priority.name')
                    ->label('Prioridad')
                    ->badge()
                    // ->color(function (string $state): string {
                    //     return match ($state) {
                    //         'ASIGNADO'          => 'primary',
                    //         'EN SEGUIMIENTO'    => 'warning',
                    //         'ALTA MEDICA'       => 'success',
                    //     };
                    // })
                    // ->icon(function (string $state): string {
                    //     return match ($state) {
                    //         'ASIGNADO'          => 'healthicons-f-i-note-action',
                    //         'EN SEGUIMIENTO'    => 'healthicons-f-i-note-action',
                    //         'ALTA MEDICA'       => 'healthicons-f-i-documents-accepted',
                    //     };
                    // })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn(TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Ultima Actualización')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn(TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
            ])
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
                                        'telemedicine_doctor_id' => $data['doctor_id']
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