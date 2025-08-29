<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Schemas;

use Filament\Schemas\Schema;
use Tables\Actions\CreateAction;
use App\Models\TelemedicinePatient;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use App\Models\TelemedicineHistoryPatient;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

class TelemedicineHistoryPatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make()
                    ->heading('HISTORIA CLINICA')
                    ->description(fn(TelemedicineHistoryPatient $record) => 'PACIENTE: ' . $record->telemedicinePatient->full_name . ' | ' . 'EDAD: ' . $record->telemedicinePatient->age . ' años | ' . 'SEXO: ' . $record->telemedicinePatient->sex)
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Section::make()
                        ->heading('INFORMACIÓN PRINCIPAL')
                        ->columnSpanFull()
                        ->icon(Heroicon::Bars3BottomLeft)
                        ->schema([
                            TextEntry::make('code')
                                ->label('Nro. de Historia:')
                                ->badge()
                                ->color('success'),
                            TextEntry::make('created_by')
                                ->label('Registrado por:')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('created_at')
                                ->label('Fecha:')
                                ->badge()
                                ->icon(Heroicon::CalendarDays)
                                ->dateTime(),
                        ])->columnSpanFull()->columns(3),

                        Section::make()
                            ->heading('ANTECEDENTES PERSONALES Y FAMILIARES')
                            ->columnSpanFull()
                            ->icon(Heroicon::Bars3BottomLeft)
                            ->schema([
                                Fieldset::make()
                                    ->schema([
                                        IconEntry::make('cancer')
                                            ->boolean(),
                                        IconEntry::make('diabetes')
                                            ->boolean(),
                                        IconEntry::make('tension_alta')
                                            ->boolean(),
                                        IconEntry::make('cardiacos')
                                            ->boolean(),
                                        IconEntry::make('psiquiatricas')
                                            ->boolean(),
                                        IconEntry::make('alteraciones_coagulacion')
                                            ->boolean(),
                                        IconEntry::make('trombosis_embooleanas')
                                            ->boolean(),
                                        IconEntry::make('tranfusiones_sanguineas')
                                            ->boolean(),
                                        IconEntry::make('covid')
                                            ->boolean(),
                                        
                                    ])->columnSpanFull()->columns(4),
                                Fieldset::make('Observaciones Adicionales de Antecedentes Personales y Familiares')
                                    ->schema([
                                        TextEntry::make('observations_personal')
                                        ->label('Detalles:')
                                    ])
                                    ->columnSpanFull()->columns(1),
                            ])->columnSpanFull()->columns(5),

                            Section::make()
                                ->heading('ANTECEDENTES PERSONALES Y PATOLÓGICOS')
                                ->columnSpanFull()
                                ->icon(Heroicon::Bars3BottomLeft)
                                ->schema([
                                    Fieldset::make()
                                        ->schema([
                                            IconEntry::make('hepatitis')
                                                ->boolean(),
                                            IconEntry::make('vih')
                                                ->boolean(),
                                            IconEntry::make('gastritis_ulceras')
                                                ->boolean(),
                                            IconEntry::make('neurologia')
                                                ->boolean(),
                                            IconEntry::make('ansiedad_angustia')
                                                ->boolean(),
                                            IconEntry::make('tiroides')
                                                ->boolean(),
                                            IconEntry::make('lupus')
                                                ->boolean(),
                                            IconEntry::make('enfermedad_autoimmune')
                                                ->boolean(),
                                            IconEntry::make('diabetes_mellitus')
                                                ->boolean(),
                                            IconEntry::make('presion_arterial_alta')
                                                ->boolean(),
                                            IconEntry::make('tiene_cateter_venoso')
                                                ->boolean(),
                                            IconEntry::make('fracturas')
                                                ->boolean(),
                                            IconEntry::make('trombosis_venosa')
                                                ->boolean(),
                                            IconEntry::make('embooleania_pulmonar')
                                                ->boolean(),
                                            IconEntry::make('varices_piernas')
                                                ->boolean(),
                                            IconEntry::make('insuficiencia_arterial')
                                                ->boolean(),
                                            IconEntry::make('coagulacion_anormal')
                                                ->boolean(),
                                            IconEntry::make('moretones_frecuentes')
                                                ->boolean(),
                                            IconEntry::make('sangrado_cirugias_previas')
                                                ->boolean(),
                                            IconEntry::make('sangrado_cepillado_dental')
                                                ->boolean(),
                                        
                                        ])->columnSpanFull()->columns(5),
                                    Fieldset::make('Observaciones Adicionales de Antecedentes Personales y Patológicos')
                                        ->schema([
                                            TextEntry::make('observations_pathological')
                                                ->label('Detalles:')
                                        ])
                                        ->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(5),

                            Section::make()
                                ->heading('ANTECEDENTES NO PATOLÓGICOS')
                                ->columnSpanFull()
                                ->icon(Heroicon::Bars3BottomLeft)
                                ->schema([
                                    Fieldset::make()
                                        ->schema([
                                            IconEntry::make('alcohol')
                                                ->boolean(),
                                            IconEntry::make('drogas')
                                                ->boolean(),
                                            IconEntry::make('vacunas_recientes')
                                                ->boolean(),
                                            IconEntry::make('transfusiones_sanguineas')
                                                ->boolean(),
                                            
                                        ])->columnSpanFull()->columns(4),
                                    Fieldset::make('Observaciones Adicionales de Antecedentes No Patológicos')
                                        ->schema([
                                            TextEntry::make('observations_not_pathological')
                                                ->label('Detalles:')
                                        ])
                                        ->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(4),

                            Section::make()
                                ->heading('ANTECEDENTES GINECÓLOGOS')
                                ->columnSpanFull()
                                ->icon(Heroicon::Bars3BottomLeft)
                                ->schema([
                                    Fieldset::make()
                                        ->schema([
                                            TextEntry::make('numero_embarazos')
                                                ->label('Número de embarazos:')
                                                ->badge()
                                                ->color('success'),
                                            TextEntry::make('numero_partos')
                                                ->label('Nro. de Partos:')
                                                ->badge()
                                                ->color('success'),
                                            TextEntry::make('numero_abortos')
                                                ->label('Nro. de Abortos:')
                                                ->badge()
                                                ->color('success'),
                                            TextEntry::make('cesareas')
                                                ->label('Nro. de Cesáreas:')
                                                ->badge()
                                                ->color('success'),
                                        ])->columnSpanFull()->columns(4),
                                    Fieldset::make('Observaciones Adicionales de Antecedentes Ginecológicos')
                                        ->schema([
                                            TextEntry::make('observations_ginecologica')
                                                ->label('Detalles:')
                                        ])->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(4),

                            Section::make()
                                ->heading('ALERGIAS')
                                ->columnSpanFull()
                                ->icon(Heroicon::Bars3BottomLeft)
                                ->schema([
                                    TextEntry::make('allergies')
                                        ->label('Número de embarazos:')
                                        ->badge()
                                        ->color('success'),
                                ])->columnSpanFull()->columns(4),

                            Section::make()
                                ->heading('ANTECEDENTES QUIRÚRGICOS')
                                ->columnSpanFull()
                                ->icon(Heroicon::Bars3BottomLeft)
                                ->schema([
                                    Fieldset::make('Antecedentes Quirúrgicos')
                                        ->schema([
                                            TextEntry::make('history_surgical')
                                                ->label('Detalles:')
                                        ])->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(4),

                            Section::make()
                                ->heading('MEDICAMENTOS Y SUPLEMENTOS USADOS')
                                ->columnSpanFull()
                                ->icon(Heroicon::Bars3BottomLeft)
                                ->schema([
                                    Fieldset::make('Medicamentos o suplementos:')
                                        ->schema([
                                            TextEntry::make('medications_supplements')
                                                ->label('Detalles:')
                                                
                                        ])->columnSpanFull()->columns(1),
                                    Fieldset::make('Observaciones Adicionales de Medicamentos y Suplementos')
                                        ->schema([
                                            TextEntry::make('observations_medication')
                                                ->label('Detalles:')

                                        ])->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(4)
                            
                    ])->columnSpanFull(),

            ]);
    }
}