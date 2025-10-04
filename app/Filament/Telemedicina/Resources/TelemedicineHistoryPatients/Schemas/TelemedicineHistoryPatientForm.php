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
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;

class TelemedicineHistoryPatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Información Principal')
                        ->schema([
                            Section::make()
                            ->heading('Datos principales del paciente')
                            ->description('...')
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
                        ])->columns(3),
                    Step::make('Signos Vitales')
                        ->schema([
                            Section::make()
                                ->heading('Signos Vitales')
                                ->description('Los signos vitales serán tomados al momento de realizar una Asistencia Medica Domiciliaria (AMD) o en sitio.')
                                ->schema([
                                    // ...
                                    TextInput::make('vs_pa')
                                        ->label('Presión Arterial')
                                        ->helperText('Presión Arterial (mmHg)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils')
                                        ->required(),
                                    TextInput::make('vs_fc')
                                        ->label('Frecuencia Cardíaca')
                                        ->helperText('Frecuencia Cardíaca (lpm)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils')
                                        ->required(),
                                    TextInput::make('vs_fr')
                                        ->label('Frecuencia Respiratoria')
                                        ->helperText('Frecuencia Respiratoria (rpm)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils')
                                        ->required(),
                                    TextInput::make('vs_temp')
                                        ->label('Temperatura')
                                        ->helperText('Temperatura (°C)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils')
                                        ->required(),
                                    TextInput::make('vs_sat')
                                        ->label('Saturación')
                                        ->helperText('Saturación (% de oxigeno en sangre)')
                                        ->numeric()
                                        ->prefixIcon('healthicons-f-i-utensils')
                                        ->required(),
                                ])->columnSpanFull()->columns(5),
                        ]),
                    Step::make('Antecedentes Patológicos Familiares')
                        ->schema([
                            Section::make()
                                ->schema([
                                    Fieldset::make('Seleccionar Antecedentes')
                                        ->schema([
                                            Toggle::make('tension_alta')
                                                ->label('Hipertensión Arterial'),

                                            Toggle::make('diabetes')
                                                ->label('Diábetes Mellitus'),

                                            Toggle::make('asma')
                                                ->label('Asma Bronquial'),

                                            Toggle::make('cardiacos')
                                                ->label('Enfermedades Cardíacas'),

                                            Toggle::make('gastritis_ulceras')
                                                ->label('Gastropatias'),

                                            Toggle::make('enfermedad_autoimmune')
                                                ->label('Enfermedad Autoimmune'),


                                            Toggle::make('trombosis_embooleanas')
                                                ->label('Insuficiencia Venosa'),

                                            Toggle::make('fracturas')
                                                ->label('Traumatismos'),

                                            // Toggle::make('alteraciones_coagulacion')
                                            //     ->label('Alteraciones de Coagulación'),
                                            Toggle::make('cancer')
                                                ->label('Cáncer'),

                                            Toggle::make('tranfusiones_sanguineas')
                                                ->label('Anemia'),

                                            Toggle::make('tiroides')
                                                ->label('Tiroides'),

                                            Toggle::make('hepatitis')
                                                ->label('Hepatitis'),

                                            Toggle::make('moretones_frecuentes')
                                                ->label('Enfermedades Hematológicas'),

                                            Toggle::make('psiquiatricas')
                                                ->label('Enfermedades Psiquiátricas'),

                                            Toggle::make('COVID19')
                                                ->label('COVID-19'),
                                        ])->columnSpanFull()->columns(3),
                                    Fieldset::make()
                                        ->schema([
                                            TextArea::make('observations_personal')
                                                ->label('Observaciones Antecedentes Personales'),
                                        ])->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(3),
                        ]),
                    Step::make('Antecedentes Patológicos Personales')
                        ->schema([
                            Section::make()
                                ->schema([
                                    // ...
                                    Fieldset::make('Seleccionar Antecedentes')
                                        ->schema([

                                            Toggle::make('tension_alta')
                                                ->label('Hipertensión Arterial'),
                                                
                                            Toggle::make('diabetes')
                                                ->label('Diábetes Mellitus'),

                                            Toggle::make('asma')
                                                ->label('Asma Bronquial'),

                                            Toggle::make('cardiacos')
                                                ->label('Enfermedades Cardíacas'),

                                            Toggle::make('gastritis_ulceras')
                                                ->label('Gastropatias'),

                                            Toggle::make('enfermedad_autoimmune')
                                                ->label('Enfermedad Autoimmune'),

                                            Toggle::make('vih')
                                                ->label('VIH/SIDA'),


                                            Toggle::make('trombosis_embooleanas')
                                                ->label('Insuficiencia Venosa'),

                                            Toggle::make('fracturas')
                                                ->label('Traumatismos'),

                                            // Toggle::make('alteraciones_coagulacion')
                                            //     ->label('Alteraciones de Coagulación'),
                                            Toggle::make('cancer')
                                                ->label('Cáncer'),

                                            Toggle::make('tranfusiones_sanguineas')
                                                ->label('Anemia'),
                                            
                                            Toggle::make('tiroides')
                                                ->label('Tiroides'),

                                            Toggle::make('hepatitis')
                                                ->label('Hepatitis'),

                                            Toggle::make('moretones_frecuentes')
                                                ->label('Enfermedades Hematológicas'),

                                            Toggle::make('transfusiones_sanguineas')
                                                ->label('Transfusiones Sanguineas'),

                                            Toggle::make('psiquiatricas')
                                                ->label('Enfermedades Psiquiátricas'),

                                            Toggle::make('COVID19')
                                                ->label('COVID-19'),

                                        ])->columnSpanFull()->columns(5),
                                    Fieldset::make()
                                        ->schema([
                                            TextArea::make('observations_pathological')
                                                ->label('Otros Antecedentes'),
                                        ])->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(3),
                        ]),
                    Step::make('Antecedentes Quirúrgicos')
                        ->schema([
                            // ...
                            Fieldset::make()
                                ->schema([
                                    Textarea::make('history_surgical')
                                        ->label('Antecedentes Quirúrgicos')
                                        ->autoSize()
                                        ->columnSpanFull(),
                            ])->columnSpanFull(),
                        ]),
                    Step::make('Antecedentes No Patológicos')
                        ->schema([
                            Section::make()
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
                        ]),
                    
                    Step::make('Alergias')
                        ->schema([
                            // ...
                            Fieldset::make('Selección Múltiple')
                                ->schema([
                                    Select::make('allergies')
                                        ->label('Alergias')
                                        ->options(AllergyList::all()->pluck('description', 'description')->toArray())
                                        ->multiple()
                                        ->searchable(),
                                ]),
                            Grid::make(1)
                                ->schema([
                                    Textarea::make('observations_allergies')
                                        ->autoSize()
                                        ->label('Otras Alergias'),
                                ])->columnSpanFull()->columns(1),
                        ]),
                    Step::make('Medicamentos y Suplementos')
                        ->schema([
                            // ...
                            Fieldset::make()
                                ->schema([
                                    Textarea::make('medications_supplements')
                                        ->label('Medicamentos y Suplementos')
                                        ->autoSize(),
                                    Textarea::make('observations_medication')
                                        ->label('Observaciones')
                                        ->autoSize(),
                                ])->columnSpanFull(),
                        ])->columnSpanFull()->columns(2),
                    Step::make('Antecedentes Ginecologicos')
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
                            Fieldset::make()
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
                                ])->columnSpanFull()->columns(4),
                            Grid::make(1)
                                ->schema([
                                    Textarea::make('observations_ginecologica')
                                        ->autoSize()
                                        ->label('Observaciones Ginecológicas'),
                                ])->columnSpanFull()->columns(1),
                        ])->columns(4),
                ])
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Crear Historia Clinica
                    </x-filament::button>
                BLADE)))
                ->columnSpanFull(), 
            ]);
    }
}