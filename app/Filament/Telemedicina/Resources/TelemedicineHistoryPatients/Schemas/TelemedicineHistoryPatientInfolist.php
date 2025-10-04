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
                            ->collapsed()
                            ->heading('ANTECEDENTES PERSONALES Y FAMILIARES')
                            ->columnSpanFull()
                            ->icon(Heroicon::Bars3BottomLeft)
                            ->schema([
                                Fieldset::make()
                                    ->schema([
                                        IconEntry::make('cancer')
                                            ->label('Cáncer')
                                            ->boolean(),
                                        IconEntry::make('diabetes')
                                            ->label('Diábetes')
                                            ->boolean(),
                                        IconEntry::make('tension_alta')
                                            ->label('Tensión Alta')
                                            ->boolean(),
                                        IconEntry::make('cardiacos')
                                            ->label('Cardíacos')
                                            ->boolean(),
                                        IconEntry::make('psiquiatricas')
                                            ->label('Psiquiátricas')
                                            ->boolean(),
                                        IconEntry::make('alteraciones_coagulacion')
                                            ->label('Alteraciones de Coagulación')
                                            ->boolean(),
                                        IconEntry::make('trombosis_embooleanas')
                                            ->label('Trombosis Emboleanas')
                                            ->boolean(),
                                        IconEntry::make('tranfusiones_sanguineas')
                                            ->label('Transfusiones Sanguíneas')
                                            ->boolean(),
                                        IconEntry::make('covid')
                                            ->label('COVID')
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
                                ->collapsed()
                                ->heading('ANTECEDENTES PERSONALES Y PATOLÓGICOS')
                                ->columnSpanFull()
                                ->icon(Heroicon::Bars3BottomLeft)
                                ->schema([
                                    Fieldset::make()
                                        ->schema([
                                            IconEntry::make('hepatitis')
                                                ->label('Hepatitis')
                                                ->boolean(),
                                            IconEntry::make('vih')
                                                ->label('VIH')  
                                                ->boolean(),
                                            IconEntry::make('gastritis_ulceras')
                                                ->label('Gastritis y úlceras')
                                                ->boolean(),
                                            IconEntry::make('neurologia')
                                                ->label('Neurología')
                                                ->boolean(),
                                            IconEntry::make('ansiedad_angustia')
                                                ->label('Ansiedad y Angustia')
                                                ->boolean(),
                                            IconEntry::make('tiroides')
                                                ->label('Tiroides')
                                                ->boolean(),
                                            IconEntry::make('lupus')
                                                ->label('Lupus')
                                                ->boolean(),
                                            IconEntry::make('enfermedad_autoimmune')
                                                ->label('Enfermedad Autoimmune')
                                                ->boolean(),
                                            IconEntry::make('diabetes_mellitus')
                                                ->label('Diábetes Mellitus')
                                                ->boolean(),
                                            IconEntry::make('presion_arterial_alta')
                                                ->label('Presión Arterial Alta')
                                                ->boolean(),
                                            IconEntry::make('tiene_cateter_venoso')
                                                 ->label('Tiene Cateter Venoso')
                                                ->boolean(),
                                            IconEntry::make('fracturas')
                                                ->label('Fracturas')
                                                ->boolean(),
                                            IconEntry::make('trombosis_venosa')
                                                ->label('Trombosis Venosa')
                                                ->boolean(),
                                            IconEntry::make('embooleania_pulmonar')
                                                ->label('Embolia Pulmonar')
                                                ->boolean(),
                                            IconEntry::make('varices_piernas')
                                                ->label('Varices en Piernas')
                                                ->boolean(),
                                            IconEntry::make('insuficiencia_arterial')
                                                ->label('Insuficiencia Arterial')
                                                ->boolean(),
                                            IconEntry::make('coagulacion_anormal')
                                                ->label('Coagulación Anormal')
                                                ->boolean(),
                                            IconEntry::make('moretones_frecuentes')
                                                ->label('Moretones Frecuentes')  
                                                ->boolean(),
                                            IconEntry::make('sangrado_cirugias_previas')
                                                ->label('Sangrado en Cirugías Previas')
                                                ->boolean(),
                                            IconEntry::make('sangrado_cepillado_dental')
                                                ->label('Sangrado al cepillado Dental')
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
                                ->collapsed()
                                ->heading('ANTECEDENTES NO PATOLÓGICOS')
                                ->columnSpanFull()
                                ->icon(Heroicon::Bars3BottomLeft)
                                ->schema([
                                    Fieldset::make()
                                        ->schema([
                                            IconEntry::make('alcohol')
                                                ->label('Alcohol')
                                                ->boolean(),
                                            IconEntry::make('drogas')
                                                ->label('Drogas')
                                                ->boolean(),
                                            IconEntry::make('vacunas_recientes')
                                                ->label('Vacunas Recientes')
                                                ->boolean(),
                                            IconEntry::make('transfusiones_sanguineas')
                                                ->label('Transfusiones Sanguíneas')
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
                                ->collapsed()
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
                                ->collapsed()
                                ->heading('ALERGIAS')
                                ->columnSpanFull()
                                ->icon(Heroicon::Bars3BottomLeft)
                                ->schema([
                                    TextEntry::make('allergies')
                                        ->label('Lista de Alergias:')
                                        ->badge()
                                        ->color('primary'),
                                ])->columnSpanFull(),

                            Section::make()
                                ->collapsed()
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
                                ])->columnSpanFull()->columns(4),

                            Section::make()
                                ->collapsed()
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
                            
                    ])->columnSpanFull(),

            ]);
    }
}