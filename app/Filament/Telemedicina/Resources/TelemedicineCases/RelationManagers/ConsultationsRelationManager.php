<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ColumnGroup;
use App\Models\TelemedicineConsultationPatient;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;

class ConsultationsRelationManager extends RelationManager
{
    protected static string $relationship = 'consultations';

    protected static ?string $title = 'Gestión del Caso';

    protected static string|BackedEnum|null $icon = 'healthicons-f-blister-pills-oval-x14';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Gestión del Caso')
            ->description('Descripción detallada de todos los seguimiento y asignaciones del caso.')
            ->columns([
                TextColumn::make('telemedicine_case_code')
                    ->label('Numero de Caso')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('code_reference')
                    ->label('Referencia')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->description(fn($record): string => 'Atenido por: Dr(a):' . $record->telemedicineDoctor->full_name)
                    ->sortable(),
                TextColumn::make('nro_identificacion')
                    ->label('Número de Identificación')
                    ->prefix('V-')
                    ->alignCenter()
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('telemedicineServiceList.name')
                    ->label('Servicio')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-check')
                    ->searchable()
                    ->sortable(),
                ColumnGroup::make('LABORATORIOS Y ESTUDIOS CUBIERTOS', [
                    TextColumn::make('labs')
                        ->label('Laboratorio')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->labs ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->labs ? $record->labs : 'N/A';
                        })
                        ->searchable(),
                    TextColumn::make('studies')
                        ->label('Estudios')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->studies ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->studies ? $record->studies : 'N/A';
                        })
                        ->searchable(),
                    TextColumn::make('consult_specialist')
                        ->label('Consultas de Especialistas')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->consult_specialist ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->consult_specialist ? $record->consult_specialist : 'N/A';
                        })
                        ->searchable(),
                ]),

                ColumnGroup::make('LABORATORIOS Y ESTUDIOS NO CUBIERTOS', [
                    TextColumn::make('other_labs')
                        ->label('Otros Laboratorios')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->other_labs ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->other_labs ? $record->labs : 'N/A';
                        })
                        ->searchable(),

                    TextColumn::make('other_studies')
                        ->label('Otros Estudios')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->studies ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->studies ? $record->studies : 'N/A';
                        })
                        ->searchable(),

                    TextColumn::make('other_specialist')
                        ->label('Otros Especialistas')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->consult_specialist ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->consult_specialist ? $record->consult_specialist : 'N/A';
                        })
                        ->searchable(),
                ]),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(function (TelemedicineConsultationPatient $record) {
                        return $record->status == 'EN SEGUIMIENTO' ? 'warning' : 'success';
                    }),

                TextColumn::make('telemedicinePriority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ALTA'          => 'success',
                            'MEDIA'         => 'warning',
                            'BAJA'          => 'primary',
                            'EMERGENCIA'    => 'danger',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'ALTA'             => 'healthicons-f-health',
                            'MEDIA'            => 'healthicons-f-health',
                            'BAJA'             => 'healthicons-f-health',
                            'EMERGENCIA'       => 'heroicon-c-shield-exclamation',
                        };
                    })
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn(TelemedicineConsultationPatient $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                ->icon('heroicon-s-eye')
                ->label('Ver Detalle')
                ->color('primary')
                ->url(function (TelemedicineConsultationPatient $record) {
                    return TelemedicineConsultationPatientResource::getUrl('view', ['record' => $record->getKey()]);
                })
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}