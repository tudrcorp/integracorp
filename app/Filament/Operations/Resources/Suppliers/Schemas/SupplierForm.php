<?php

namespace App\Filament\Operations\Resources\Suppliers\Schemas;

use App\Models\City;
use App\Models\State;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Repeater\TableColumn;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->description('Información de la entidad.')
                    ->collapsed()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Proveedor')
                            ->required()
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('name', $state.toUpperCase());
                            JS),
                        Select::make('status_convenio')
                            ->label('Estado del Convenio')
                            ->required()
                            ->options([
                                'GENERAL'           => 'GENERAL',
                                'PPP BAJO COSTO'    => 'PPP BAJO COSTO',
                                'PPP GEOGRAFICO'    => 'PPP GEOGRAFICO',
                            ]),
                        Select::make('tipo_clinica')
                            ->label('Tipo de Clinica')
                            ->searchable()
                            ->required()
                            ->options([
                                'A'                 => 'A',
                                'B'                 => 'B',
                                'C'                 => 'C',
                                'NO APLICA'         => 'NO APLICA',
                                'NO CLASIFICADO'    => 'NO CLASIFICADO'
                            ]),
                        Select::make('tipo_servicio')
                            ->label('Tipo de Servicio')
                            ->searchable()
                            ->options([
                                'A-NIVEL-NACIONAL'  => 'A-NIVEL-NACIONAL',
                                'MULTI-ESTADO'      => 'MULTI-ESTADO',
                            ]),
                        Select::make('state_services')
                            ->label('Estado donde Presta Servicios')
                            ->searchable()
                            ->multiple()
                            ->options(State::all()->pluck('definition', 'definition')),
                        Select::make('state_id')
                            ->options(State::all()->pluck('definition', 'id'))
                            ->label('Estado')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('city_id')
                            ->options(fn(Get $get) => City::where('state_id', $get('state_id'))->pluck('definition', 'id'))
                            ->label('Ciudad')
                            ->live()
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('clasificacion')
                            ->label('Clasificación del Proveedor')
                            ->searchable()
                            ->options([
                                'AEROAMBULANCIA'                    => 'AEROAMBULANCIA',
                                'AMBULANCIA'                        => 'AMBULANCIA',
                                'AMD-REGIONAL'                      => 'AMD-REGIONAL',
                                'APS-ATENCION-PRIMARIA-DE-SALUD'    => 'APS-ATENCIÓN-PRIMARIA-DE-SALUD',
                                'CARDIOLOGIA'                       => 'CARDIOLOGÍA',
                                'CLINICA'                           => 'CLÍNICA',
                                'ENFERMERAS / HOME CARE'            => 'ENFERMERAS / HOME CARE',
                                'FARMACIA'                          => 'FARMACIA',
                                'GASTROENTEROLOGIA'                 => 'GASTROENTEROLOGÍA',
                                'GINECOLOGIA'                       => 'GINECOLOGÍA',
                                'IMAGENOLOGIA'                      => 'IMAGENOLOGIA',
                                'INSUMOS Y EQUIPOS MEDICOS'         => 'INSUMOS Y EQUIPOS MEDICOS',
                                'LABORATORIO'                       => 'LABORATORIO',
                                'MEDICINA OCUPACIONAL'              => 'MEDICINA OCUPACIONAL',   
                                'ODONTOLOGIA'                       => 'ODONTOLOGIA',
                                'OFTALMOLOGIA'                      => 'OFTALMOLOGIA',
                                'ONCOLOGIA'                         => 'ONCOLOGIA',
                                'OPTICA'                            => 'OPTICA',
                                'OZONOTERAPIA'                      => 'OZONOTERAPIA',
                                'REHABILITACION'                    => 'REHABILITACION',
                                'SERVICIOS DE DIALISIS'             => 'SERVICIOS DE DIALISIS',
                                'UNIDAD QUIRURGICA AMBULATORIA'     => 'UNIDAD QUIRURGICA AMBULATORIA',
                                'UNIDAD GINECOLOGICA'               => 'UNIDAD GINECOLOGICA',
                                'UNIDAD ODONTOLOGICA'               => 'UNIDAD ODONTOLOGICA',
                                'UNIDAD OFTALMOLOGICA'              => 'UNIDAD OFTALMOLOGICA',
                                'UNIDAD PODOLOGIA'                  => 'UNIDAD PODOLOGIA',
                                'UNIDAD TRAUMATOLOGICA'             => 'UNIDAD TRAUMATOLOGICA',
                                'URGENT CARE'                       => 'URGENT CARE',
                                'URGENT CARE AMD'                   => 'URGENT CARE AMD',
                                'URGENT CARE APS'                   => 'URGENT CARE APS',
                                'URGENT CARE CLINICA'               => 'URGENT CARE CLINICA',
                                
                            ]),
                        Select::make('status_sistema')
                            ->label('Estado del Sistema')
                            ->searchable()
                            ->options([
                                'ACTIVO-AFILIADO'                       => 'ACTIVO-AFILIADO',
                                'ACTIVO-EN-PROCESO'                     => 'ACTIVO-EN-PROCESO',
                                'AFILIADO'                              => 'AFILIADO',
                                'CONVENIO SUSPENDIDO POR EL PROVEEDOR'  => 'CONVENIO SUSPENDIDO POR EL PROVEEDOR',
                                'CONVENIO SUSPENDIDO POR TDEC'          => 'CONVENIO SUSPENDIDO POR TDEC',
                                'EN PROCESO'                            => 'EN PROCESO',
                                'SIN RESPUESTA DE AFILIACION'           => 'SIN RESPUESTA DE AFILIACION',
                            ]),
                        TextInput::make('rif')
                            ->label('RIF')
                            ->mask('J999999999999'),
                        TextInput::make('razon_social')
                            ->label('Razón Social')
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('razon_social', $state.toUpperCase());
                            JS),
                        TextInput::make('personal_phone')
                            ->label('Teléfono Celular')
                            ->helperText('Formato de teléfono: 04122346790, sin espacios( ), sin guiones(-).')
                            ->mask('99999999999') // Opcional: mejora la UX en el navegador
                            ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                            ->rules([
                                'required', // Si el campo es obligatorio
                                'regex:/^\d{11}$/',
                            ])
                            ->validationMessages([
                                'regex' => 'El número de teléfono debe contener exactamente 11 dígitos y no debe incluir espacios ni guiones.',
                                'length' => 'El número de teléfono debe tener 11 dígitos.',
                            ])
                            
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('local_phone')
                            ->label('Teléfono Local')
                            ->helperText('Formato de teléfono: 04122346790, sin espacios( ), sin guiones(-).')
                            ->mask('99999999999') // Opcional: mejora la UX en el navegador
                            ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                            ->rules([
                                'required', // Si el campo es obligatorio
                                'regex:/^\d{11}$/',
                            ])
                            ->validationMessages([
                                'regex' => 'El número de teléfono debe contener exactamente 11 dígitos y no debe incluir espacios ni guiones.',
                                'length' => 'El número de teléfono debe tener 11 dígitos.',
                            ])
                            
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('correo_principal')
                            ->label('Correo Electrónico')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('afiliacion_proveedor')
                            ->label('Afiliación Proveedor')
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('afiliacion_proveedor', $state.toUpperCase()); 
                            JS),
                        // TextInput::make('promedio_costo_proveedor')
                        //     ->label('Promedio Costo Proveedor')
                        //     ->afterStateUpdatedJs(<<<'JS'
                        //         $set('promedio_costo_proveedor', $state.toUpperCase());
                        //     JS),
                        TextInput::make('ubicacion_principal')
                            ->label('Ubicación Principal')
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('ubicacion_principal', $state.toUpperCase());
                            JS),
                        TextInput::make('convenio_pago')
                            ->label('Convenio de Pago')
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('convenio_pago', $state.toUpperCase());    
                            JS),
                        Select::make('tiempo_credito')
                            ->label('Tiempo de Crédito')
                            ->searchable()
                            ->options([
                                '3 DIAS'            => '3 DIAS',
                                '5 DIAS'            => '5 DIAS',
                                '7 DIAS'            => '7 DIAS',
                                '10 DIAS'           => '10 DIAS',
                                '15 DIAS'           => '15 DIAS',
                                '20 DIAS'           => '20 DIAS',
                                '25 DIAS'           => '25 DIAS',
                                '30 DIAS'           => '30 DIAS',
                                'CONTADO'           => 'CONTADO',
                                'FONDO ANTICIPADO'  => 'FONDO ANTICIPADO',
                                'PREPAGO'           => 'PREPAGO',
                            ]),
                        TextInput::make('horario')
                            ->label('Horario')
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('horario', $state.toUpperCase());    
                            JS),
                        Textarea::make('otros_servicios')
                            ->columnSpanFull()
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('otros_servicios', $state.toUpperCase());    
                            JS),
                        Hidden::make('created_by')->default(Auth::user()->name),
                        Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
                    ])->columnSpanFull()->columns(4),

                Section::make('Caracteristicas General')
                    ->description('Información de la entidad.')
                    ->collapsed()
                    ->schema([
                        Toggle::make('urgen_care')
                            ->onIcon('heroicon-s-hand-thumb-up')
                            ->onColor('success'),
                        Toggle::make('consulta_aps')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('amd')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('laboratorio_centro')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('laboratorio_domicilio')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('rx_centro')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('rx_domicilio')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('eco_abdominal_centro')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('eco_abdominal_domicilio')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('electrocardiograma_centro')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('electrocardiograma_domicilio')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('mamografia')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('tomografo')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('resonancia')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('encologogia')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('equipos_especiales_oftalmologia')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('radioterapia_intraoperatoria')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('quirofanos')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('uci_uten')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('neonatal')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('ambulancias')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('odontologia')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('oftalmologia')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('densitometria_osea')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('dialisis')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('otras_unidades_especiales')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                    ])->columnSpanFull()->columns(4),

                Section::make('Contactos')
                    ->description('Información de contactos principales de la entidad.')
                    ->collapsed()
                    ->schema([
                        Repeater::make('supplierContactPrincipals')
                            ->label('Tabla dinamica de Contactos Principales')
                            ->relationship()
                            ->table([
                                TableColumn::make('Departamento'),
                                TableColumn::make('Cargo'),
                                TableColumn::make('Nombre y Apellido'),
                                TableColumn::make('Correo Electrónico'),
                                TableColumn::make('Teléfono Celular'),
                                TableColumn::make('Teléfono Local'),
                            ])
                            ->schema([
                                TextInput::make('departament')
                                    ->afterStateUpdatedJs(<<<'JS'
                                            $set('departament', $state.toUpperCase());    
                                        JS),
                                TextInput::make('position')
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('position', $state.toUpperCase());    
                                    JS),
                                TextInput::make('name')
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('name', $state.toUpperCase());    
                                    JS)
                                    ->required(),
                                TextInput::make('email')
                                    ->email(),
                                TextInput::make('personal_phone')
                                    ->label('Teléfono Celular')
                                    ->tel()
                                    ->numeric() // Sugerido: obliga al navegador a usar el teclado numérico en móviles
                                    ->mask('99999999999') // Opcional: mejora la UX en el navegador
                                    ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                                    ->rules([
                                        'required', // Si el campo es obligatorio

                                        // REGEX: ^\d{11}$
                                        // ^        -> Inicio de la cadena
                                        // \d{11}   -> Exactamente 11 dígitos (0-9)
                                        // $        -> Fin de la cadena
                                        'regex:/^\d{11}$/',
                                    ])
                                    ->validationMessages([
                                        'regex' => 'El número de teléfono debe contener exactamente 11 dígitos y no debe incluir espacios ni guiones.',
                                        'length' => 'El número de teléfono debe tener 11 dígitos.',
                                    ]),
                                TextInput::make('local_phone')
                                    ->label('Teléfono Local')
                                    ->tel()
                                    ->numeric() // Sugerido: obliga al navegador a usar el teclado numérico en móviles
                                    ->mask('99999999999') // Opcional: mejora la UX en el navegador
                                    ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                                    ->rules([
                                        'required', // Si el campo es obligatorio

                                        // REGEX: ^\d{11}$
                                        // ^        -> Inicio de la cadena
                                        // \d{11}   -> Exactamente 11 dígitos (0-9)
                                        // $        -> Fin de la cadena
                                        'regex:/^\d{11}$/',
                                    ])
                                    ->validationMessages([
                                        'regex' => 'El número de teléfono debe contener exactamente 11 dígitos y no debe incluir espacios ni guiones.',
                                        'length' => 'El número de teléfono debe tener 11 dígitos.',
                                    ]),
                                Hidden::make('created_by')->default(Auth::user()->name),
                                Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
                            ])
                            ->addActionLabel('Añadir Contacto')
                            ->columnSpanFull()
                            ->reorderable()
                    ])->columnSpanFull(),

                Section::make('Sucursales')
                    ->description('Información de sucursales acosiadas al proveedor.')
                    ->collapsed()
                    ->schema([
                        Repeater::make('supplierRedGlobals')
                            ->label('Tabla dinámica de Información de Sucursales')
                            ->relationship()
                            ->table([
                                TableColumn::make('Estado'),
                                TableColumn::make('Ciudad'),
                                TableColumn::make('Nombre y Apellido'),
                                TableColumn::make('Correo Electrónico'),
                                TableColumn::make('Teléfono Celular'),
                                TableColumn::make('Teléfono Local'),
                                TableColumn::make('Direccion de Ubicacion'),
                            ])
                            ->schema([
                                Select::make('state_id')
                                    ->options(State::all()->pluck('definition', 'id'))
                                    ->label('Estado')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('city_id')
                                    ->options(fn(Get $get) => City::where('state_id', $get('state_id'))->pluck('definition', 'id'))
                                    ->label('Ciudad')
                                    ->live()
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('name')
                                    ->label('Nombre o Razón Social')
                                    ->required()
                                    ->maxLength(255)
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('name', $state.toUpperCase());    
                                    JS),
                                TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->required()
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('personal_phone')
                                    ->label('Teléfono Celular')
                                    ->required()
                                    ->tel()
                                    ->numeric() // Sugerido: obliga al navegador a usar el teclado numérico en móviles
                                    ->mask('99999999999') // Opcional: mejora la UX en el navegador
                                    ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                                    ->rules([
                                        'required', // Si el campo es obligatorio

                                        // REGEX: ^\d{11}$
                                        // ^        -> Inicio de la cadena
                                        // \d{11}   -> Exactamente 11 dígitos (0-9)
                                        // $        -> Fin de la cadena
                                        'regex:/^\d{11}$/',
                                    ])
                                    ->validationMessages([
                                        'regex' => 'El número de teléfono debe contener exactamente 11 dígitos y no debe incluir espacios ni guiones.',
                                        'length' => 'El número de teléfono debe tener 11 dígitos.',
                                    ]),
                                TextInput::make('local_phone')
                                    ->label('Teléfono Local')
                                    ->required()
                                    ->tel()
                                    ->numeric() // Sugerido: obliga al navegador a usar el teclado numérico en móviles
                                    ->mask('99999999999') // Opcional: mejora la UX en el navegador
                                    ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                                    ->rules([
                                        'required', // Si el campo es obligatorio

                                        // REGEX: ^\d{11}$
                                        // ^        -> Inicio de la cadena
                                        // \d{11}   -> Exactamente 11 dígitos (0-9)
                                        // $        -> Fin de la cadena
                                        'regex:/^\d{11}$/',
                                    ])
                                    ->validationMessages([
                                        'regex' => 'El número de teléfono debe contener exactamente 11 dígitos y no debe incluir espacios ni guiones.',
                                        'length' => 'El número de teléfono debe tener 11 dígitos.',
                                    ]),
                                TextInput::make('address')
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('address', $state.toUpperCase());    
                                    JS),
                                Hidden::make('created_by')->default(Auth::user()->name),
                                Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
                            ])
                            ->addActionLabel('Añadir Sucursal')
                            ->columnSpanFull()
                            ->reorderable()
                    ])->columnSpanFull(),

                Section::make('Notas y/o Observaciones')
                    ->description('Detalles adicionales del proveedor.')
                    ->collapsed()
                    ->schema([
                        Repeater::make('supplierObservacions')
                            ->label('Bitacora de Notas y/o Observaciones')
                            ->relationship()
                            ->table([
                                TableColumn::make('Notas y/o Observacion'),
                                TableColumn::make('Responsable de la Nota'),
                            ])
                            ->schema([
                                Textarea::make('observation')
                                    ->autosize()
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('observation', $state.toUpperCase());    
                                    JS),
                    TextInput::make('created_by')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(Auth::user()->name),
                            ])
                            ->addActionLabel('Añadir Observación o Nota')
                            ->columnSpanFull()
                            ->reorderable()
                    ])->columnSpanFull()
            ]);
    }
}