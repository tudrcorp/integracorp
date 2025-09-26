<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas;

use function Psy\debug;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use App\Models\TelemedicineStudiesList;
use App\Models\TelemedicineServiceList;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Repeater\TableColumn;

class TelemedicineConsultationPatientForm
{
    public static function configure(Schema $schema): Schema
    {
        //Variables recuperadas de la sesion del usuario
        //------------------------------------------------
        $case = session()->get('case');
        $patient = session()->get('patient');
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
                                            Hidden::make('telemedicine_case_id')->default($case->id)->hiddenOn('edit'),
                                            Hidden::make('telemedicine_doctor_id')->default($case->telemedicine_doctor_id)->hiddenOn('edit'),
                                            Hidden::make('telemedicine_patient_id')->default($case->telemedicine_patient_id)->hiddenOn('edit'),
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
                                                    Textarea::make('background')
                                                        ->label('Antecedentes')
                                                        ->autosize()
                                                        ->afterStateUpdatedJs(<<<'JS'
                                                        $set('background', $state.toUpperCase());
                                                    JS),
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
                    Step::make('Tipo de Servicio')
                        ->schema([
                            // ...
                            Fieldset::make('Selección de Servicios')
                                ->schema([
                                    Select::make('telemedicine_service_list_id')
                                        ->label('Tipo de Servicio')
                                        ->live()
                                        ->options(TelemedicineServiceList::all()->except(1)->pluck('name', 'id'))
                                        ->hint('Seleccione un servicio para ver detalles')
                                        ->hintIcon('heroicon-m-information-circle', tooltip: 'Cada servicio incluye consulta médica virtual, seguimiento y receta digital.')
                                        ->helperText(function (Get $get, $state) {
                                            $service = TelemedicineServiceList::find($state);
                                            return $service ? $service->description : '---';
                                        })
                                        ->searchable()
                                        ->required(),
                                    Grid::make(1)
                                        ->schema([
                                            Radio::make('feedback')
                                                ->label('Desea agregar algún tipo de tratamiento de forma preventiva?')
                                                ->live()
                                                ->options([
                                                    'si' => 'Si',
                                                    'no' => 'No',
                                                ])
                                                ->inline()
                                        ])->columnSpanFull(),
                                ])->columnSpanFull()->columns(3),
                        ]),
                    Step::make('Medicamentos e Indicaciones')
                        ->hidden(function (Get $get) {
                            if ($get('feedback') == 'si') {
                                return false;
                            }
                            return true;
                        }) // Solo se muestra si el servicio es "Consulta General"
                        ->schema([
                            // ...
                            Repeater::make('medications')
                                ->table([
                                    TableColumn::make('Medicamento'),
                                    TableColumn::make('Indicaciones'),
                                ])
                                ->schema([
                                    TextInput::make('medicines')
                                        ->required()
                                        ->afterStateUpdatedJs(<<<'JS'
                                            $set('medicines', $state.toUpperCase());
                                        JS),
                                    TextInput::make('indications')
                                        ->required()
                                        ->afterStateUpdatedJs(<<<'JS'
                                            $set('indications', $state.toUpperCase());
                                        JS)
                                ])
                        ]),
                    Step::make('Laboratorios y Estudios')
                        ->hidden(fn(Get $get): bool => $get('telemedicine_service_list_id') != 4) // Solo se muestra si el servicio es "Consulta General"
                        ->schema([
                            // ...
                            Grid::make()
                                ->schema([
                                    Fieldset::make('Estudios de Laboratorio')
                                        ->schema([
                                            Select::make('labs')
                                                ->label('Estudios de Laboratorio')
                                                ->options([
                                                    'HEMATOLOGÍA COMPLETA'              => 'HEMATOLOGÍA COMPLETA',
                                                    'VSG / PCR'                         => 'VSG / PCR',
                                                    'UROANÁLISIS'                       => 'UROANÁLISIS',
                                                    'COPROANÁLISIS'                     => 'COPROANÁLISIS',
                                                    'GLICEMIA'                          => 'GLICEMIA',
                                                    'UREA Y CREATININA'                 => 'UREA Y CREATININA',
                                                    'TGO / TPG (PERFIL HEPATICO)'       => 'TGO / TPG (PERFIL HEPATICO)',
                                                    'BILIRRUBINA TOTAL Y FRACCIONADA'   => 'BILIRRUBINA TOTAL Y FRACCIONADA',
                                                    'FOSFATASA ALCALINA'                => 'FOSFATASA ALCALINA',
                                                    'PERFIL 20'                         => 'PERFIL 20',
                                                ])
                                                ->multiple()
                                                ->helperText('Seleccione el/los estudios de Laboratorio que requiera el paciente'),
                                            TextInput::make('other_labs')
                                                ->label('Otros Estudios de Laboratorio') // BVA
                                                ->helperText('Ingrese otros estudios de Laboratorio que requiera el paciente'),
                                        ])->columns(1),
                                    Fieldset::make('Estudios de Imagenes')
                                        ->schema([
                                            Select::make('studies')
                                                ->label('Estudios de Imágenes')
                                                ->live()
                                                ->options([
                                                    'RX SIMPLE'       => 'RX SIMPLE (Por accidentes)',
                                                    'RX DE TORAX'     => 'RX DE TORAX (Por infección respiratoria)',
                                                    'ECO ABDOMINAL '  => 'ULTRASONIDO (Con fines diagnósticos)',
                                                ])
                                                ->multiple()
                                                ->helperText('Seleccione el/los estudios de Imágenes que requiera el paciente'),
                                            Fieldset::make('Ingrese la parte del cuerpo que requiera el estudio')
                                                ->hidden(fn(Get $get): bool => !in_array('RX SIMPLE', $get('studies')))
                                                ->schema([
                                                    Fieldset::make('HOMBRO')
                                                        ->schema([
                                                            Checkbox::make('hombro_izq')
                                                                ->label('IZQ'),
                                                            Checkbox::make('hombro_der')
                                                                ->label('DER'),
                                                            Checkbox::make('hombro_comp')
                                                                ->label('COMP'),
                                                        ])->columns(3),
                                                    Fieldset::make('CODO AP Y Lateral')
                                                        ->schema([
                                                            Checkbox::make('codo_izq')
                                                                ->label('IZQ'),
                                                            Checkbox::make('codo_der')
                                                                ->label('DER'),
                                                            Checkbox::make('codo_comp')
                                                                ->label('COMP'),
                                                        ])->columns(3),
                                                    Fieldset::make('MUÑECA AP Y Lateral')
                                                        ->schema([
                                                            Checkbox::make('muneca_izq')
                                                                ->label('IZQ'),
                                                            Checkbox::make('muneca_der')
                                                                ->label('DER'),
                                                            Checkbox::make('muneca_comp')
                                                                ->label('COMP'),
                                                        ])->columns(3),
                                                    Fieldset::make('MANO AP, Lateral y Oblicua')
                                                        ->schema([
                                                            Checkbox::make('mano_izq')
                                                                ->label('IZQ'),
                                                            Checkbox::make('mano_der')
                                                                ->label('DER'),
                                                            Checkbox::make('mano_comp')
                                                                ->label('COMP'),
                                                        ])->columns(3),
                                                    Fieldset::make('HUMERO')
                                                        ->schema([
                                                            Checkbox::make('humero_izq')
                                                                ->label('IZQ'),
                                                            Checkbox::make('humero_der')
                                                                ->label('DER'),
                                                            Checkbox::make('humero_comp')
                                                                ->label('COMP'),
                                                        ])->columns(3),
                                                    Fieldset::make('ANTEBRAZO')
                                                        ->schema([
                                                            Checkbox::make('ante_izq')
                                                                ->label('IZQ'),
                                                            Checkbox::make('ante_der')
                                                                ->label('DER'),
                                                            Checkbox::make('ante_comp')
                                                                ->label('COMP'),
                                                        ])->columns(3),
                                                    Fieldset::make('COLUMNA DORSO LUMBAR')
                                                        ->schema([
                                                            Checkbox::make('cdl_ap')
                                                                ->label('AP y LATERAL'),
                                                        ]),
                                                    Fieldset::make('PELVIS OSEA CENTRADA EN PUBIS')
                                                        ->schema([
                                                            Checkbox::make('pocep')
                                                                ->label('AP DE PIE'),
                                                        ]),
                                                    Fieldset::make('COLUMNA CERVICAL')
                                                        ->schema([
                                                            Checkbox::make('cc_ap')
                                                                ->label('AP'),
                                                            Checkbox::make('cc_oblicuas')
                                                                ->label('OBLICUAS'),
                                                            Fieldset::make('Lateral')
                                                                ->schema([
                                                                    Checkbox::make('cc_la_flexion')
                                                                        ->label('FLEXION'),
                                                                    Checkbox::make('cc_la_extension')
                                                                        ->label('EXTENSION'),
                                                                ])->columnSpanfull(),
                                                        ])->columns(2),
                                                    Fieldset::make('COLUMNA LUMBO SACRA')
                                                        ->schema([
                                                            Checkbox::make('cls_ap')
                                                                ->label('AP'),
                                                            Checkbox::make('cls_oblicuas')
                                                                ->label('OBLICUAS'),
                                                            Fieldset::make('Lateral')
                                                                ->schema([
                                                                    Checkbox::make('cls_la_flexion')
                                                                        ->label('FLEXION'),
                                                                    Checkbox::make('cls_la_extension')
                                                                        ->label('EXTENSION'),
                                                                ])->columnSpanfull(),
                                                        ])->columns(2),
                                                ]),
                                            TextInput::make('other_studies')
                                                ->label('Otros Estudios de Imágenes') // BVA
                                                ->helperText('Ingrese otros estudios de Imágenes que requiera el paciente')
                                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('other_studies', $state.toUpperCase());
                                                JS)
                                        ])->columnSpan(2)->columns(1),
                                    //...
                                ])->columns(3),
                        ]),
                    Step::make('Interconsulta con Especialista')
                        ->hidden(fn(Get $get): bool => $get('telemedicine_service_list_id') != 6) // Solo se muestra si el servicio es "Consulta General"
                        ->schema([
                            // ...
                            Fieldset::make()
                                ->schema([
                                    Select::make('consult_specialist')
                                        ->label('Interconsultas Especialistas para Patologías Agudas')
                                        ->multiple()
                                        ->options([
                                            'MÉDICO DE URGENCIAS'       => 'MÉDICO DE URGENCIAS',
                                            'TRAUMATÓLOGO'              => 'TRAUMATÓLOGO',
                                            'NEURÓLOGO'                 => 'NEURÓLOGO',
                                            'CARDIÓLOGO'                => 'CARDIÓLOGO',
                                            'NEUMÓLOGO'                 => 'NEUMÓLOGO',
                                            'GASTROENTERÓLOGO'          => 'GASTROENTERÓLOGO',
                                            'INFECTÓLOGO'               => 'INFECTÓLOGO',
                                            'NEFRÓLOGO'                 => 'NEFRÓLOGO',
                                            'ENDOCRINÓLOGO'             => 'ENDOCRINÓLOGO',
                                            'PSIQUIATRA DE URGENCIAS'   => 'PSIQUIATRA DE URGENCIAS',
                                            'CIRUJANO GENERAL'          => 'CIRUJANO GENERAL',
                                            'OFTALMÓLOGO'               => 'OFTALMÓLOGO',
                                            'OTORRINOLARINGÓLOGO'       => 'OTORRINOLARINGÓLOGO',
                                            'DERMATÓLOGO'               => 'DERMATÓLOGO',
                                            'ESIÓLOGO / MEDICINA CRÍTICA' => 'ANESTESIÓLOGO / MEDICINA CRÍTICA',
                                        ]),
                                    TextInput::make('other_specialist')
                                        ->label('Otros Especialistas') // BVA
                                        ->helperText('Ingrese otros especialistas que requiera el paciente')
                                        ->afterStateUpdatedJs(<<<'JS'
                                            $set('other_specialist', $state.toUpperCase());
                                        JS)
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