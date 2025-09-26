<?php

namespace App\Filament\Resources\TelemedicineConsultationPatients\Schemas;

use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use App\Models\TelemedicineConsultationPatient;
use Filament\Infolists\Components\RepeatableEntry;

class TelemedicineConsultationPatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->collapsed(true)
                    ->description(fn(TelemedicineConsultationPatient $record) => 'PACIENTE: ' . $record->full_name . ' | ' . 'EDAD: ' . $record->telemedicinePatient->age . ' años | ' . 'SEXO: ' . $record->telemedicinePatient->sex)
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('INFORMACIÓN PRINCIPAL DE LA TELEMEDICINA')
                            ->schema([
                                TextEntry::make('telemedicine_case_code')
                                    ->label('Numero de Caso:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('full_name')
                                    ->label('Nombre Completo:')
                                    ->badge()
                                    ->default(fn(TelemedicineConsultationPatient $record) => strtoupper($record->full_name))
                                    ->color('success'),
                                TextEntry::make('nro_identificacion')
                                    ->label('Número de Identificación:')
                                    ->prefix('V-')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('telemedicineDoctor.full_name')
                                    ->label('Atenido por:')
                                    ->prefix('Dr(a). '),
                                TextEntry::make('type_service')
                                    ->label('Tipo de Servicio:'),

                                Fieldset::make('INFORMACION MEDICA')
                                    ->schema([
                                        Fieldset::make()
                                            ->schema([
                                                TextEntry::make('reason_consultation')
                                                    ->label('Motivo de Consulta:'),
                                            ])->columnSpanFull()->columns(1),
                                        Fieldset::make()
                                            ->schema([
                                                TextEntry::make('actual_phatology')
                                                    ->label('Patología Actual:'),
                                            ])->columnSpanFull()->columns(1),
                                        Fieldset::make()
                                            ->schema([
                                                TextEntry::make('diagnostic_impression')
                                                    ->label('Impresión Diagnóstica:'),
                                            ])->columnSpanFull()->columns(1),

                                        Fieldset::make('Tratamiento Medico')
                                            ->schema([
                                                RepeatableEntry::make('TelemedicinePatientMedications')
                                                    ->label('Medicamentos Indicados')
                                                    ->schema([
                                                        TextEntry::make('medicine')
                                                            ->label('Medicamento'),
                                                        TextEntry::make('indications')
                                                            ->label('Indicaciones'),
                                                    ])
                                                    ->columns(2)
                                                    ->grid(1),
                                            ])->columnSpanFull()->columns(1),
                                                        
                                        Grid::make()
                                            ->schema([
                                                Fieldset::make()
                                                    ->schema([
                                                        TextEntry::make('labs')
                                                            ->badge()
                                                            ->color('primary')
                                                            ->label('Laboratorios:'),
                                                        TextEntry::make('other_labs')
                                                            ->badge()
                                                            ->color('primary')
                                                            ->label('Otros Laboratorios:'),
                                                    ]),
                                                Fieldset::make()
                                                    ->schema([
                                                        TextEntry::make('studies')
                                                            ->badge()
                                                            ->color('primary')
                                                            ->label('Estudios:'),
                                                        TextEntry::make('other_studies')
                                                            ->badge()
                                                            ->color('primary')
                                                            ->label('Otros Estudios:'),
                                                        TextEntry::make('part_body')
                                                            ->hidden(function (TelemedicineConsultationPatient $record) {
                                                                $exite = in_array('RX DE TORAX', $record->studies);
                                                                if (!$exite) {
                                                                    return true;
                                                                }
                                                                return false;
                                                            })
                                                            ->label('Partes del Cuerpo:'),
                                                    ]),
                                                Fieldset::make()
                                                    ->schema([
                                                        TextEntry::make('consult_specialist')
                                                            ->badge()
                                                            ->color('primary')
                                                            ->label('Consulta a Especialista:'),
                                                        TextEntry::make('other_specialist')
                                                            ->badge()
                                                            ->color('primary')
                                                            ->label('Otros Especialistas:'),
                                                    ]),
                                            
                                    ])->columnSpanFull()->columns(3),
                                        
                    ])->columnSpanFull()->columns(5),
                            ])->columnSpanFull()->columns(5),
                    ])->columnSpanFull(),
            ]);
    }
}