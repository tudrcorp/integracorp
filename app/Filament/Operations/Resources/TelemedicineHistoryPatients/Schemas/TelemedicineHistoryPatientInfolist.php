<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

use Tables\Actions\CreateAction;
use App\Models\TelemedicinePatient;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use App\Models\TelemedicineHistoryPatient;

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
                                TextEntry::make('weight')
                                    ->label('Peso')
                                    ->helperText('Peso (kg)')
                                    ->icon('healthicons-f-i-utensils')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('height')
                                    ->label('Estatura')
                                    ->helperText('Centímetros(cm) / Metros(mts)')
                                    ->icon('healthicons-f-i-utensils')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('imc')
                                    //peso/estatura * 2
                                    ->label('Indice de Masa Corporal (IMC)')
                                    ->helperText('')
                                    ->icon('healthicons-f-i-utensils')
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
                            ])->columnSpanFull()->columns(4),

                        Section::make()
                            ->collapsed()
                            ->heading('ANTECEDENTES PERSONALES Y FAMILIARES')
                            ->columnSpanFull()
                            ->icon(Heroicon::Bars3BottomLeft)
                            ->schema([
                                Fieldset::make()
                                    ->schema([
                                        IconEntry::make('tension_alta')
                                            ->boolean()
                                            ->label('Hipertensión Arterial'),
                                        IconEntry::make('diabetes')
                                            ->boolean()
                                            ->label('Diábetes Mellitus'),
                                        IconEntry::make('asma')
                                            ->boolean()
                                            ->label('Asma Bronquial'),
                                        IconEntry::make('cardiacos')
                                            ->boolean()
                                            ->label('Enfermedades Cardíacas'),
                                        IconEntry::make('gastritis_ulceras')
                                            ->boolean()
                                            ->label('Gastropatias'),
                                        IconEntry::make('enfermedad_autoimmune')
                                            ->boolean()
                                            ->label('Enfermedad Autoimmune'),
                                        IconEntry::make('trombosis_embooleanas')
                                            ->boolean()
                                            ->label('Insuficiencia Venosa'),
                                        IconEntry::make('fracturas')
                                            ->boolean()
                                            ->label('Traumatismos'),
                                        IconEntry::make('cancer')
                                            ->boolean()
                                            ->label('Cáncer'),
                                        IconEntry::make('tranfusiones_sanguineas')
                                            ->boolean()
                                            ->label('Anemia'),
                                        IconEntry::make('tiroides')
                                            ->boolean()
                                            ->label('Tiroides'),
                                        IconEntry::make('hepatitis')
                                            ->boolean()
                                            ->label('Hepatitis'),
                                        IconEntry::make('moretones_frecuentes')
                                            ->boolean()
                                            ->label('Enfermedades Hematológicas'),
                                        IconEntry::make('psiquiatricas')
                                            ->boolean()
                                            ->label('Enfermedades Psiquiátricas'),
                                        IconEntry::make('covid')
                                            ->boolean()
                                            ->label('COVID-19'),
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
                                        IconEntry::make('tension_alta_app')
                                            ->boolean()
                                            ->label('Hipertensión Arterial'),
                                        IconEntry::make('diabetes')
                                            ->boolean()
                                            ->label('Diábetes Mellitus'),
                                        IconEntry::make('asma_app')
                                            ->boolean()
                                            ->label('Asma Bronquial'),
                                        IconEntry::make('cardiacos_app')
                                            ->boolean()
                                            ->label('Enfermedades Cardíacas'),
                                        IconEntry::make('gastritis_ulceras_app')
                                            ->boolean()
                                            ->label('Gastropatias'),
                                        IconEntry::make('enfermedad_autoimmune_app')
                                            ->boolean()
                                            ->label('Enfermedad Autoimmune'),
                                        IconEntry::make('trombosis_embooleanas_app')
                                            ->boolean()
                                            ->label('Insuficiencia Venosa'),
                                        IconEntry::make('fracturas_app')
                                            ->boolean()
                                            ->label('Traumatismos'),
                                        // IconEntry::make('alteraciones_coagulacion')
                                        //     ->label('Alteraciones de Coagulación'),
                                        IconEntry::make('cancer_app')
                                            ->boolean()
                                            ->label('Cáncer'),
                                        IconEntry::make('tranfusiones_sanguineas_app')
                                            ->boolean()
                                            ->label('Anemia'),
                                        IconEntry::make('tiroides_app')
                                            ->boolean()
                                            ->label('Tiroides'),
                                        IconEntry::make('hepatitis_app')
                                            ->boolean()
                                            ->label('Hepatitis'),
                                        IconEntry::make('moretones_frecuentes_app')
                                            ->boolean()
                                            ->label('Enfermedades Hematológicas'),
                                        IconEntry::make('psiquiatricas_app')
                                            ->boolean()
                                            ->label('Enfermedades Psiquiátricas'),
                                        IconEntry::make('covid_app')
                                            ->boolean()
                                            ->label('COVID-19'),

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
                                        IconEntry::make('tabaco')
                                            ->boolean()
                                            ->label('Tabaquismo'),
                                        IconEntry::make('alcohol')
                                            ->boolean()
                                            ->label('Alcohol'),
                                        IconEntry::make('drogas')
                                            ->boolean()
                                            ->label('Drogas'),

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
