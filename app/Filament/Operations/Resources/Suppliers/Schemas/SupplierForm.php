<?php

namespace App\Filament\Operations\Resources\Suppliers\Schemas;

use App\Models\City;
use App\Models\State;
use Filament\Schemas\Schema;
use App\Models\SupplierTipoClinica;
use App\Models\SupplierTipoServicio;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Models\SupplierClasificacion;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use App\Models\SupplierEstatusSistema;
use App\Models\SupplierStatusConvenio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
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
                        TextInput::make('rif')
                            ->label('RIF')
                            ->mask('J999999999999'),
                        TextInput::make('razon_social')
                            ->label('Razón Social')
                            ->afterStateUpdatedJs(<<<'JS'
                                        $set('razon_social', $state.toUpperCase());
                                    JS),
                        Select::make('status_convenio')
                            ->label('Tipo de Convenio')
                            ->required()
                            ->options(SupplierStatusConvenio::all()->pluck('description', 'description'))
                            ->preload()
                            ->searchable(),
                        Select::make('status_sistema')
                            ->label('Estatus del Convenio')
                            ->searchable()
                            ->options(SupplierEstatusSistema::all()->pluck('description', 'description')),
 
                        Select::make('clasificacion_id')
                            ->label('Clasificación del Proveedor')
                            ->searchable()
                            ->live()
                            ->options(SupplierClasificacion::orderBy('description', 'asc')->pluck('description', 'id'))
                            ->preload()
                            ->searchable(),
                        Select::make('tipo_clinica')
                            ->label('Tipo de Clinica')
                            ->searchable()
                            ->required()
                            ->options(SupplierTipoClinica::all()->pluck('description', 'description'))
                            ->preload()
                            ->searchable()
                            ->hidden(fn (Get $get) => $get('clasificacion_id') != 3),
                        Select::make('type_service')
                            ->options(fn(Get $get) => SupplierTipoServicio::where('supplier_clasificacion_id', $get('clasificacion_id'))->orderBy('description', 'asc')->pluck('description', 'description'))
                            ->label('Tipo de servicio')
                            ->searchable()
                            ->multiple()
                            ->preload()
                            ->required(),

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
                        Select::make('tipo_servicio')
                            ->label('Zona de Cobertura')
                            ->searchable()
                            ->options([
                                'A-NIVEL-NACIONAL'  => 'A-NIVEL-NACIONAL',
                                'MULTI-ESTADO'      => 'MULTI-ESTADO',
                            ]),
                        Select::make('state_services')
                            ->label('Desglose de Zona de Cobertura')
                            ->searchable()
                            ->multiple()
                            ->options(State::all()->pluck('definition', 'definition'))
                            ->preload()
                            ->searchable(),
                        TextInput::make('local_phone')
                            ->label('Teléfono Local')
                            ->helperText('Formato de teléfono: 04122346790, sin espacios( ), sin guiones(-).')
                            ->mask('99999999999') // Opcional: mejora la UX en el navegador
                            ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                            ->rules([
                                'regex:/^\d{11}$/',
                            ])
                            ->validationMessages([
                                'regex' => 'El número de teléfono debe contener exactamente 11 dígitos y no debe incluir espacios ni guiones.',
                                'length' => 'El número de teléfono debe tener 11 dígitos.',
                            ])

                            ->tel()
                            ->maxLength(255),
                        TextInput::make('personal_phone')
                            ->label('Teléfono Celular')
                            ->helperText('Formato de teléfono: 04122346790, sin espacios( ), sin guiones(-).')
                            ->mask('99999999999') // Opcional: mejora la UX en el navegador
                            ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                            ->rules([
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
                            ->label('Horario de Atención')
                            ->afterStateUpdatedJs(<<<'JS'
                                        $set('horario', $state.toUpperCase());    
                                    JS),
                        DatePicker::make('afiliacion_proveedor')
                            ->format('d/m/Y')
                            ->label('Fecha Afiliación Proveedor'),
                        Textarea::make('otros_servicios')
                            ->columnSpanFull()
                            ->afterStateUpdatedJs(<<<'JS'
                                        $set('otros_servicios', $state.toUpperCase());    
                                    JS),
                        
                        Hidden::make('created_by')->default(Auth::user()->name),
                        Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
                    ])->columnSpanFull()->columns(4),

                Section::make('Certificación de Infraestructura')
                    ->description('Facilidades Adicionales que ofrece el Proveedor')
                    ->collapsed()
                    ->schema([
        
                        Toggle::make('densitometria_osea')
                            ->label('Densitómetro')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('dialisis')
                            ->label('Equipo de Dialisis')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('electrocardiograma_centro')
                            ->label('Electrocardiógrafo')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('equipos_especiales_oftalmologia')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('mamografia')
                            ->label('Mamógrafo')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('quirofanos')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('radioterapia_intraoperatoria')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('resonancia')
                            ->label('Resonador')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('tomografo')
                            ->label('Tomógrafo')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('uci_pediatrica')
                            ->label('UCI Pediatrica(Unidad de Cuidados Intensivos)')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('uci_adulto')
                            ->label('UCI Adulto(Unidad de Cuidados Intensivos)')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('estacionamiento_propio')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('ascensor')
                        ->label('Ascensor Operativo')
                            ->onIcon('heroicon-s-hand-thumb-up')->onColor('success'),
                        Toggle::make('robotica')
                            ->label('Equipo de  Cirugía Robótica')
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
                                    ->mask('99999999999') // Opcional: mejora la UX en el navegador
                                    ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                                    ->rules([
                                        'regex:/^\d{11}$/',
                                    ])
                                    ->validationMessages([
                                        'regex' => 'El número de teléfono debe contener exactamente 11 dígitos y no debe incluir espacios ni guiones.',
                                        'length' => 'El número de teléfono debe tener 11 dígitos.',
                                    ]),
                                TextInput::make('local_phone')
                                    ->label('Teléfono Local')
                                    ->tel()
                                    ->mask('99999999999') // Opcional: mejora la UX en el navegador
                                    ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                                    ->rules([

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
                                    ->tel()
                                    ->mask('99999999999') // Opcional: mejora la UX en el navegador
                                    ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                                    ->rules([
                                        'regex:/^\d{11}$/',
                                    ])
                                    ->validationMessages([
                                        'regex' => 'El número de teléfono debe contener exactamente 11 dígitos y no debe incluir espacios ni guiones.',
                                        'length' => 'El número de teléfono debe tener 11 dígitos.',
                                    ]),
                                TextInput::make('local_phone')
                                    ->label('Teléfono Local')
                                    ->tel()
                                    ->mask('99999999999') // Opcional: mejora la UX en el navegador
                                    ->length(11) // Asegura exactamente 11 caracteres (validación Laravel)
                                    ->rules([
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

            Section::make('Zonas')
                ->description('Zonas de cobertura del proveedor.')
                ->collapsed()
                ->schema([
                    Repeater::make('SupplierZonaCoberturas')
                        ->label('Tabla dinámica para incluir las Zonas de Cobertura')
                        ->relationship()
                        ->table([
                            TableColumn::make('Clasificación del Proveedor'),
                            TableColumn::make('Tipo de Servicio'),
                            TableColumn::make('Estado'),
                            TableColumn::make('Ciudad'),
                        ])
                        ->schema([
                            Select::make('clasificacion_id')
                                ->label('Clasificación del Proveedor')
                                ->searchable()
                                ->live()
                                ->options(SupplierClasificacion::orderBy('description', 'asc')->pluck('description', 'id'))
                                ->preload()
                                ->searchable(),
                            Select::make('type_service')
                                ->options(fn(Get $get) => SupplierTipoServicio::where('supplier_clasificacion_id', $get('clasificacion_id'))->orderBy('description', 'asc')->pluck('description', 'description'))
                                ->label('Tipo de servicio')
                                ->searchable()
                                ->multiple()
                                ->preload()
                                ->required(),
                            Select::make('state_id')
                                ->options(State::all()->pluck('definition', 'id'))
                                // ->disableOptionsWhenSelectedInSiblingRepeaterItems()
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