<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Schemas;

use Dompdf\Adapter\GD;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use App\Models\TelemedicineServiceList;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Fieldset;
use Symfony\Component\Mime\Part\DataPart;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Repeater\TableColumn;

class TelemedicineFollowUpForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Información Principal del Caso')
                        ->schema([
                            Fieldset::make('Información del Paciente')
                                ->schema([
                                    Select::make('telemedicine_case_id')
                                        ->label('Número de Caso')
                                        ->options(TelemedicineCase::all()->pluck('code', 'id'))
                                        ->live()
                                        ->default(fn (): ?int => request()->query('record') ? request()->query('record') : null)
                                        ->searchable()
                                        ->required(),
                                        Hidden::make('code')->default(function (Get $get): ?string {
                                            return TelemedicineCase::find(request()->query('record'))->code ?? '';
                                        }),
                            ])->columnSpanFull()->columns(4),
                            Grid::make()
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
                                ])->columnSpanFull(),
                            Hidden::make('telemedicine_patient_id'),
                            Hidden::make('telemedicine_doctor_id'),
                            Hidden::make('telemedicine_consultation_patient_id'),
                            Hidden::make('created_by')->default(Auth::user()->name),
                            Fieldset::make('Estatus del Caso')
                                ->schema([
                                    Radio::make('feedbackOne')
                                        ->label('El paciente ya se encuentra de ALTA?')
                                        ->boolean(falseLabel: 'No, asignar un nuevo servicio!')
                                        ->inline()
                                        ->default(true)
                                        ->live()
                                ])->columnSpanFull(),
                        ])->columns(4),
                // Step::make('Seguimiento')
                //     ->hidden(fn (Get $get) => $get('feedbackOne') == true) // Solo se muestra si el servicio es "Consulta General"
                //     ->schema([
                //         Fieldset::make('Fecha de Siguiente Seguimiento')
                //             ->hidden(fn (Get $get) => $get('feedback') == true)
                //             ->schema([
                //                 DatePicker::make('next_follow_up')
                //                     ->required()
                //                     ->live()
                //                     ->label('Fecha')
                //                     ->displayFormat('d/m/Y'),
                //                 TimePicker::make('hour')
                //                     ->prefixIcon(Heroicon::Clock)
                //                     ->required()
                //                     ->live()
                //                     ->seconds(false)
                //                     ->label('Hora de Seguimiento'),

                //             ])
                //     ]),
                    Step::make('Tipo de Servicio')
                        ->hidden(fn (Get $get) => $get('feedbackOne') == true) // Solo se muestra si el servicio es "Consulta General"
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
                    Step::make('Proximo Seguimiento')
                        ->hidden(fn (Get $get) => $get('feedbackOne') == true) // Solo se muestra si el servicio es "Consulta General"
                        ->schema([
                            Fieldset::make('Fecha de Siguiente Seguimiento')
                                ->schema([
                                    DatePicker::make('next_follow_up')
                                        ->required()
                                        ->live()
                                        ->label('Fecha')
                                        ->displayFormat('d/m/Y'),
                                    TimePicker::make('hour')
                                        ->prefixIcon(Heroicon::Clock)
                                        ->required()
                                        ->live()
                                        ->seconds(false)
                                        ->label('Hora de Seguimiento'),
                                ])
                        ]),
            ])
                ->columnSpanFull()
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button type="submit" size="sm">
                        Registrar Seguimiento
                    </x-filament::button>
                BLADE)))
            ]);
    }
}