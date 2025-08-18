<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas;

use function Psy\debug;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Illuminate\Support\HtmlString;
use App\Models\TelemedicinePatient;

use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use App\Models\TelemedicineExamenList;
use App\Models\TelemedicineStudiesList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
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
                        ->description('...')
                        ->schema([
                            Section::make()
                            ->heading('Datos del Paciente')
                            ->description('...')
                            ->schema([
                                Fieldset::make()
                                    ->schema([
                                        Hidden::make('telemedicine_case_id')->default($case->id),
                                        Hidden::make('telemedicine_doctor_id')->default($case->telemedicine_doctor_id),
                                        Hidden::make('telemedicine_patient_id')->default($case->telemedicine_patient_id),
                                        TextInput::make('code_reference')
                                            ->label('Referencia')
                                            ->default('REF-'.rand(11111, 99999))
                                            ->required()
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('telemedicine_case_code')
                                            ->label('Código del Caso')
                                            ->default('CASO-'.$case->code)
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('full_name')
                                            ->label('Paciente')
                                            ->default($patient->full_name)
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('nro_identificacion')
                                            ->label('Número de Identificación')
                                            ->default($patient->nro_identificacion)
                                            ->disabled()
                                            ->dehydrated(),
                                    ])->columnSpanFull()->columns(4),
                            ])
                            ->columnSpanFull(),
                        ]),
                    Step::make('Motivo de la Consulta')
                        ->description('...')
                        ->schema([
                            Section::make()
                                ->heading('Motivo de la Consulta')
                                ->description('...')
                                ->schema([
                                    Fieldset::make()
                                        ->schema([
                                            Grid::make()
                                            ->schema([
                                                Select::make('type_service')
                                                ->label('Tipo de Servicio')
                                                ->options([
                                                    'TELEMEDICINA (TLM)' => 'TELEMEDICINA (TLM)',
                                                    'ATENCION MEDICA DOMICILIARIA (AMD)' => 'ATENCION MEDICA DOMICILIARIA (AMD)',
                                                    'MONITOREO TELEFÓNICO EVOLUTIVO' => 'MONITOREO TELEFÓNICO EVOLUTIVO',
                                                    'LECTURA DE RESULTADOS (LABORATORIOS)' => 'LECTURA DE RESULTADOS (LABORATORIOS)',
                                                    'LECTURA DE RESULTADOS (IMAGENOLOGÍA)' => 'LECTURA DE RESULTADOS (IMAGENOLOGÍA)',
                                                    'TRASLADO EN AMBULANCIA' => 'TRASLADO EN AMBULANCIA',
                                                    // 'APLICACION DE INYECTABLES' => 'APLICACION DE INYECTABLES',
                                                    // 'FISIOTERAPIA  Y REHABILITACIÓN' => 'FISIOTERAPIA  Y REHABILITACIÓN',
                                                    // 'LECTURA DE RESULTADOS' => 'LECTURA DE RESULTADOS',
                                                    // 'MEDICAMENTOS' => 'MEDICAMENTOS',
                                                    // 'SESION DE ONDAS DE CHOQUE' => 'SESION DE ONDAS DE CHOQUE',
                                                    // 'TRASLADO EMERGENCIA' => 'TRASLADO EMERGENCIA',
                                                    // 'TOMA UROANALISIS' => 'TOMA UROANALISIS',
                                                    // 'PUNCION ASPIRATIVA CON AGUJA FINA(PAAF) ECOGUIADA Y DRENAJE DE LIQUIDO' => 'PUNCION ASPIRATIVA CON AGUJA FINA(PAAF) ECOGUIADA Y DRENAJE DE LIQUIDO',
                                                    // 'VARICES GRADO VI + ULCERA INFECTADA EN TOBILLO DERECHO' => 'VARICES GRADO VI + ULCERA INFECTADA EN TOBILLO DERECHO',
                                                ])
                                            ])->columnSpanFull()->columns(3),
                                            Grid::make(1)
                                            ->schema([
                                                Textarea::make('reason_consultation')
                                                    ->label('Motivo de Consulta')
                                                    ->autosize(),
                                            ])->columnSpanFull()->columns(1),
                                            Grid::make(1)
                                            ->schema([
                                                Textarea::make('actual_phatology')
                                                    ->label('Enfermedad Actual')
                                                    ->autosize(),
                                            ])->columnSpanFull()->columns(1),
                                        ])->columnSpanFull()->columns(4),
                                ])
                                ->columnSpanFull(),
                        ]),
                    Step::make('Signos Vitales y Antecedentes')
                        ->schema([
                            // ...
                            Fieldset::make()
                                ->schema([
                                    TextInput::make('vs_pa'),
                                    TextInput::make('vs_fc'),
                                    TextInput::make('vs_fr'),
                                    TextInput::make('vs_temp'),
                                    TextInput::make('vs_sat'),
                                    TextInput::make('vs_weight'),
                                    Grid::make(1)
                                    ->schema([
                                        Textarea::make('background')
                                            ->label('Antecedentes')
                                            ->autosize(),
                                        
                                    ])->columnSpanFull(),
                                ])->columnSpanFull()->columns(6),
                        ]),
                    Step::make('Impresión Diagnóstica')
                        ->schema([
                            // ...
                            Fieldset::make()
                                ->schema([
                                    Textarea::make('diagnostic_impression')
                                        ->label('Impresión Diagnóstica')
                                        ->autosize(),
                                ])->columnSpanFull()->columns(1),
                        ]),
                    Step::make('Medicamentos e Indicaciones')
                        ->schema([
                            // ...
                            Fieldset::make()
                                ->schema([
                                    Textarea::make('medicines')
                                        ->label('Medicamentos')
                                        ->helperText('Debe indicar el nombre del medicamento y los miligramos separados por comas (,)')    
                                        ->autosize()
                                        ->required(),
                                    Textarea::make('indications')
                                        ->label('Indicaciones')
                                        ->helperText('Debe indicar el nombre del medicamento, la dosis y el período de uso separados por comas (,)')
                                        ->autosize()
                                        ->required(),
                                ])->columnSpanFull()->columns(1),
                        ]),
                    Step::make('Laboratorios y Estudios')
                        ->schema([
                            // ...
                            Fieldset::make()
                                ->schema([
                                    Select::make('labs')
                                        ->label('Estudios de Laboratorio')
                                        ->options(TelemedicineExamenList::all()->pluck('description', 'description'))
                                        ->multiple()
                                        ->helperText('Seleccione el/los estudios de Laboratorio que requiera el paciente'),
                                    Select::make('studies')
                                        ->label('Estudios de Imágenes')
                                        ->options(TelemedicineStudiesList::all()->pluck('description', 'description'))
                                        ->multiple()
                                        ->helperText('Seleccione el/los estudios de Imágenes que requiera el paciente'),
                                ])->columnSpanFull()->columns(1),
                        ]),
                ])
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Crear cotización
                    </x-filament::button>
                BLADE)))
                ->columnSpanFull(),

                
            ]);
    }
}