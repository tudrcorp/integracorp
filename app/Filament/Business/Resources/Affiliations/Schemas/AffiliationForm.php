<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\Affiliation;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\BusinessLine;
use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\Country;
use App\Models\Coverage;
use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\State;
use App\Support\AffiliationAffiliateBusinessContextSynchronizer;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AffiliationForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function configure(Schema $schema): Schema
    {
        $data_records = session()->get('data_records');

        return $schema
            ->components([
                Tabs::make('affiliationFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información principal')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Fieldset::make('Plan/Cobertura/Frecuencia de Pago/Monto a Pagar')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                            // Esta seccion solo puede ser vista por el administrador general del mudilo de negocios
                                    ->hidden(fn ($operation) => Auth::user()->is_business_admin != 1 && $operation === 'edit')
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Código de afiliación')
                                            ->prefixIcon('heroicon-m-clipboard-document-check')
                                            ->disabled()
                                            ->dehydrated()
                                            ->maxLength(255)
                                            ->default(function () {
                                                if (Affiliation::max('id') == null) {
                                                    $parte_entera = 0;
                                                } else {
                                                    $parte_entera = Affiliation::max('id');
                                                }

                                                return 'TDEC-IND-000'.$parte_entera + 1;
                                            })
                                            ->required(),
                                        Select::make('individual_quote_id')
                                            ->label('Nombre del cliente')
                                            ->prefixIcon('heroicon-m-clipboard-document-check')
                                            ->options(IndividualQuote::all()->pluck('full_name', 'id'))
                                            ->default($data_records[0]['individual_quote_id'] ?? null)
                                            ->searchable()
                                            ->disabled()
                                            ->dehydrated()
                                            ->preload(),
                                        Select::make('plan_id')
                                            ->options(Plan::all()->pluck('description', 'id'))
                                            ->default($data_records[0]['plan_id'] ?? null)
                                            ->label('Plan')
                                            ->live()
                                            ->searchable()
                                            ->preload()
                                            ->disabled()
                                            ->dehydrated()
                                            ->prefixIcon('heroicon-m-clipboard-document-check')
                                            ->required(),
                                        Select::make('coverage_id')
                                            ->helperText('Punto(.) para separar miles.')
                                            ->label('Cobertura')
                                            ->live()
                                            ->options(Coverage::all()->pluck('price', 'id'))
                                            ->default($data_records[0]['coverage_id'] ?? null)
                                            ->searchable()
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->hidden(fn (Get $get) => $get('plan_id') == 1 || $get('plan_id') == null)
                                            ->preload(),
                                        Select::make('payment_frequency')
                                            ->label('Frecuencia de pago')
                                            ->live()
                                            ->options([
                                                'ANUAL' => 'ANUAL',
                                                'SEMESTRAL' => 'SEMESTRAL',
                                                'TRIMESTRAL' => 'TRIMESTRAL',
                                            ])
                                            ->searchable()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->preload()
                                            ->afterStateUpdated(function ($state, $set, Get $get) use ($data_records) {

                                                $set('fee_anual', $data_records[0]['subtotal_anual']);

                                                if ($get('payment_frequency') == 'ANUAL') {
                                                    $set('total_amount', $data_records[0]['subtotal_anual']);
                                                }
                                                if ($get('payment_frequency') == 'TRIMESTRAL') {
                                                    $set('total_amount', $data_records[0]['subtotal_quarterly']);
                                                }
                                                if ($get('payment_frequency') == 'SEMESTRAL') {
                                                    $set('total_amount', $data_records[0]['subtotal_biannual']);
                                                }

                                            }),
                                        TextInput::make('fee_anual')
                                            ->label('Tarifa anual')
                                            ->prefix('US$')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->live(),
                                        TextInput::make('total_amount')
                                            ->label('Total a pagar')
                                            ->helperText('Punto(.) para separar decimales')
                                            ->prefix('US$')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->live(),
                                        Hidden::make('created_by')->default(Auth::user()->name),
                                        Hidden::make('status')->default('PRE-APROBADA'),
                                    ])->columnSpanFull()->columns(3),

                                Fieldset::make('Asociar Agencia y/o Agente')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    // Esta seccion solo puede ser vista por el administrador general del mudilo de negocios
                                    ->hidden(fn ($operation) => $operation === 'edit')
                                    ->schema([
                                        Select::make('code_agency')
                                            ->label('Lista de Agencias')
                                            ->options(function (Get $get) {
                                                return Agency::all()->pluck('name_corporative', 'code');
                                            })
                                            ->live()
                                            ->searchable()
                                            ->prefixIcon('heroicon-c-building-library')
                                            ->preload(),
                                        Select::make('agent_id')
                                            ->label('Agentes')
                                            ->options(function (Get $get) {
                                                if ($get('code_agency') == null) {
                                                    return Agent::where('owner_code', 'TDG-100')->pluck('name', 'id');
                                                }

                                                return Agent::where('owner_code', $get('code_agency'))->pluck('name', 'id');
                                            })
                                            ->live()
                                            ->searchable()
                                            ->prefixIcon('fontisto-person')
                                            ->preload(),
                                    ])->columnSpanFull(),

                                Fieldset::make('Información adicional de la Afiliación')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Select::make('business_unit_id')
                                            ->label('Unidad de Negocio')
                                            ->options(function (Get $get) {
                                                return BusinessUnit::all()->pluck('definition', 'id');
                                            })
                                            ->live()
                                            ->searchable()
                                            ->prefixIcon('heroicon-c-building-library')
                                            ->preload(),
                                        Select::make('business_line_id')
                                            ->label('Lineas de Servicio')
                                            ->options(function (Get $get) {
                                                if ($get('business_unit_id') == null) {
                                                    return [];
                                                }

                                                return BusinessLine::where('business_unit_id', $get('business_unit_id'))->pluck('definition', 'id'); // Agent::where('owner_code', $get('code_agency'))->pluck('name', 'id');
                                            })
                                            ->live()
                                            ->searchable()
                                            ->prefixIcon('fontisto-person')
                                            ->preload(),
                                        Select::make('service_providers')
                                            ->label('Provvedor(es) de Servicios')
                                            ->multiple()
                                            ->options(ServiceProvider::all()->pluck('name', 'name'))
                                            ->searchable()
                                            ->prefixIcon('fontisto-person')
                                            ->preload(),
                                        Actions::make([
                                            Action::make('syncAffiliateBusinessContext')
                                                ->label('Sincronizar con afiliados')
                                                ->icon('heroicon-o-arrow-path')
                                                ->color('info')
                                                ->extraAttributes([
                                                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                                                ])
                                                ->requiresConfirmation()
                                                ->modalHeading('Sincronizar unidad y línea de servicio')
                                                ->modalDescription('Se actualizarán la unidad de negocio y la línea de servicio en todos los afiliados individuales vinculados a esta afiliación, usando los valores seleccionados arriba.')
                                                ->modalSubmitActionLabel('Sincronizar')
                                                ->modalSubmitAction(
                                                    fn (Action $action) => $action
                                                        ->color('info')
                                                        ->extraAttributes([
                                                            'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                                                        ])
                                                )
                                                ->modalCancelAction(
                                                    fn (Action $action) => $action
                                                        ->color('gray')
                                                        ->extraAttributes([
                                                            'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                                                        ])
                                                )
                                                ->visible(fn (string $operation): bool => $operation === 'edit')
                                                ->action(function (Get $get, Component $livewire): void {
                                                    if (! method_exists($livewire, 'getRecord')) {
                                                        return;
                                                    }

                                                    $affiliation = $livewire->getRecord();

                                                    if (! $affiliation instanceof Affiliation) {
                                                        return;
                                                    }

                                                    try {
                                                        $updatedCount = app(AffiliationAffiliateBusinessContextSynchronizer::class)->sync(
                                                            $affiliation,
                                                            $get('business_unit_id'),
                                                            $get('business_line_id'),
                                                        );
                                                    } catch (\InvalidArgumentException $exception) {
                                                        Notification::make()
                                                            ->danger()
                                                            ->title('No se pudo sincronizar')
                                                            ->body($exception->getMessage())
                                                            ->send();

                                                        return;
                                                    }

                                                    Notification::make()
                                                        ->success()
                                                        ->title('Afiliados sincronizados')
                                                        ->body($updatedCount === 0
                                                            ? 'No hay afiliados vinculados a esta afiliación.'
                                                            : "Se actualizaron {$updatedCount} afiliado(s) con la unidad de negocio y línea de servicio seleccionadas.")
                                                        ->send();
                                                }),
                                        ])
                                            ->alignment(Alignment::Start)
                                            ->columnSpanFull(),
                                    ])->columnSpanFull()->columns(3),

                                Fieldset::make('Aliado de Servicio NIVEL 1')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Select::make('aliado_1_name')
                                            ->label('Nombre')
                                            ->live()
                                            ->options([
                                                'ATEMMEDI' => 'ATEMMEDI',
                                            ])
                                            ->searchable()
                                            ->prefixIcon('heroicon-c-building-library'),
                                        DatePicker::make('date_init_aliado_1')
                                            ->label('Fecha de Inicio')
                                            ->format('d/m/Y')
                                            ->prefixIcon('heroicon-c-building-library'),
                                        DatePicker::make('date_end_aliado_1')
                                            ->label('Fecha de Finalización')
                                            ->format('d/m/Y')
                                            ->prefixIcon('heroicon-c-building-library'),
                                        Grid::make(3)
                                            ->schema([
                                                FileUpload::make('vaucher_aliado_1')
                                                    ->label('Vaucher Aliado Nivel 1')
                                                    ->directory('vauchers-aliados')
                                                    ->helperText('Se recomienda que al momento de actualiar la informacion del afiliado se adjunte el vaucher de aceptacion.'),
                                            ])->columnSpanFull(),

                                    ])->columnSpanFull()->columns(3),
                            ]),
                        Tab::make('Titular')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(3)
                                    ->extraAttributes(['class' => self::INNER_CARD])
                                    ->schema([
                                        TextInput::make('full_name_ti')
                                            ->label('Nombre y Apellido')
                                            ->afterStateUpdatedJs(<<<'JS'
                                        $set('full_name_ti', $state.toUpperCase());
                                    JS)
                                            ->live(onBlur: true)
                                            ->prefixIcon('heroicon-s-identification')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo requerido',
                                            ])
                                            ->maxLength(255),
                                        TextInput::make('nro_identificacion_ti')
                                            ->label('Nro. de Identificación')
                                            ->prefixIcon('heroicon-s-identification')
                                            ->unique(
                                                ignoreRecord: true,
                                                table: 'affiliations',
                                                column: 'nro_identificacion_ti',
                                            )
                                            ->mask('999999999')
                                            ->rules([
                                                'regex:/^[0-9]+$/', // Acepta de 1 a 6 dígitos
                                            ])
                                            ->validationMessages([
                                                'numeric' => 'El campo es numerico',
                                            ])
                                            ->required(),

                                        Select::make('sex_ti')
                                            ->label('Sexo')
                                            ->live()
                                            ->options([
                                                'MASCULINO' => 'MASCULINO',
                                                'FEMENINO' => 'FEMENINO',
                                            ])
                                            ->searchable()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->preload(),

                                        DatePicker::make('birth_date_ti')
                                            ->label('Fecha de Nacimiento')
                                            ->prefixIcon('heroicon-m-calendar-days')
                                            ->displayFormat('d/m/Y')
                                            ->format('d-m-Y')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ]),
                                        TextInput::make('email_ti')
                                            ->label('Correo Electrónico')
                                            ->email()
                                            ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                            ->validationMessages([
                                                'required' => 'Campo requerido',
                                                'email' => 'El correo no es valido',
                                                'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                            ]),
                                        TextInput::make('adress_ti')
                                            ->label('Dirección')
                                            ->afterStateUpdatedJs(<<<'JS'
                                        $set('adress_ti', $state.toUpperCase());
                                    JS)
                                            ->live(onBlur: true)
                                            ->prefixIcon('heroicon-s-identification')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->maxLength(255),
                                        Select::make('country_code_ti')
                                            ->label('Código de país')
                                            ->options(fn () => UtilsController::getCountries())
                                            ->hiddenOn('edit')
                                            ->default('+58')
                                            ->live(onBlur: true),
                                        TextInput::make('phone_ti')
                                            ->prefixIcon('heroicon-s-phone')
                                            ->tel()
                                            ->label('Número de teléfono')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $countryCode = $get('country_code_ti');
                                                if ($countryCode) {
                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                    $set('phone_ti', $countryCode.$cleanNumber);
                                                }
                                            }),
                                        Select::make('country_id_ti')
                                            ->label('País')
                                            ->live()
                                            ->options(Country::all()->pluck('name', 'id'))
                                            ->searchable()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->default(189)
                                            ->preload(),
                                        Select::make('state_id_ti')
                                            ->label('Estado')
                                            ->options(function (Get $get) {
                                                return State::where('country_id', $get('country_id_ti'))->pluck('definition', 'id');
                                            })
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $region_id = State::where('id', $state)->value('region_id');
                                                $region = Region::where('id', $region_id)->value('definition');
                                                $set('region_ti', $region);
                                            })
                                            ->live()
                                            ->searchable()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->preload(),
                                        TextInput::make('region_ti')
                                            ->label('Región')
                                            ->prefixIcon('heroicon-m-map')
                                            ->disabled()
                                            ->dehydrated()
                                            ->maxLength(255),
                                        Select::make('city_id_ti')
                                            ->label('Ciudad')
                                            ->options(function (Get $get) {
                                                return City::where('country_id', $get('country_id_ti'))->where('state_id', $get('state_id_ti'))->pluck('definition', 'id');
                                            })
                                            ->searchable()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->preload(),
                                        FileUpload::make('document')
                                            ->label('Documento del titular')
                                            ->uploadingMessage('Cargando documento...'),

                                        Grid::make(1)
                                            ->schema([
                                                Radio::make('feedback')
                                                    ->label('¿Desea incluir beneficiarios adicionales?')
                                                    ->default(true)
                                                    ->live()
                                                    ->boolean()
                                                    ->inline()
                                                    ->inlineLabel(false),
                                            ])->columnSpanFull()->hiddenOn('edit'),
                                    ]),
                            ]),
                        Tab::make('Afiliados')
                            ->icon('heroicon-o-users')
                            ->hidden(fn (Get $get, string $operation) => ! $get('feedback') || $operation === 'edit')
                            ->schema([
                                Repeater::make('affiliates')
                                    ->label('Información de afiliados')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Fieldset::make('Información personal del afiliado')
                                                    ->schema([
                                                        TextInput::make('full_name')
                                                            ->label('Nombre y Apellido')
                                                            ->afterStateUpdatedJs(<<<'JS'
                                                            $set('adress_ti', $state.toUpperCase());
                                                        JS)
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Campo Requerido',
                                                            ])
                                                            ->live(onBlur: true)
                                                            ->maxLength(255),
                                                        TextInput::make('nro_identificacion')
                                                            ->label('Número de Identificación')
                                                            ->numeric()
                                                            ->unique(
                                                                ignoreRecord: true,
                                                                table: 'affiliates',
                                                                column: 'nro_identificacion',
                                                            )
                                                            ->mask('999999999')
                                                            ->rules([
                                                                'regex:/^[0-9]+$/', // Acepta de 1 a 6 dígitos
                                                            ])
                                                            ->required()
                                                            ->validationMessages([
                                                                'numeric' => 'El campo es numerico',
                                                                'required' => 'Campo Requerido',
                                                            ]),
                                                        Select::make('sex')
                                                            ->label('Sexo')
                                                            ->options([
                                                                'MASCULINO' => 'MASCULINO',
                                                                'FEMENINO' => 'FEMENINO',
                                                            ])
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Campo Requerido',
                                                            ]),
                                                        DatePicker::make('birth_date')
                                                            ->label('Fecha de Nacimiento')
                                                            ->displayFormat('d-m-Y')
                                                            ->format('d-m-Y')
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Campo Requerido',
                                                            ]),
                                                        Select::make('relationship')
                                                            ->label('Parentesco')
                                                            ->options([
                                                                'AMIGO' => 'AMIGO',
                                                                'MADRE' => 'MADRE',
                                                                'PADRE' => 'PADRE',
                                                                'CONYUGE' => 'CONYUGE',
                                                                'HIJO' => 'HIJO',
                                                                'HIJA' => 'HIJA',
                                                                'OTRO' => 'OTRO',
                                                            ])
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Campo Requerido',
                                                            ]),
                                                    ])->columnSpanFull(1)->columns(5),
                                                Fieldset::make('Documento de identidad')
                                                    ->schema([
                                                        FileUpload::make('document')
                                                            ->label('Documento')
                                                            ->uploadingMessage('Cargando documento...')
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Campo Requerido',
                                                            ]),

                                                    ])->columnSpanFull(1),
                                            ])->columnSpanFull()->columns(2),
                                    ])
                                    ->columnSpanFull()
                                    ->defaultItems(function (Get $get, Set $set) use ($data_records) {
                                        // Se reste 1 por el titular, ejempo: La cotización es para 2 personas, el titular y 1 afiliado;
                                        return $data_records[0]['total_persons'] == 1 ? 1 : $data_records[0]['total_persons'] - 1;
                                    })
                                    ->addActionLabel('Agregar afiliado'),
                            ]),
                        Tab::make('Declaración de Condiciones Médicas')
                            ->icon('heroicon-o-heart')
                            ->hidden(fn (Get $get) => $get('plan_id') != 3)
                            ->schema([
                                Fieldset::make('Cuestionario de salud')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Radio::make('cuestion_1')
                                            ->label('¿Usted y el grupo de beneficiarios solicitantes, gozan de buena salud?')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_2')
                                            ->label('¿Usted o el grupo de beneficiarios presentan alguna condición médica o congénita?')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_3')
                                            ->label('¿Usted o el grupo de beneficiario ha sido intervenido quirúrgicamente? ')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_4')
                                            ->label('¿Usted o el grupo de beneficiario padece o ha padecido alguna enfermedad?')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_5')
                                            ->label('Enfermedades Cardiovasculares, tales como; Hipertensión Arterial, Ataque cardíaco, Angina o dolor de pecho,
                                                    Soplo Cardíaco, Insuficiencia Cardíaca Congestiva o desórdenes del corazón o sistema circulatorio.')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_6')
                                            ->label('Enfermedades Cerebrovasculares, tales como: Desmayos, confusión, parálisis de miembros, dificultad para
                                                    hablar, articular y entender, Accidente Cerebro-vascular (ACV). Cefalea o migraña. Epilepsia o Convulsiones.
                                                    Otros trastornos o enfermedad del Cerebro o Sistema Nervioso.')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_7')
                                            ->label('Enfermedades Respiratorias, tales como: Asma Bronquial, Bronquitis, Bronquiolitis, Enfisema, Neumonía, Enfer-
                                                    medad pulmonar Obstructiva Crónica (EPOC) u otras enfermedades del Sistema Respiratorio.')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_8')
                                            ->label('Enfermedades o Trastornos Endocrinos tales como: Diabetes Mellitus, Bocio, hipertiroidismo, hipotiroidismo,
                                                Tiroiditis, Resistencia a la insulina, enfermedad de Cushing, cáncer de tiroides.')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_9')
                                            ->label('Enfermedades Gastrointestinales como: Litiasis vesicular, Cólico Biliar, Úlcera gástrica, gastritis, Hemorragia
                                                digestivas, colitis, hemorroides, Apendicitis, Peritonitis, Pancreatitis u otros desórdenes del estómago, intestino,
                                                hígado o vesícula biliar.')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_10')
                                            ->label('Enfermedades Renales: Litiasis renal, Cólico nefrítico, Sangre en la orina o Hematuria, Cistitis, Infecciones
                                                urinarias, Pielonefritis, Insuficiencia renal aguda. Otras enfermedades del riñón, vejiga o próstata.')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_11')
                                            ->label('Enfermedades Osteoarticulares, Artrosis, Artritis reumatoide, Traumatismo craneoencefálico, Fracturas óseas,
                                                Luxaciones o esguinces, tumores óseos, u otros trastornos de los músculos, articulaciones o columna vertical o
                                                espalda.')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_12')
                                            ->label('¿Ha sufrido o padece de alguna enfermedad de la Piel como: Dermatitis, Celulitis, Abscesos cutáneos, quistes,
                                                tumores o cáncer? ,Quemaduras o Heridas Complicadas.')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_13')
                                            ->label('¿Padece de alguna enfermedad o desorden de los ojos, oídos, nariz o garganta?')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_14')
                                            ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamen-
                                                tosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_15')
                                            ->label('¿Usted o alguno de los solicitantes, toma algún tipo de medicamentos por tratamiento prolongado?')
                                            ->boolean()
                                            ->inline(),
                                        Radio::make('cuestion_16')
                                            ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamen-
                                                tosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?')
                                            ->boolean()
                                            ->inline(),
                                    ])->columns(1)->columnSpanFull(),
                                Fieldset::make('Información Adicional')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Textarea::make('observations_cuestions')
                                            ->label('Observaciones adicionales')
                                            ->helperText('En caso de haber respondido afirmativamente alguna de las preguntas de la DECLARACIÓN CONDICIONES MÉDICAS, indique la pregunta que
                                                        corresponda, especifique la persona solicitante e indique detalles como: Diagnistico/Enfermedad, Fecha y Condicion actual.'),
                                    ])->columnSpanFull()->columns(1),
                            ]),
                        Tab::make('Pagador')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Radio::make('feedback_dos')
                                            ->label('¿El titular de la póliza es el responsable de pago?')
                                            ->default(true)
                                            ->live()
                                            ->boolean()
                                            ->inline()
                                            ->inlineLabel(false),
                                    ])->hiddenOn('edit'),
                                Fieldset::make('Datos principales del pagador')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        TextInput::make('full_name_payer')
                                            ->label('Nombre y Apellido')
                                            ->afterStateUpdatedJs(<<<'JS'
                                            $set('full_name_payer', $state.toUpperCase());
                                        JS)
                                            ->live(onBlur: true)
                                            ->prefixIcon('heroicon-s-identification')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo requerido',
                                            ])
                                            ->maxLength(255),
                                        TextInput::make('nro_identificacion_payer')
                                            ->label('Nro. de Identificación')
                                            ->prefixIcon('heroicon-s-identification')
                                            ->unique(
                                                ignoreRecord: true,
                                                table: 'affiliations',
                                                column: 'nro_identificacion_payer',
                                            )
                                            ->mask('999999999')
                                            ->rules([
                                                'regex:/^[0-9]+$/', // Acepta de 1 a 6 dígitos
                                            ])
                                            ->validationMessages([
                                                'numeric' => 'El campo es numerico',
                                            ])
                                            ->required(),
                                        Select::make('country_code_payer')
                                            ->label('Código de país')
                                            ->options([
                                                '+1' => '🇺🇸 +1 (Estados Unidos)',
                                                '+44' => '🇬🇧 +44 (Reino Unido)',
                                                '+49' => '🇩🇪 +49 (Alemania)',
                                                '+33' => '🇫🇷 +33 (Francia)',
                                                '+34' => '🇪🇸 +34 (España)',
                                                '+39' => '🇮🇹 +39 (Italia)',
                                                '+7' => '🇷🇺 +7 (Rusia)',
                                                '+55' => '🇧🇷 +55 (Brasil)',
                                                '+91' => '🇮🇳 +91 (India)',
                                                '+86' => '🇨🇳 +86 (China)',
                                                '+81' => '🇯🇵 +81 (Japón)',
                                                '+82' => '🇰🇷 +82 (Corea del Sur)',
                                                '+52' => '🇲🇽 +52 (México)',
                                                '+58' => '🇻🇪 +58 (Venezuela)',
                                                '+57' => '🇨🇴 +57 (Colombia)',
                                                '+54' => '🇦🇷 +54 (Argentina)',
                                                '+56' => '🇨🇱 +56 (Chile)',
                                                '+51' => '🇵🇪 +51 (Perú)',
                                                '+502' => '🇬🇹 +502 (Guatemala)',
                                                '+503' => '🇸🇻 +503 (El Salvador)',
                                                '+504' => '🇭🇳 +504 (Honduras)',
                                                '+505' => '🇳🇮 +505 (Nicaragua)',
                                                '+506' => '🇨🇷 +506 (Costa Rica)',
                                                '+507' => '🇵🇦 +507 (Panamá)',
                                                '+593' => '🇪🇨 +593 (Ecuador)',
                                                '+592' => '🇬🇾 +592 (Guyana)',
                                                '+591' => '🇧🇴 +591 (Bolivia)',
                                                '+598' => '🇺🇾 +598 (Uruguay)',
                                                '+20' => '🇪🇬 +20 (Egipto)',
                                                '+27' => '🇿🇦 +27 (Sudáfrica)',
                                                '+234' => '🇳🇬 +234 (Nigeria)',
                                                '+212' => '🇲🇦 +212 (Marruecos)',
                                                '+971' => '🇦🇪 +971 (Emiratos Árabes)',
                                                '+92' => '🇵🇰 +92 (Pakistán)',
                                                '+880' => '🇧🇩 +880 (Bangladesh)',
                                                '+62' => '🇮🇩 +62 (Indonesia)',
                                                '+63' => '🇵🇭 +63 (Filipinas)',
                                                '+66' => '🇹🇭 +66 (Tailandia)',
                                                '+60' => '🇲🇾 +60 (Malasia)',
                                                '+65' => '🇸🇬 +65 (Singapur)',
                                                '+61' => '🇦🇺 +61 (Australia)',
                                                '+64' => '🇳🇿 +64 (Nueva Zelanda)',
                                                '+90' => '🇹🇷 +90 (Turquía)',
                                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                                '+372' => '🇪🇪 +372 (Estonia)',
                                                '+371' => '🇱🇻 +371 (Letonia)',
                                                '+370' => '🇱🇹 +370 (Lituania)',
                                                '+48' => '🇵🇱 +48 (Polonia)',
                                                '+40' => '🇷🇴 +40 (Rumania)',
                                                '+46' => '🇸🇪 +46 (Suecia)',
                                                '+47' => '🇳🇴 +47 (Noruega)',
                                                '+45' => '🇩🇰 +45 (Dinamarca)',
                                                '+41' => '🇨🇭 +41 (Suiza)',
                                                '+43' => '🇦🇹 +43 (Austria)',
                                                '+31' => '🇳🇱 +31 (Países Bajos)',
                                                '+32' => '🇧🇪 +32 (Bélgica)',
                                                '+353' => '🇮🇪 +353 (Irlanda)',
                                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                                '+380' => '🇺🇦 +380 (Ucrania)',
                                                '+994' => '🇦🇿 +994 (Azerbaiyán)',
                                                '+995' => '🇬🇪 +995 (Georgia)',
                                                '+976' => '🇲🇳 +976 (Mongolia)',
                                                '+998' => '🇺🇿 +998 (Uzbekistán)',
                                                '+84' => '🇻🇳 +84 (Vietnam)',
                                                '+856' => '🇱🇦 +856 (Laos)',
                                                '+374' => '🇦🇲 +374 (Armenia)',
                                                '+965' => '🇰🇼 +965 (Kuwait)',
                                                '+966' => '🇸🇦 +966 (Arabia Saudita)',
                                                '+972' => '🇮🇱 +972 (Israel)',
                                                '+963' => '🇸🇾 +963 (Siria)',
                                                '+961' => '🇱🇧 +961 (Líbano)',
                                                '+960' => '🇲🇻 +960 (Maldivas)',
                                                '+992' => '🇹🇯 +992 (Tayikistán)',
                                            ])
                                            ->hiddenOn('edit')
                                            ->default('+58')
                                            ->live(onBlur: true),
                                        TextInput::make('phone_payer')
                                            ->prefixIcon('heroicon-s-phone')
                                            ->tel()
                                            ->label('Número de teléfono')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $countryCode = $get('country_code_payer');
                                                if ($countryCode) {
                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                    $set('phone_payer', $countryCode.$cleanNumber);
                                                }
                                            }),
                                        TextInput::make('email_payer')
                                            ->label('Correo Electrónico')
                                            ->email()
                                            ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                            ->validationMessages([
                                                'required' => 'Campo requerido',
                                                'email' => 'El correo no es valido',
                                                'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                            ]),
                                        Select::make('relationship_payer')
                                            ->label('Parentesco')
                                            ->options([
                                                'AMIGO' => 'AMIGO',
                                                'MADRE' => 'MADRE',
                                                'PADRE' => 'PADRE',
                                                'CONYUGE' => 'CONYUGE',
                                                'HIJO' => 'HIJO',
                                                'HIJA' => 'HIJA',
                                            ]),
                                    ])->columns(3)->hidden(fn (Get $get) => $get('feedback_dos')),
                            ]),
                        Tab::make('Acuerdo y condiciones')
                            ->icon('heroicon-o-document-check')
                            ->hiddenOn('edit')
                            ->schema([
                                Section::make('Lea detenidamente las siguientes condiciones!')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->description(function (Get $get) {
                                        if ($get('plan_id') == 1 || $get('plan_id') == 2) {
                                            return 'Estoy de acuerdo en aceptar la cobertura domiciliaria para patologías agudas del plan seleccionado, bajo los términos y condiciones de la emisión. De no ser así, notificare mi desacuerdo por escrito, durante los quince (15) días siguientes.';
                                        }
                                        if ($get('plan_id') == 3) {
                                            return 'Certifico que he leído todas las respuestas y declaraciones en esta solicitud y que a mi mejor entendimiento, están completas y son verdaderas.
                                    Entiendo que cualquier omisión o declaración incompleta o incorrecta puede causar que las reclamaciones sean negadas y que el plan sea modificado, rescindido
                                    o cancelado.
                                    Estoy de acuerdo en aceptar la cobertura bajo los términos y condiciones con que sea emitida.
                                    De no ser así , notificaré mi desacuerdo por escrito a la compañía durante los quince (15) días siguientes al recibir el certificado de cobertura.
                                    Como Agente, acepto completa responsabilidad por el envío de esta solicitud, todas las tarifas cobradas y por la entrega del certificado de afiliación cuando sea emitida.
                                    Desconozco la existencia de cualquier condición que no haya sido revelada en esta solicitud que pudiera afectar la protección de los afiliados.';
                                        }
                                    })
                                    ->icon('heroicon-m-folder-plus')
                                    ->schema([
                                        Checkbox::make('is_accepted')
                                            ->label('ACEPTO')
                                            ->required(),
                                    ])
                                    ->hiddenOn('edit'),
                            ]),
                    ]),
            ]);
    }
}
