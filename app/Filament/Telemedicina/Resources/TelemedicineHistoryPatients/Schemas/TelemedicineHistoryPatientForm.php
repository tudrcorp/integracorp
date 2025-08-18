<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Schemas;

use Filament\Schemas\Schema;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use App\Models\TelemedicineAllergyList;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use App\Models\TelemedicineHistoryPatient;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;

class TelemedicineHistoryPatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Information Principal')
                        ->schema([
                            Section::make()
                            ->heading('Datos principales del paciente')
                            ->description('...')
                                ->schema([
                                    // ...
                                    Grid::make(3)
                                        ->schema([
                                            TextInput::make('code')
                                                ->label('Nro. de Historia')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (TelemedicineHistoryPatient::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = TelemedicineHistoryPatient::max('id');
                                                    }
                                                    return 'TEL-HIS-000' . $parte_entera + 1;
                                                })
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),
                                        ])->columnSpanFull(),
                                    // ...
                                    Select::make('telemedicine_patient_id')
                                        ->label('Paciente')
                                        ->options(TelemedicinePatient::all()->pluck('full_name', 'id'))
                                        ->default(function () {
                                            if (request('record')) {
                                                return request('record');
                                            }
                                            return null;
                                        })
                                        // ->disabled(function () {
                                        //     if (request('record')) {
                                        //         return true;
                                        //     }
                                        //     return false;
                                            
                                        // })
                                        // ->dehydrated(function () {
                                        //     if (request('record')) {
                                        //         return true;
                                        //     }
                                        //     return false;
                                        // })
                                        ->required(),
                                    // ...
                                    DatePicker::make('history_date')
                                        ->label('Fecha')
                                        ->default(now()),
                                    // ...
                                    Hidden::make('telemedicine_doctor_id')->default(Auth::user()->doctor_id),
                                    Hidden::make('code_patient')->default(fn (Get $get) => TelemedicinePatient::find($get('telemedicine_patient_id'))->code),
                                    Hidden::make('created_by')->default(Auth::user()->name),
                                ])->columnSpanFull()->columns(3),   
                        ])->columns(3),
                    Step::make('Signos Vitales')
                        ->schema([
                            Section::make()
                                ->heading('Signos vitales del paciente')
                                ->description('...')
                                ->schema([
                                    // ...
                                    // TextInput::make('vs_pa')
                                    //     ->label('Presión Arterial')
                                    //     ->helperText('Presión Arterial (mmHg)')
                                    //     ->numeric()
                                    //     ->prefixIcon('healthicons-f-i-utensils')
                                    //     ->required(),
                                    // TextInput::make('vs_fc')
                                    //     ->label('Frecuencia Cardíaca')
                                    //     ->helperText('Frecuencia Cardíaca (lpm)')
                                    //     ->numeric()
                                    //     ->prefixIcon('healthicons-f-i-utensils')
                                    //     ->required(),
                                    // TextInput::make('vs_fr')
                                    //     ->label('Frecuencia Respiratoria')
                                    //     ->helperText('Frecuencia Respiratoria (rpm)')
                                    //     ->numeric()
                                    //     ->prefixIcon('healthicons-f-i-utensils')
                                    //     ->required(),
                                    // TextInput::make('vs_temp')
                                    //     ->label('Temperatura')
                                    //     ->helperText('Temperatura (°C)')
                                    //     ->numeric()
                                    //     ->prefixIcon('healthicons-f-i-utensils')
                                    //     ->required(),
                                    // TextInput::make('vs_sat')
                                    //     ->label('Saturación')
                                    //     ->helperText('Saturación (% de oxigeno en sangre)')
                                    //     ->numeric()
                                    //     ->prefixIcon('healthicons-f-i-utensils')
                                    //     ->required(),
                                    TextInput::make('weight')
                                        ->label('Peso')
                                        ->helperText('Peso (kg)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils')
                                        ->required(),
                                    TextInput::make('height')
                                        ->label('Altura')
                                        ->helperText('Altura (cm)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils')
                                        ->required(),
                                ])->columnSpanFull()->columns(3),
                        ]),
                    Step::make('Antecedentes Personales y Familiares')
                        ->schema([
                            Section::make()
                                ->schema([
                                    // ...
                                    Toggle::make('cancer'),
                                    Toggle::make('diabetes'),
                                    Toggle::make('tension_alta'),
                                    Toggle::make('cardiacos'),
                                    Toggle::make('psiquiatricas'),
                                    Toggle::make('alteraciones_coagulacion'),
                                    Toggle::make('trombosis_embooleanas'),
                                    Toggle::make('tranfusiones_sanguineas'),
                                    Toggle::make('COVID19'),
                                    Grid::make()
                                        ->schema([
                                            TextArea::make('observations_personal')
                                                ->label('Observaciones Antecedentes Personales'),
                                        ])->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(3),
                        ]),
                    Step::make('Antecedentes Personales Patológicos')
                        ->schema([
                            Section::make()
                                ->schema([
                                    // ...
                                    Toggle::make('hepatitis'),
                                    Toggle::make('VIH_SIDA'),
                                    Toggle::make('gastritis_ulceras'),
                                    Toggle::make('neurologia'),
                                    Toggle::make('ansiedad_angustia'),
                                    Toggle::make('tiroides'),
                                    Toggle::make('lupus'),
                                    Toggle::make('enfermedad_autoimmune'),
                                    Toggle::make('diabetes_mellitus'),
                                    Toggle::make('presion_arterial_alta'),
                                    Toggle::make('fracturas'),
                                    Toggle::make('trombosis_venosa'),
                                    Toggle::make('embooleania_pulmonar'),
                                    Toggle::make('varices_piernas'),
                                    Toggle::make('insuficiencia_arterial'),
                                    Toggle::make('coagulacion_anormal'),
                                    Toggle::make('moretones_frecuentes'),
                                    Toggle::make('sangrado_cirugias_previas'),
                                    Toggle::make('sangrado_cepillado_dental'),
                                    Grid::make()
                                        ->schema([
                                            TextArea::make('observations_pathological')
                                                ->label('Observaciones Patológicas'),
                                        ])->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(3),
                        ]),
                    Step::make('Antecedentes No Patológicos')
                        ->schema([
                            Section::make()
                                ->schema([
                                    Grid::make()
                                        ->schema([
                                            // ...
                                            Toggle::make('alcohol'),
                                            Toggle::make('drogas'),
                                            Toggle::make('vacunas_recientes'),
                                            Toggle::make('transfusiones_sanguineas'),
                                        ])->columns(4),
                                    Grid::make()
                                        ->schema([
                                            TextArea::make('observations_not_pathological')
                                                ->label('Observaciones No Patológicas'),
                                        ])->columnSpanFull()->columns(1),
                                ])
                        ]),
                    Step::make('Antecedentes Ginecologicos')
                        ->hidden(function ($get) {
                            if(null !== $get('telemedicine_patient_id')) {
                                $sex = TelemedicinePatient::find($get('telemedicine_patient_id'))->sex;
                                if ($sex == 'MASCULINO') {
                                    return true;
                                }
                            }
                            return false;
                        })
                        ->schema([
                            // ...
                            TextInput::make('numero_embarazos')
                                ->numeric(),
                            TextInput::make('numero_partos')
                                ->numeric(),
                            TextInput::make('numero_abortos')
                                ->numeric(),
                            TextInput::make('cesareas')
                                ->numeric(),
                            TextInput::make('observations_ginecologica'),
                        ])->columns(3),
                    Step::make('Alergias')
                        ->schema([
                            // ...
                            Select::make('allergies')
                                ->options(TelemedicineAllergyList::all()->pluck('description', 'description')->toArray())
                                ->multiple()
                                ->searchable(),
                            TextInput::make('observations_allergies'),
                        ]),
                    Step::make('Antecedentes Quirúrgicos')
                        ->schema([
                            // ...
                            Textarea::make('history_surgical')
                                ->columnSpanFull(),
                        ]),
                    Step::make('Medicamentos(Crónicos) y Suplementos Usados')
                        ->schema([
                            // ...
                            Textarea::make('medications_supplements')
                                ->columnSpanFull(),
                            TextInput::make('observations_medication'),
                        ]),
                ])->columnSpanFull(),   
            ]);
    }
}