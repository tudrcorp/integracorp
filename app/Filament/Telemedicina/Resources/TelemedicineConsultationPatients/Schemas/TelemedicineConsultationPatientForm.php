<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas;

use function Psy\debug;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Illuminate\Support\HtmlString;
use App\Models\TelemedicinePriority;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use App\Models\TelemedicineListStudy;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use App\Models\TelemedicineServiceList;
use App\Models\TelemedicineStudiesList;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\TelemedicineConsultationPatient;
use Filament\Forms\Components\Repeater\TableColumn;

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
                                ->schema([
                                    Fieldset::make()
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
                        
                                        ])->columnSpanFull()->columns(2),
                                ])
                                ->columnSpanFull(),
                        ]),
                    Step::make('Motivo de la Consulta')
                        ->hidden(function () use ($countCase) {
                            if($countCase < 1){
                                return false;
                            }
                            return true;
                        })
                        ->schema([
                            Section::make()
                                ->heading('Motivo de la Consulta')
                                ->schema([
                                    Fieldset::make()
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
                                                    
                                                    
                                                    Fieldset::make()
                                                        ->schema([
                                                            Textarea::make('background')
                                                                ->label('Antecedentes Asociados')
                                                                ->autosize()
                                                                ->afterStateUpdatedJs(<<<'JS'
                                                                                $set('background', $state.toUpperCase());
                                                                            JS),
                                                            Toggle::make('question')
                                                                ->label('No refiere antecedentes relacionados con la patología aguda que esta presentando'),
                                                        ])->columnSpanFull(),

                                                    Textarea::make('diagnostic_impression')
                                                        ->label('Impresión Diagnóstica')
                                                        ->autosize()
                                                        ->afterStateUpdatedJs(<<<'JS'
                                                            $set('diagnostic_impression', $state.toUpperCase());
                                                        JS),
                                                ])->columnSpanFull()->columns(2),
                                        ])->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ]),
                    Step::make('Cuestionario de Seguimiento')
                        //Este hidden es para ocultar el paso de seguimiento si solo hay un caso registrado (consulta inicial)
                        ->hidden(function () use ($countCase) {
                            if($countCase < 1){
                                return true;
                            }
                            return false;
                        })
                        ->schema([
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
                            Fieldset::make('Estatus del Caso')
                                ->schema([
                                    Radio::make('feedbackOne')
                                        ->label('El paciente ya se encuentra de ALTA?')
                                        ->boolean(falseLabel: 'No, asignar un nuevo servicio!')
                                        ->inline()
                                        ->live(),
                                ])->columnSpanFull(),
                        ]),
                    Step::make('Tipo de Servicio')
                        ->hidden(fn (Get $get) => $get('feedbackOne') == true)
                        ->schema([
                            // ...
                            Fieldset::make('Selección de Servicios')
                                ->schema([
                                    Select::make('telemedicine_service_list_id')
                                        ->label('Tipo de Servicio')
                                        ->live()
                                        ->options(function (Get $get) use ($countCase) {
                                            if($countCase < 1){
                                                return TelemedicineServiceList::where('level', 1)->get()->pluck('name', 'id');
                                            }
                                            return TelemedicineServiceList::all()->pluck('name', 'id');
                                        })
                                        // ->hint('Seleccione un servicio para ver detalles')
                                        // ->hintIcon('heroicon-m-information-circle', tooltip: 'Cada servicio incluye consulta médica virtual, seguimiento y receta digital.')
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
                                ])->columnSpanFull()->columns(3),
                        ]),
                    Step::make('Medicamentos e Indicaciones')
                        ->hidden(fn(Get $get) => $get('feedbackOne') == true)
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
                        ->hidden(fn(Get $get) => $get('feedbackOne') == true)
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
                                                ->label('Estudios de Imágenes (CIBERTOS)')
                                                ->live()
                                                ->options(TelemedicineListStudy::where('type', 'CUBIERTO')->get()->pluck('name', 'name'))
                                                ->multiple()
                                                ->helperText('Seleccione el/los estudios de Imágenes que requiera el paciente'),
                                            Select::make('other_studies')
                                                ->label(' Otros Estudios de Imágenes (NO CIBERTOS)')
                                                ->live()
                                                ->options(TelemedicineListStudy::where('type', 'NO CUBIERTO')->get()->pluck('name', 'name'))
                                                ->multiple()
                                                ->helperText('Seleccione el/los estudios de Imágenes que requiera el paciente'),
                                        ])->columnSpan(2)->columns(1),
                                    //...
                                ])->columns(3),
                        ]),
                    Step::make('Interconsulta con Especialista')
                        ->hidden(fn(Get $get) => $get('feedbackOne') == true)
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
                ->columnSpanFull(),
            ]);
    }
}