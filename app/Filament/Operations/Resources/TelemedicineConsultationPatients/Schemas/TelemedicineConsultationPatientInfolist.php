<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

use App\Models\TelemedicineCase;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\TelemedicineConsultationPatient;
use Filament\Infolists\Components\RepeatableEntry;

class TelemedicineConsultationPatientInfolist
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make()
                    ->description(fn(TelemedicineConsultationPatient $record) => 'PACIENTE: ' . $record->full_name . ' | ' . 'EDAD: ' . $record->telemedicinePatient->age . ' años | ' . 'SEXO: ' . $record->telemedicinePatient->sex)
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('INFORMACIÓN PRINCIPAL')
                            ->schema([
                                TextEntry::make('telemedicine_case_code')
                                    ->label('NÚMERO DE CASO:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('full_name')
                                    ->label('NOMBRE COMPLETO:')
                                    ->badge()
                                    ->default(fn(TelemedicineConsultationPatient $record) => strtoupper($record->full_name))
                                    ->color('success'),
                                TextEntry::make('nro_identificacion')
                                    ->label('NÚMERO DE IDENTIFICACION:')
                                    ->prefix('V-')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('telemedicineServiceList.name')
                                    ->label('SERVICIO:')
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('telemedicineDoctor.full_name')
                                    ->label('ATENIDO POR:')
                                    ->prefix('Dr(a). ')
                                    ->badge(),
                                TextEntry::make('created_at')
                                    ->label('FECHA DE REGISTRO:')
                                    ->badge()
                                    ->date('d/m/Y'),
                                TextEntry::make('status')
                                    ->label('ESTADO:')
                                    ->badge()
                                    ->color(function (TelemedicineConsultationPatient $record) {
                                        if ($record->status == 'EN SEGUIMIENTO') {
                                            return 'warning';
                                        } elseif ($record->status == 'CONSULTA INICIAL') {
                                            return 'info';
                                        } elseif ($record->status == 'ALTA MEDICA') {
                                            return 'success';
                                        }
                                    }),
                                TextEntry::make('telemedicinePriority.name')
                                    ->label('Prioridad')
                                    ->badge()
                                    ->color(function (string $state): string {
                                        return match ($state) {
                                            'No Urgente'  => 'no-urgente',
                                            'Estándar'    => 'estandar',
                                            'Urgencia'    => 'urgencia',
                                            'Emergencia'  => 'emergencia',
                                            'Critico'     => 'critico',
                                        };
                                    })
                                    ->icon(function (string $state): string {
                                        return match ($state) {
                                            'No Urgente'  => 'healthicons-f-health',
                                            'Estándar'    => 'healthicons-f-health',
                                            'Urgencia'    => 'healthicons-f-health',
                                            'Emergencia'  => 'heroicon-c-shield-exclamation',
                                            'Critico'     => 'heroicon-c-shield-exclamation',
                                        };
                                    }),
                                TextEntry::make('updated_at')
                                    ->label('Ultima Actualización')
                                    ->default(fn(TelemedicineCase $record): string => $record->updated_at->diffForHumans()),

                            ])->columnSpanFull()->columns(5),
                        Fieldset::make('INFORMACIÓN MEDICA')
                            ->hidden(fn(TelemedicineConsultationPatient $record) => $record->status == 'EN SEGUIMIENTO' || $record->status == 'ALTA MAEDICA')
                            ->schema([
                                TextEntry::make('reason_consultation')
                                    ->label('RAZÓN DE CONSULTA:'),
                                TextEntry::make('actual_phatology')
                                    ->label('PATOLÓGICO ACTUAL:'),
                                TextEntry::make('background')
                                    ->label('ANTECEDENTES:'),
                                TextEntry::make('diagnostic_impression')
                                    ->label('IMPRESIÓN DIAGNOSTICA:'),
                            ])->columnSpanFull()->columns(2),
                        Fieldset::make('CUESTIONARIO DE SEGUIMIENTO')
                            ->hidden(fn(TelemedicineConsultationPatient $record) => $record->status == 'CONSULTA INICIAL')
                            ->schema([
                                TextEntry::make('cuestion_1')
                                    ->label('1.- ¿COMO SE SIENTE EL DIA DE HOY?')
                                    ->prefix('RESPUESTA: '),
                                TextEntry::make('cuestion_2')
                                    ->label('2.- ¿COMO HA RESPONDIDO AL TRATAMIENTO INDICADO?')
                                    ->prefix('RESPUESTA: '),
                                TextEntry::make('cuestion_3')
                                    ->label('3. ¿SIENTE QUE HAN MEJORADO LOS SÍNTOMAS?')
                                    ->prefix('RESPUESTA: '),
                                TextEntry::make('cuestion_4')
                                    ->label('4. ¿SE REALIZO LOS ESTUDIOS SOLICITADOS?')
                                    ->prefix('RESPUESTA: '),
                                TextEntry::make('cuestion_5')
                                    ->label('5. EN VISTA DE QUE SUS RESULTADOS DE LABORATORIO ESTÁN ALTERADOS, SE MODIFICAN LAS INDICACIONES MEDICAS.')
                                    ->prefix('RESPUESTA: '),
                            ])->columnSpanFull()->columns(2),
                    ])->columnSpanFull(),
            ]);
    }
}