<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Schemas;

use App\Models\AllergyList;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use App\Models\TelemedicineAllergyList;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use App\Models\TelemedicineHistoryPatient;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;

class TelemedicineHistoryPatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                    Section::make('Información Principal')
                    ->collapsible()
                    ->schema([
                        Section::make()
                        ->description('Datos principales del paciente')
                        ->schema([
                            // ...
                            Grid::make(5)
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
                                            return 'HIS-000' . $parte_entera + 1;
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
                                    if (session()->get('patient')) {
                                        $patient = session()->get('patient')->id;
                                        
                                        return $patient;
                                    }
                                        Log::warning(session()->get('patient_id'));
                                    return null;
                                })
                                ->required(),
                            TextInput::make('weight')
                                ->label('Peso')
                                ->helperText('Peso (kg)')
                                ->numeric()
                                ->prefixIcon('healthicons-f-i-utensils')
                                ->required(),
                            TextInput::make('height')
                                ->label('Estatura')
                                ->helperText('Centímetros(cm) / Metros(mts)')
                                ->numeric()
                                ->prefixIcon('healthicons-f-i-utensils')
                                ->required(),
                            TextInput::make('imc')
                                //peso/estatura * 2
                                ->label('Indice de Masa Corporal (IMC)')
                                ->helperText('')
                                ->numeric()
                                ->prefixIcon('healthicons-f-i-utensils')
                                ->required(),
                            // ...
                            DatePicker::make('history_date')
                                ->label('Fecha')
                                ->default(now()),
                            // ...
                            Hidden::make('telemedicine_doctor_id')->default(Auth::user()->doctor_id),
                            Hidden::make('created_by')->default(Auth::user()->name),
                        ])->columnSpanFull()->columns(5),   
                    ])->columnSpanFull(),
                    Section::make('Signos Vitales del Paciente')
                    ->collapsible()
                    ->schema([
                        Section::make()
                            ->description('Los signos vitales serán tomados al momento de realizar una Asistencia Medica Domiciliaria (AMD) o en sitio.')
                            ->schema([
                                // ...
                                TextInput::make('vs_pa')
                                    ->label('Presión Arterial')
                                    ->helperText('Presión Arterial (mmHg)')
                                    ->numeric()
                                    ->prefixIcon('healthicons-f-i-utensils'),
                                TextInput::make('vs_fc')
                                    ->label('Frecuencia Cardíaca')
                                    ->helperText('Frecuencia Cardíaca (lpm)')
                                    ->numeric()
                                    ->prefixIcon('healthicons-f-i-utensils'),
                                TextInput::make('vs_fr')
                                    ->label('Frecuencia Respiratoria')
                                    ->helperText('Frecuencia Respiratoria (rpm)')
                                    ->numeric()
                                    ->prefixIcon('healthicons-f-i-utensils'),
                                TextInput::make('vs_temp')
                                    ->label('Temperatura')
                                    ->helperText('Temperatura (°C)')
                                    ->numeric()
                                    ->prefixIcon('healthicons-f-i-utensils'),
                                TextInput::make('vs_sat')
                                    ->label('Saturación')
                                    ->helperText('Saturación (% de oxigeno en sangre)')
                                    ->numeric()
                                    ->prefixIcon('healthicons-f-i-utensils'),
                            ])->columnSpanFull()->columns(5),
                    ])->columnSpanFull(),
                    Section::make('Antecedentes Patológicos Familiares')
                    ->collapsible()
                    ->schema([
                        Section::make()
                        ->description('Sección de selección multiple de antecedentes patológicos familiares mas un campo de observación para agregar información adicional.')
                            ->schema([
                                Fieldset::make('Seleccionar Antecedentes')
                                    ->schema([

                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('tension_alta')
                                                    ->live()
                                                    ->label('Hipertensión Arterial'),
                                                TextInput::make('input_tension_alta')
                                                    ->label('Tipo (opcional)')
                                                    ->disabled(fn ($get) => !$get('tension_alta'))
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('diabetes')
                                                ->live()
                                                    ->label('Diábetes Mellitus'),
                                                TextInput::make('input_diabetes')
                                                    ->disabled(fn($get) => !$get('diabetes'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('asma')
                                                    ->live()
                                                    ->label('Asma Bronquial'),
                                                TextInput::make('input_asma')
                                                    ->disabled(fn($get) => !$get('asma'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('cardiacos')
                                                    ->live()
                                                    ->label('Enfermedades Cardíacas'),
                                                TextInput::make('input_cardiacos')
                                                    ->disabled(fn($get) => !$get('cardiacos'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('gastritis_ulceras')
                                                ->live()
                                                    ->label('Gastropatias'),
                                                TextInput::make('input_gastritis_ulceras')
                                                    ->disabled(fn($get) => !$get('gastritis_ulceras'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('enfermedad_autoimmune')
                                                ->live()
                                                    ->label('Enfermedad Autoimmune'),
                                                TextInput::make('input_enfermedad_autoimmune')
                                                    ->disabled(fn($get) => !$get('enfermedad_autoimmune'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),

                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('trombosis_embooleanas')
                                                ->live()
                                                    ->label('Insuficiencia Venosa'),
                                                TextInput::make('input_trombosis_embooleanas')
                                                    ->disabled(fn($get) => !$get('trombosis_embooleanas'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('fracturas')
                                                    ->live()
                                                    ->label('Traumatismos'),
                                                TextInput::make('input_fracturas')
                                                    ->disabled(fn($get) => !$get('fracturas'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),

                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('cancer')
                                                    ->live()
                                                    ->label('Cáncer'),
                                                TextInput::make('input_cancer')
                                                    ->disabled(fn($get) => !$get('cancer'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('tranfusiones_sanguineas')
                                                    ->live()
                                                    ->label('Anemia'),
                                                TextInput::make('input_ftranfusiones_sanguineas')
                                                    ->disabled(fn($get) => !$get('tranfusiones_sanguineas'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),

                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('tiroides')
                                                    ->live()
                                                    ->label('Tiroides'),
                                                TextInput::make('input_tiroides')
                                                    ->disabled(fn($get) => !$get('tiroides'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('hepatitis')
                                                    ->live()
                                                    ->label('Hepatitis'),
                                                TextInput::make('input_hepatitis')
                                                    ->disabled(fn($get) => !$get('hepatitis'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),

                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('moretones_frecuentes')
                                                    ->live()
                                                    ->label('Enfermedades Hematológicas'),
                                                TextInput::make('input_moretones_frecuentes')
                                                    ->disabled(fn($get) => !$get('moretones_frecuentes'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('psiquiatricas')
                                                    ->live()
                                                    ->label('Enfermedades Psiquiátricas'),
                                                TextInput::make('input_psiquiatricas')
                                                    ->disabled(fn($get) => !$get('psiquiatricas'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),

                                        Toggle::make('COVID19')
                                            ->label('COVID-19'),
                                            
                                    ])->columnSpanFull()->columns(2),
                                Fieldset::make()
                                    ->schema([
                                        TextArea::make('observations_personal')
                                            ->label('Observaciones Antecedentes Personales'),
                                    ])->columnSpanFull()->columns(1),
                            ])->columnSpanFull()->columns(3),
                    ])->columnSpanFull(),
                    Section::make('Antecedentes Patológicos Personales')
                    ->collapsible()
                    ->schema([
                        Section::make()
                            ->description('Sección de selección multiple de antecedentes patológicos personales mas un campo de observación para agregar información adicional.')
                            ->schema([
                                // ...
                                Fieldset::make('Seleccionar Antecedentes')
                                    ->schema([

                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('tension_alta_app')->live()
                                                    ->label('Hipertensión Arterial'),
                                                TextInput::make('input_tension_alta_app')
                                                    ->disabled(fn($get) => !$get('tension_alta_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('diabetes_app')->live()
                                                    ->label('Diábetes Mellitus'),
                                                TextInput::make('input_diabetes_app')
                                                    ->disabled(fn($get) => !$get('diabetes_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('asma_app')->live()
                                                    ->label('Asma Bronquial'),
                                                TextInput::make('input_asma_app')
                                                    ->disabled(fn($get) => !$get('asma_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('cardiacos_app')->live()
                                                    ->label('Enfermedades Cardíacas'),
                                                TextInput::make('input_cardiacos_app')
                                                    ->disabled(fn($get) => !$get('cardiacos_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('gastritis_ulceras_app')->live()
                                                    ->label('Gastropatias'),
                                                TextInput::make('input_gastritis_ulceras_app')
                                                    ->disabled(fn($get) => !$get('gastritis_ulceras_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('enfermedad_autoimmune_app')->live()
                                                    ->label('Enfermedad Autoimmune'),
                                                TextInput::make('input_enfermedad_autoimmune_app')
                                                    ->disabled(fn($get) => !$get('enfermedad_autoimmune_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),



                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('trombosis_embooleanas_app')->live()
                                                    ->label('Insuficiencia Venosa'),
                                                TextInput::make('input_trombosis_embooleanas_app')
                                                    ->disabled(fn($get) => !$get('trombosis_embooleanas_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('fracturas_app')->live()
                                                    ->label('Traumatismos'),
                                                TextInput::make('input_fracturas_app')
                                                    ->disabled(fn($get) => !$get('fracturas_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),

                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('cancer_app')->live()
                                                    ->label('Cáncer'),
                                                TextInput::make('input_cancer_app')
                                                    ->disabled(fn($get) => !$get('cancer_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('tranfusiones_sanguineas_app')->live()
                                                    ->label('Anemia'),
                                                TextInput::make('input_ftranfusiones_sanguineas_app')
                                                    ->disabled(fn($get) => !$get('tranfusiones_sanguineas_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),

                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('tiroides_app')->live()
                                                    ->label('Tiroides'),
                                                TextInput::make('input_tiroides_app')
                                                    ->disabled(fn($get) => !$get('tiroides_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('hepatitis_app')->live()
                                                    ->label('Hepatitis'),
                                                TextInput::make('input_hepatitis_app')
                                                    ->disabled(fn($get) => !$get('hepatitis_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),

                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('moretones_frecuentes_app')->live()
                                                    ->label('Enfermedades Hematológicas'),
                                                TextInput::make('input_moretones_frecuentes_app')
                                                    ->disabled(fn($get) => !$get('moretones_frecuentes_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),
                                        Section::make()
                                            ->inlineLabel()
                                            ->schema([
                                                Toggle::make('psiquiatricas_app')->live()
                                                    ->label('Enfermedades Psiquiátricas'),
                                                TextInput::make('input_psiquiatricas_app')
                                                    ->disabled(fn($get) => !$get('psiquiatricas_app'))
                                                    ->label('Tipo (opcional)')
                                                    ->placeholder('----'),
                                            ]),

                                        Toggle::make('vih_app')
                                            ->label('VIH/SIDA'),
                                        Toggle::make('covid_app')
                                            ->label('COVID-19'),

                                    ])->columnSpanFull()->columns(2),
                                Fieldset::make()
                                    ->schema([
                                        TextArea::make('observations_pathological')
                                            ->label('Otros Antecedentes'),
                                    ])->columnSpanFull()->columns(1),
                            ])->columnSpanFull()->columns(3),
                    ])->columnSpanFull(),
                    Section::make('Antecedentes Quirúrgicos')
                    ->collapsible()
                    ->schema([
                        // ...
                        Section::make()
                            ->description('Campo de observación para especificar todos los antecedentes quirúrgicos que posea el paciente.')
                            ->schema([
                                Textarea::make('history_surgical')
                                    ->label('Antecedentes Quirúrgicos')
                                    ->autoSize()
                                    ->columnSpanFull(),
                        ])->columnSpanFull(),
                    ])->columnSpanFull(),
                    Section::make('Antecedentes No Patológicos')
                    ->collapsible()
                    ->schema([
                        Section::make()
                            ->description('Sección de selección multiple de antecedentes mas dos campo de observación para agregar información adicional y lo relacionado con el esquema de vacunación.')
                            ->schema([
                                Fieldset::make('Seleccionar Hábitos')
                                    ->schema([
                                        // ...
                                        Toggle::make('tabaco')
                                            ->label('Tabaquismo'),
                                        Toggle::make('alcohol')
                                            ->label('Alcohol'),
                                        Toggle::make('drogas')
                                            ->label('Drogas'),
                                    ])->columns(4),
                                Grid::make()
                                    ->schema([
                                        TextArea::make('observations_not_pathological')
                                            ->autoSize()
                                            ->label('OTROS ANTECEDENTES NO PATOLÓGICOS: (Hábitos de vida, higiénicos, alimenticios, vivienda, nivel educativo, ocupación, viajes realizados.)'),
                                        TextArea::make('esquema_vacunas')
                                            ->autoSize()
                                            ->label('Esquema de Vacunación'),
                                    ])->columnSpanFull()->columns(1),
                            ])
                    ])->columnSpanFull(),
                    Section::make('Alergias')
                    ->collapsible()
                    ->schema([
                        // ...
                        Section::make('Selección Múltiple')
                            ->description('Desplegable de Selección Múltiple de Alergias. Puede seleccionar una o varias alergias. Puede agregar alergias adicionales.')
                            ->schema([
                                Select::make('allergies')
                                    ->label('Alergias')
                                    ->options(AllergyList::all()->pluck('description', 'description')->toArray())
                                    ->multiple()
                                    ->searchable(),
                                Grid::make(1)
                                ->schema([
                                    Textarea::make('observations_allergies')
                                        ->autoSize()
                                        ->label('Otras Alergias'),
                                ])->columnSpanFull(),
                            ])->columnSpanFull()->columns(4),
                    ])->columnSpanFull(),
                    Section::make('Medicamentos y Suplementos')
                    ->collapsible()
                    ->schema([
                        // ...
                        Section::make()
                            ->description('Campo de observación para especificar todos los medicamentos y suplementos que posea el paciente.')
                            ->schema([
                                Textarea::make('medications_supplements')
                                    ->label('Medicamentos y Suplementos')
                                    ->autoSize(),
                                Textarea::make('observations_medication')
                                    ->label('Observaciones')
                                    ->autoSize(),
                            ])->columnSpanFull(),
                    ])->columnSpanFull(),
                    Section::make('Antecedentes Ginecológicos')
                    ->collapsible()
                    ->hidden(function ($get) {
                        if (null !== $get('telemedicine_patient_id')) {
                            $sex = TelemedicinePatient::find($get('telemedicine_patient_id'))->sex;
                            if ($sex == 'MASCULINO') {
                                return true;
                            }
                        }
                        return false;
                    })
                    ->schema([
                        // ...
                        Section::make()
                            ->description('Información ginecológica del paciente.')
                            ->schema([
                                TextInput::make('numero_embarazos')
                                    ->label('Número de Embarazos')
                                    ->numeric(),
                                TextInput::make('numero_partos')
                                    ->label('Número de Partos')
                                    ->numeric(),
                                TextInput::make('cesareas')
                                    ->label('Cesareas')
                                    ->numeric(),
                                TextInput::make('numero_abortos')
                                    ->label('Número de Abortos')
                                    ->numeric(),
                                Grid::make(1)
                                ->schema([
                                    Textarea::make('observations_ginecologica')
                                        ->autoSize()
                                        ->label('Observaciones Ginecológicas'),
                                ])->columnSpanFull()->columns(1),
                            ])->columnSpanFull()->columns(4),
                    ])->columnSpanFull(),
            ]);
    }
}