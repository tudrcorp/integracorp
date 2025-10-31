<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas;

use function Psy\debug;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Illuminate\Support\HtmlString;
use App\Models\PathologicalHistory;
use Illuminate\Support\Facades\Log;
use App\Models\TelemedicinePriority;
use Filament\Forms\Components\Radio;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Models\NoPathologicalHistory;
use App\Models\TelemedicineListStudy;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Illuminate\Support\Facades\Blade;
use App\Models\TelemedicineServiceList;
use App\Models\TelemedicineStudiesList;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Illuminate\Support\Facades\Session;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Enums\GridDirection;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\TelemedicineConsultationPatient;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Repeater\TableColumn;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Tables\TelemedicineConsultationPatientsTable;

class TelemedicineConsultationPatientForm
{
    public static function configure(Schema $schema): Schema
    {
        //Variables recuperadas de la sesion del usuario
        //------------------------------------------------
        $case = session()->get('case');
        $patient = session()->get('patient');
        $countCase = TelemedicineConsultationPatient::where('telemedicine_case_id', $case->id)->count();
        //------------------------------------------------

        return $schema
            ->components([
                Wizard::make([
                    
                    Step::make('Datos del Paciente')
                    ->schema([
                        Section::make()
                        ->heading('Datos del Paciente')
                        ->description('Información principal sobre el paciente')
                        ->schema([
                            Fieldset::make('Datos del Caso')
                            ->schema([
                                Hidden::make('telemedicine_case_id')->default($case->id),
                                Hidden::make('telemedicine_doctor_id')->default($case->telemedicine_doctor_id),
                                Hidden::make('telemedicine_patient_id')->default($case->telemedicine_patient_id),
                                Hidden::make('assigned_by')->default(Auth::user()->id),
                                Hidden::make('status')->default(function () use ($countCase) {
                                    if ($countCase < 1) {
                                        return 'CONSULTA INICIAL';
                                    }
                                    return 'EN SEGUIMIENTO';
                                }),
                                TextInput::make('code_reference')
                                    ->label('Referencia')
                                    ->default('REF-' . rand(11111, 99999))
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('telemedicine_case_code')
                                    ->label('Código del Caso')
                                    ->default($case->code)
                                    ->disabled()
                                    ->dehydrated(),
                            ])->columnSpanFull()->columns(6),

                            Fieldset::make('Información Adicional')
                            ->schema([
                                TextInput::make('full_name')
                                    ->label('Paciente')
                                    ->default($patient->full_name)
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('nro_identificacion')
                                    ->label('Número de Identificación')
                                    ->prefix('V-')
                                    ->default($patient->nro_identificacion)
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('sex')
                                    ->label('Sexo')
                                    ->default($patient->sex)
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('age')
                                    ->label('Edad')
                                    ->prefix(' Años')
                                    ->default($patient->age)
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('phone_ppal')
                                    ->label('Número de Teléfono Principal')
                                    ->default($case->patient_phone)
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('phone_secondary')
                                    ->label('Número de Teléfono Secundario')
                                    ->default($case->patient_phone_2)
                                    ->disabled()
                                    ->dehydrated(),
                                TextArea::make('address')
                                    ->autosize()
                                    ->label('Dirección')
                                    ->helperText('Direccion descrita por el paciente al momento de la asignación del caso.')
                                    ->default($case->patient_address)
                                    ->disabled()
                                    ->columnSpanFull()
                                    ->dehydrated(),
                                TextArea::make('directionAmbulance')
                                    ->autosize()
                                    ->label('Dirección alternativa para estacionamiento de Ambulancia')
                                    ->helperText('Esta en la dirección alternativa donde el paciente puede recibir un servicio de ambulancia.')
                                    ->default($case->directionAmbulance)
                                    ->disabled()
                                    ->columnSpanFull()
                                    ->dehydrated()
                                    ->hidden(fn () => $case->directionAmbulance == null),
                            ])->columnSpanFull()->columns(3)
                        ])
                        ->columnSpanFull(),
                    ]),
                    
                    Step::make('Motivo de la Consulta')
                    ->hidden(function () use ($countCase) {
                        
                        $action = session()->get('action') ?? null;
                        
                        if($countCase < 1) {
                            return false;
                        }
                        if(isset($action) && $action == 'edit' && session()->get('status') == 'CONSULTA INICIAL') {
                            return false;
                        }

                        return true;
                    })
                    ->schema([
                        Fieldset::make('Información sobre Signos Vitales')
                        ->schema([
                            TextInput::make('pa')
                                ->label('Presión Arterial')
                                ->helperText('Presión Arterial (mmHg)')
                                ->numeric()
                                ->prefixIcon('healthicons-f-i-utensils'),
                            TextInput::make('fc')
                                ->label('Frecuencia Cardíaca')
                                ->helperText('Frecuencia Cardíaca (lpm)')
                                ->numeric()
                                ->prefixIcon('healthicons-f-i-utensils'),
                            TextInput::make('fr')
                                ->label('Frecuencia Respiratoria')
                                ->helperText('Frecuencia Respiratoria (rpm)')
                                ->numeric()
                                ->prefixIcon('healthicons-f-i-utensils'),
                            TextInput::make('temp')
                                ->label('Temperatura')
                                ->helperText('Temperatura (°C)')
                                ->numeric()
                                ->prefixIcon('healthicons-f-i-utensils'),
                            TextInput::make('saturacion')
                                ->label('Saturación')
                                ->helperText('Saturación (% de oxigeno en sangre)')
                                ->numeric()
                                ->prefixIcon('healthicons-f-i-utensils'),
                        ])->columnSpanFull()->columns(5),
                        
                        Fieldset::make('Indice de Masa Corporal (IMC)')
                        ->schema([
                            TextInput::make('peso')
                                ->label('Peso')
                                ->helperText('Peso (kg), el punto(.) es el separador de decimales. Ej: 60.5')
                                ->numeric()
                                ->live(onBlur: true)
                                ->prefixIcon('healthicons-f-i-utensils')
                                ->required(),
                            TextInput::make('estatura')
                                ->label('Estatura')
                                ->helperText('Metros(mts), el punto(.) es el separador de decimales, Ej: 1.70')
                                ->numeric()
                                ->live(onBlur: true)
                                ->prefixIcon('healthicons-f-i-utensils')
                                ->afterStateUpdated(function (string $context, $state, Set $set, Get $get) {
                                    $cal = $get('peso') / ($get('estatura') * $get('estatura'));
                                    $set('imc', round($cal, 2));
                                })
                                ->required(),
                            TextInput::make('imc')
                                //peso/estatura * 2
                                ->label('Indice de Masa Corporal (IMC)')
                                ->helperText('')
                                ->numeric()
                                ->disabled()
                                ->dehydrated()
                                ->prefixIcon('healthicons-f-i-utensils'),
                        ])->columnSpanFull()->columns(3),
                        
                        Fieldset::make('Consulta')
                        ->schema([
                            Grid::make(1)
                            ->schema([
                                Textarea::make('reason_consultation')
                                    ->label('Motivo de Consulta')
                                    ->autosize()
                                    ->default($case->reason)
                                    ->afterStateUpdatedJs(<<<'JS'
                                    $set('reason_consultation', $state.toUpperCase());
                                JS),
                                Textarea::make('actual_phatology')
                                    ->label('Enfermedad Actual')
                                    ->autosize()
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('actual_phatology', $state.toUpperCase());
                                    JS),
                                
                                Textarea::make('background')
                                    ->label('Antecedentes Asociados')
                                    ->autosize()
                                    ->default(function () {
                                        $history = session()->get('patologicalHistorySelected');
                                        Log::info($history);
                                        if ($history) {
                                            return $history;
                                        }
                                        return null;
                                    })
                                    ->belowContent([
                                        // Icon::make(Heroicon::InformationCircle),
                                        // 'This is the user\'s full name.',
                                        Action::make('associatePathologicalHistory')
                                            ->label('Asociar Antecedente')
                                            ->color('no-urgente')
                                            ->icon('heroicon-s-share')
                                            ->slideOver()
                                            ->modalHeading('Histórico de Antecedentes No Patológicos')
                                            ->modalContent(function () {
                                                
                                                $patient = session()->get('patient');
                                                $records = $patient?->telemedicinePatientHistory()->orderByDesc('created_at')->get()->first();
                                                $record = $records->toArray();
                                                $history = NoPathologicalHistory::where('telemedicine_history_patient_id', $record['id'])->get();

                                                return view('pathological-history-table', ['records' => $history]);
                                            })
                                            ->action(function (Action $action, Component $livewire) use ($case, $patient) { // 👈 INYECTA Component $livewire

                                                // 1. **Lógica de procesamiento (Aquí se establece la sesión)**
                                                // Si la modal tiene campos, se procesan aquí.
                                                $nuevoValorDeSesion = session()->get('patologicalHistorySelected');
                                                // Session::put('patologicalHistorySelected', $nuevoValorDeSesion);

                                                // 2. **Sincronización del estado (EL PASO CLAVE)**
                                                // Accede al formulario del componente Livewire y establece el valor del campo 'background'.
                                                $livewire->form->fill([
                                                    'telemedicine_case_id'      => $case->id,    
                                                    'telemedicine_doctor_id'    => $case->telemedicine_doctor_id,
                                                    'telemedicine_patient_id'   => $case->telemedicine_patient_id,
                                                    'assigned_by'               => Auth::user()->id,
                                                    'status'                    => 'CONSULTA INICIAL',
                                                    'code_reference'            => 'REF-' . rand(11111, 99999),
                                                    'full_name'                 => $case->patient_name,
                                                    'telemedicine_case_code'    => $case->code,
                                                    'nro_identificacion'        => $patient->nro_identificacion,
                                                    'age'                       => $patient->age,
                                                    'sex'                       => $patient->sex,
                                                    'phone_ppal'                => $case->patient_phone,
                                                    'phone_secondary'           => $case->patient_phone_2,
                                                    'address'                   => $case->patient_address,
                                                    'reason_consultation'       => $case->reason,
                                                    'background'                => $nuevoValorDeSesion,
                                                ]);
                                            })
                                            ->hidden(function () use ($countCase) {
                                                $patient = session()->get('patient');
                                                $exist = $patient?->noPathologicalHistories()->exists();
                                                if ($exist) {
                                                    //... Si el paciente tiene historia registrada lo muestro!
                                                    return false;
                                                }
                                                //... Si el paciente no tiene historia registrada lo oculto!
                                                return true;
                                            })
                                    ])
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('background', $state.toUpperCase());
                                    JS),

                                Textarea::make('diagnostic_impression')
                                    ->label('Impresión Diagnóstica')
                                    ->autosize()
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('diagnostic_impression', $state.toUpperCase());
                                    JS),

                            //...Asignación de Servicio
                            Fieldset::make('Asignación de Servicio y Actualización de Priroridad')
                                ->hidden(function (Get $get) {
                                    if ($get('feedbackOne') == false) {
                                        return false;
                                    }
                                    return true;
                                })
                                ->schema([
                                    Select::make('telemedicine_service_list_id')
                                        ->label('Tipo de Servicio')
                                        ->live()
                                        ->options(function (Get $get) use ($countCase) {
                                            if ($countCase < 1) {
                                                return TelemedicineServiceList::where('level', 1)->get()->pluck('name', 'id');
                                            }
                                            return TelemedicineServiceList::all()->pluck('name', 'id');
                                        })
                                        ->helperText(function (Get $get, $state) {
                                            $service = TelemedicineServiceList::find($state);
                                            return $service ? $service->description : '---';
                                        })
                                        ->searchable()
                                        ->required(),
                                    Select::make('telemedicine_priority_id')
                                        ->label('Prioridad de Servicio')
                                        ->live()
                                        ->options(TelemedicinePriority::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->required(),
                                    CheckboxList::make('complements')
                                        ->hidden(function (Get $get) {
                                            if ($get('telemedicine_service_list_id') == 2) {
                                                return true;
                                            }
                                            return false;
                                        })
                                        ->label('Complementos')
                                        ->columnSpanFull(1)
                                        ->live()
                                        ->gridDirection(GridDirection::Row)
                                        ->options([
                                            1 => 'Asignación de Medicamentos',
                                            2 => 'Indicación de Laboratorios o Estudios de Imagenología',
                                            3 => 'Consulta con Especialista',
                                        ])
                                ])->columnSpanFull()->columns(4),
                                    
                            ])->columnSpanFull()->columns(2),
                        ])->columnSpanFull(),

                        Fieldset::make('Observaciones')
                        ->schema([
                            Grid::make(4)
                            ->schema([
                                Select::make('priorityMonitoring')
                                ->label('Próximo Seguimiento')
                                ->required()
                                ->options([
                                    30  => '30 minutos',
                                    60  => '60 minutos',
                                    90  => '90 minutos',
                                    120 => '120 minutos',
                                    150 => '150 minutos',
                                    180 => '180 minutos',
                                ]),    
                            ]),
                            Textarea::make('observations')
                                ->label('Observaciones')
                                ->autosize()
                        ])->columnSpanFull()->columns(1),

                    ]),
                    
                    Step::make('Cuestionario de Seguimiento')
                    ->hidden(function () use ($countCase) {

                        $action = session()->get('action') ?? null;
                        
                        if($countCase < 1){
                            return true;
                        }

                        if (isset($action) && $action == 'edit' && session()->get('status') == 'CONSULTA INICIAL') {
                            return true;
                        }
                        
                        return false;
                    })
                    ->schema([
                        //...Preguntas
                        Fieldset::make('Preguntas de Seguimiento')
                        ->schema([
                            Textarea::make('cuestion_1')
                                ->label('1.- ¿COMO SE SIENTE EL DIA DE HOY?')
                                ->required()
                                ->live()
                                ->autosize()
                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_1', $state.toUpperCase());
                                                JS),
                            Textarea::make('cuestion_2')
                                ->label('2.- ¿COMO HA RESPONDIDO AL TRATAMIENTO INDICADO?')
                                ->required()
                                ->autosize()
                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_2', $state.toUpperCase());
                                                JS),
                            Textarea::make('cuestion_3')
                                ->label('3. ¿SIENTE QUE HAN MEJORADO LOS SÍNTOMAS?')
                                ->required()
                                ->autosize()
                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_3', $state.toUpperCase());
                                                JS),
                            Textarea::make('cuestion_4')
                                ->label('4. ¿SE REALIZO LOS ESTUDIOS SOLICITADOS?')
                                ->required()
                                ->autosize()
                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_4', $state.toUpperCase());
                                                JS),
                            Textarea::make('cuestion_5')
                                ->label('5. EN VISTA DE QUE SUS RESULTADOS DE LABORATORIO ESTÁN ALTERADOS, SE MODIFICAN LAS INDICACIONES MEDICAS.')
                                ->required()
                                ->autosize()
                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_5', $state.toUpperCase());
                                                JS),
                        ])->columnSpanFull()->columns(2),

                        //...Estatus de caso
                        Fieldset::make('Estatus del Caso')
                        ->schema([
                            ToggleButtons::make('feedbackOne')
                                ->label('El paciente ya se encuentra de ALTA?')
                                ->boolean(trueLabel: 'Si')
                                ->boolean(falseLabel: 'No, asignar un nuevo servicio!')
                                ->default(false)
                                ->grouped()
                                ->live(),
                        ])->columnSpanFull(),

                        //...Asignación de Servicio
                        Fieldset::make('Asignación de Servicio y Actualización de Prioridad')
                        ->visible(function (Get $get) {
                            if ($get('feedbackOne') == true) {
                                return false;
                            }
                            return true;
                        })
                        ->schema([
                            Select::make('telemedicine_service_list_id')
                                ->label('Tipo de Servicio')
                                ->live()
                                ->options(function (Get $get) use ($countCase) {
                                    if ($countCase < 1) {
                                        return TelemedicineServiceList::where('level', 1)->get()->pluck('name', 'id');
                                    }
                                    return TelemedicineServiceList::all()->pluck('name', 'id');
                                })
                                ->helperText(function (Get $get) {
                                    return 'Seleccione un servicio para ver detalles';
                                })
                                ->helperText(function (Get $get, $state) {
                                    $service = TelemedicineServiceList::find($state);
                                    return $service ? $service->description : '---';
                                })
                                ->searchable()
                                ->required(),
                            Select::make('telemedicine_priority_id')
                                ->label('Prioridad de Servicio')
                                ->live()
                                ->options(TelemedicinePriority::all()->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                            CheckboxList::make('complements')
                                ->hidden(function (Get $get) {
                                    if ($get('telemedicine_service_list_id') == 2) {
                                        return true;
                                    }
                                    return false;
                                })
                                ->label('Complementos')
                                ->columnSpanFull(1)
                                ->live()
                                ->gridDirection(GridDirection::Row)
                                ->options([
                                    1 => 'Asigancion de Medicamentos',
                                    2 => 'Indicacion de Laboratorios o Estudios de Imagenologia',
                                    3 => 'Consulta con Especialista',
                                ])
                        ])->columnSpanFull()->columns(3)->hiddenOn('edit'),

                        Fieldset::make('Observaciones')
                        ->visible(function (Get $get) {
                            if ($get('feedbackOne') == true) {
                                return false;
                            }
                            return true;
                        })
                        ->schema([
                            Grid::make(4)
                                ->schema([
                                    Select::make('priorityMonitoring')
                                        ->label('Próximo Seguimiento')
                                        ->required()
                                        ->options([
                                            30 => '30 minutos',
                                            60 => '60 minutos',
                                            90 => '90 minutos',
                                            120 => '120 minutos',
                                            150 => '150 minutos',
                                            180 => '180 minutos',
                                            24  => '24 horas',
                                            48  => '48 horas',
                                            72  => '72 horas',
                                        ]),
                                ]),
                            Textarea::make('observations')
                                ->label('Observaciones')
                                ->autosize()
                        ])->columnSpanFull()->columns(1),
                    ]),
                    
                    Step::make('Medicamentos e Indicaciones')
                    ->hidden(fn(Get $get) => $get('feedbackOne') == true || $get('telemedicine_service_list_id') == 2 || !in_array(1, $get('complements')))
                    ->schema([
                        // ...
                        Repeater::make('medications')
                            ->table([
                                TableColumn::make('Medicamento'),
                                TableColumn::make('Indicaciones'),
                                TableColumn::make('Duración(en días)'),
                            ])
                            ->schema([
                                TextInput::make('medicines')
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('medicines', $state.toUpperCase());
                                    JS),
                                TextInput::make('indications')
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('indications', $state.toUpperCase());
                                    JS),
                                TextInput::make('duration')
                                    ->numeric()
                                    ->regex('/^[0-9]*$/')
                                    ->required(),
                            ])
                    ]),
                    
                    Step::make('Laboratorios y Estudios de Imagenología')
                    ->hidden(fn(Get $get) => $get('feedbackOne') == true || $get('telemedicine_service_list_id') == 2 || !in_array(2, $get('complements')))
                    ->schema([
                        // ...
                        Grid::make()
                            ->schema([
                                Fieldset::make('Exámenes Laboratorios')
                                    ->schema([
                                        Select::make('labs')
                                            ->label('Laboratorios (CUBIERTOS)')
                                            ->options(TelemedicineListLaboratory::where('type', 'CUBIERTO')->get()->pluck('name', 'name'))
                                            ->multiple()
                                            ->helperText('Seleccione el/los exámenes de Laboratorio que requiera el paciente'),
                                        Select::make('other_labs')
                                            ->label('Otros Laboratorio (NO CUBIERTOS)')
                                            ->options(TelemedicineListLaboratory::where('type', 'NO CUBIERTO')->get()->pluck('name', 'name'))
                                            ->multiple()
                                            ->helperText('Seleccione el/los exámenes de Laboratorio que requiera el paciente'),
                                    ])->columns(1),
                                Fieldset::make('Imagenología')
                                    ->schema([
                                        Select::make('studies')
                                            ->label('Estudios de Imágenes (CUBIERTOS)')
                                            ->live()
                                            ->options(TelemedicineListStudy::where('type', 'CUBIERTO')->get()->pluck('name', 'name'))
                                            ->multiple()
                                            ->helperText('Seleccione el/los estudios de Imágenes que requiera el paciente'),
                                        Select::make('other_studies')
                                            ->label(' Otros Estudios de Imágenes (NO CUBIERTOS)')
                                            ->live()
                                            ->options(TelemedicineListStudy::where('type', 'NO CUBIERTO')->get()->pluck('name', 'name'))
                                            ->multiple()
                                            ->helperText('Seleccione el/los estudios de Imágenes que requiera el paciente'),
                                    ])->columnSpan(2)->columns(1),
                                //...
                            ])->columns(3),
                    ]),
                    
                    Step::make('Interconsulta con Especialista')
                    ->hidden(fn(Get $get) => $get('feedbackOne') == true || $get('telemedicine_service_list_id') == 2 || !in_array(3, $get('complements')))
                    ->schema([
                        // ...
                        Fieldset::make()
                            ->schema([
                                Select::make('consult_specialist')
                                    ->label('Interconsultas Especialistas para Patologías Agudas')
                                    ->options(TelemedicineListSpecialist::where('type', 'CUBIERTO')->get()->pluck('name', 'name'))
                                    ->multiple(),
                                Select::make('other_specialist')
                                    ->label('Otros Especialistas') // BVA
                                    ->options(TelemedicineListSpecialist::where('type', 'CUBIERTO')->get()->pluck('name', 'name'))
                                    ->multiple(),
                            ])->columnSpanFull()->columns(2),
                    ]),
                ])
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button type="submit" size="sm">
                        Registrar Consulta
                    </x-filament::button>
                BLADE)))
                ->hidden(fn() => session()->get('redCode'))
                ->columnSpanFull(),
            ]);
    }
}