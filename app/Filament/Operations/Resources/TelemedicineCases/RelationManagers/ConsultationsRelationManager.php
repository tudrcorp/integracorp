<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;

use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Schemas\Components\Fieldset;
use Filament\Actions\DissociateBulkAction;
use Filament\Infolists\Components\TextEntry;
use App\Models\TelemedicineConsultationPatient;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;

class ConsultationsRelationManager extends RelationManager
{
    protected static string $relationship = 'consultations';

    // public function form(Schema $schema): Schema
    // {
    //     return $schema
    //         ->components([
    //             TextInput::make('telemedicine_case_id')
    //                 ->required()
    //                 ->maxLength(255),
    //         ]);
    // }

    // public function infolist(Schema $schema): Schema
    // {
    //     return $schema
    //         ->components([
    //             Section::make()
    //                 ->description(fn(TelemedicineConsultationPatient $record) => 'PACIENTE: ' . $record->full_name . ' | ' . 'EDAD: ' . $record->telemedicinePatient->age . ' años | ' . 'SEXO: ' . $record->telemedicinePatient->sex)
    //                 ->columnSpanFull()
    //                 ->icon(Heroicon::Bars3BottomLeft)
    //                 ->schema([
    //                     Fieldset::make('INFORMACIÓN PRINCIPAL')
    //                         ->schema([
    //                             TextEntry::make('telemedicine_case_code')
    //                                 ->label('NÚMERO DE CASO:')
    //                                 ->badge()
    //                                 ->color('success'),
    //                             TextEntry::make('full_name')
    //                                 ->label('NOMBRE COMPLETO:')
    //                                 ->badge()
    //                                 ->default(fn(TelemedicineConsultationPatient $record) => strtoupper($record->full_name))
    //                                 ->color('success'),
    //                             TextEntry::make('nro_identificacion')
    //                                 ->label('NÚMERO DE IDENTIFICACION:')
    //                                 ->prefix('V-')
    //                                 ->badge()
    //                                 ->color('success'),
    //                             TextEntry::make('telemedicineServiceList.name')
    //                                 ->label('SERVICIO:')
    //                                 ->badge()
    //                                 ->color('success'),

    //                             TextEntry::make('telemedicineDoctor.full_name')
    //                                 ->label('ATENIDO POR:')
    //                                 ->prefix('Dr(a). ')
    //                                 ->badge(),
    //                             TextEntry::make('created_at')
    //                                 ->label('FECHA DE REGISTRO:')
    //                                 ->badge()
    //                                 ->date('d/m/Y'),
    //                             TextEntry::make('status')
    //                                 ->label('ESTADO:')
    //                                 ->badge()
    //                                 ->color(function (TelemedicineConsultationPatient $record) {
    //                                     if ($record->status == 'EN SEGUIMIENTO') {
    //                                         return 'warning';
    //                                     } elseif ($record->status == 'CONSULTA INICIAL') {
    //                                         return 'info';
    //                                     } elseif ($record->status == 'ALTA MEDICA') {
    //                                         return 'success';
    //                                     }
    //                                 }),
    //                             // TextEntry::make('telemedicinePriority.name')
    //                             //     ->label('PRIORIDAD:')
    //                             //     ->badge()
    //                             //     ->color(function (string $state): string {
    //                             //         return match ($state) {
    //                             //             'ALTA'          => 'success',
    //                             //             'MEDIA'         => 'warning',
    //                             //             'BAJA'          => 'primary',
    //                             //             'EMERGENCIA'    => 'danger',
    //                             //         };
    //                             //     })
    //                             //     ->icon(function (string $state): string {
    //                             //         return match ($state) {
    //                             //             'ALTA'             => 'healthicons-f-health',
    //                             //             'MEDIA'            => 'healthicons-f-health',
    //                             //             'BAJA'             => 'healthicons-f-health',
    //                             //             'EMERGENCIA'       => 'heroicon-c-shield-exclamation',
    //                             //         };
    //                             //     }),
    //                             TextEntry::make('updated_at')
    //                                 ->label('Ultima Actualización')
    //                                 ->default(fn(TelemedicineCase $record): string => $record->updated_at->diffForHumans()),

    //                         ])->columnSpanFull()->columns(5),
    //                     Fieldset::make('INFORMACIÓN MEDICA')
    //                         ->hidden(fn(TelemedicineConsultationPatient $record) => $record->status == 'EN SEGUIMIENTO' || $record->status == 'ALTA MAEDICA')
    //                         ->schema([
    //                             TextEntry::make('reason_consultation')
    //                                 ->label('RAZÓN DE CONSULTA:'),
    //                             TextEntry::make('actual_phatology')
    //                                 ->label('PATOLÓGICO ACTUAL:'),
    //                             TextEntry::make('background')
    //                                 ->label('ANTECEDENTES:'),
    //                             TextEntry::make('diagnostic_impression')
    //                                 ->label('IMPRESIÓN DIAGNOSTICA:'),
    //                         ])->columnSpanFull()->columns(2),
    //                     Fieldset::make('CUESTIONARIO DE SEGUIMIENTO')
    //                         ->hidden(fn(TelemedicineConsultationPatient $record) => $record->status == 'CONSULTA INICIAL')
    //                         ->schema([
    //                             TextEntry::make('cuestion_1')
    //                                 ->label('1.- ¿COMO SE SIENTE EL DIA DE HOY?')
    //                                 ->prefix('RESPUESTA: '),
    //                             TextEntry::make('cuestion_2')
    //                                 ->label('2.- ¿COMO HA RESPONDIDO AL TRATAMIENTO INDICADO?')
    //                                 ->prefix('RESPUESTA: '),
    //                             TextEntry::make('cuestion_3')
    //                                 ->label('3. ¿SIENTE QUE HAN MEJORADO LOS SÍNTOMAS?')
    //                                 ->prefix('RESPUESTA: '),
    //                             TextEntry::make('cuestion_4')
    //                                 ->label('4. ¿SE REALIZO LOS ESTUDIOS SOLICITADOS?')
    //                                 ->prefix('RESPUESTA: '),
    //                             TextEntry::make('cuestion_5')
    //                                 ->label('5. EN VISTA DE QUE SUS RESULTADOS DE LABORATORIO ESTÁN ALTERADOS, SE MODIFICAN LAS INDICACIONES MEDICAS.')
    //                                 ->prefix('RESPUESTA: '),
    //                         ])->columnSpanFull()->columns(2),
    //                 ])->columnSpanFull(),
    //         ]);
    // }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Bitácora de Gestión Médica')
            ->description('Descripción detallada de todos los seguimiento y asignaciones del caso.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn(TelemedicineConsultationPatient $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
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

                // TextColumn::make('telemedicinePriority.name')
                //     ->label('Prioridad')
                //     ->badge()
                //     ->color(function (string $state): string {
                //         return match ($state) {
                //             'ALTA'          => 'success',
                //             'MEDIA'         => 'warning',
                //             'BAJA'          => 'primary',
                //             'EMERGENCIA'    => 'danger',
                //         };
                //     })
                //     ->icon(function (string $state): string {
                //         return match ($state) {
                //             'ALTA'             => 'healthicons-f-health',
                //             'MEDIA'            => 'healthicons-f-health',
                //             'BAJA'             => 'healthicons-f-health',
                //             'EMERGENCIA'       => 'heroicon-c-shield-exclamation',
                //         };
                //     })
                //     ->searchable(),
            ])
            ->recordActions([
                // ViewAction::make()
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