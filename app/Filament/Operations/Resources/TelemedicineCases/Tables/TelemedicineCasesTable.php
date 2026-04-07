<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\Tables;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

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
            ->recordClasses(function ($record): array {
                $name = $record->priority?->name;
                $classes = match ($name) {
                    'NO URGENTE' => 'bg-[#005ca9]/10 dark:bg-[#005ca9]/20 border-l-4 border-[#005ca9]',
                    'ESTANDAR' => 'bg-[#02976d]/10 dark:bg-[#02976d]/20 border-l-4 border-[#02976d]',
                    'URGENCIA' => 'bg-[#eab527]/10 dark:bg-[#eab527]/20 border-l-4 border-[#eab527]',
                    'EMERGENCIA' => 'bg-[#f17f29]/10 dark:bg-[#f17f29]/20 border-l-4 border-[#f17f29]',
                    'CRITICO' => 'bg-[#e4003b]/10 dark:bg-[#e4003b]/20 border-l-4 border-[#e4003b]',
                    default => 'border-l-4 border-gray-200 dark:border-gray-700',
                };

                return [$classes];
            })
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
