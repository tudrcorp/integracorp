<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TelemedicineConsultationPatientInfolist
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make()
                    ->description(fn (TelemedicineConsultationPatient $record) => 'PACIENTE: '.$record->full_name.' | '.'EDAD: '.$record->telemedicinePatient->age.' años | '.'SEXO: '.$record->telemedicinePatient->sex)
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
                                    ->default(fn (TelemedicineConsultationPatient $record) => strtoupper($record->full_name))
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
                                    ->color(fn (string $state): string => TelemedicinePriorityFilamentBadge::color($state))
                                    ->icon(fn (string $state): string => TelemedicinePriorityFilamentBadge::icon($state)),
                                TextEntry::make('updated_at')
                                    ->label('Ultima Actualización')
                                    ->default(fn (TelemedicineCase $record): string => $record->updated_at->diffForHumans()),

                            ])->columnSpanFull()->columns(5),
                        Fieldset::make('INFORMACIÓN MEDICA')
                            ->hidden(fn (TelemedicineConsultationPatient $record) => $record->status == 'EN SEGUIMIENTO' || $record->status == 'ALTA MAEDICA')
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
                            ->hidden(fn (TelemedicineConsultationPatient $record) => $record->status == 'CONSULTA INICIAL')
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
