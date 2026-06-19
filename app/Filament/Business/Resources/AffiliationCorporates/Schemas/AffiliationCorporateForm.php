<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Schemas;

use App\Filament\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\BusinessLine;
use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\CorporateQuoteData;
use App\Models\Country;
use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\State;
use App\Support\AffiliationCorporateAffiliateBusinessContextSynchronizer;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
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

class AffiliationCorporateForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('affiliationCorporateFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información principal')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Fieldset::make('Información de la afiliación')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->hidden(fn ($operation) => Auth::user()->is_business_admin != 1 && $operation === 'edit')
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Código de afiliación')
                                            ->prefixIcon('heroicon-m-clipboard-document-check')
                                            ->disabled()
                                            ->dehydrated()
                                            ->maxLength(255)
                                            ->default(function () {
                                                if (AffiliationCorporate::max('id') == null) {
                                                    $parte_entera = 0;
                                                } else {
                                                    $parte_entera = AffiliationCorporate::max('id');
                                                }

                                                return 'TDEC-COR-000'.$parte_entera + 1;
                                            })
                                            ->required(),
                                        TextInput::make('name_corporate')
                                            ->label('Nombre de Empresa')
                                            ->live()
                                            ->prefixIcon('heroicon-m-clipboard-document-check')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ]),
                                        Select::make('payment_frequency')
                                            ->label('Frecuencia de pago')
                                            ->live()
                                            ->options([
                                                'ANUAL' => 'ANUAL',
                                                'SEMESTRAL' => 'SEMESTRAL',
                                                'TRIMESTRAL' => 'TRIMESTRAL',
                                            ])
                                            ->searchable()
                                            ->live()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->preload()
                                            ->afterStateUpdated(function ($set, Get $get) {

                                                $plans_records = session()->get('data_records');
                                                $subtotal_anual = array_sum(array_column($plans_records, 'subtotal_anual'));
                                                $corporate_quote_id = $plans_records[0]['corporate_quote_id'];

                                                $total_persons = CorporateQuoteData::where('corporate_quote_id', $corporate_quote_id)->count();

                                                $poblacion = array_sum(array_column($plans_records, 'total_persons'));

                                                if ($get('payment_frequency') == 'ANUAL') {

                                                    // 4.- Verifico si el numero de personas de la poblacion es diferente al total de personas
                                                    // -------------------------------------------------------------------------------------------------------------------------------------------------
                                                    if ($total_persons != $poblacion) {

                                                        Notification::make()
                                                            ->color('info')
                                                            ->iconColor('info')
                                                            ->icon('heroicon-o-information-circle')
                                                            ->title('RECOMENDACIÓN')
                                                            ->body('Regresa al paso anterior donde seleccionaste la cobertura para afiliar y verifica que el numero de personas sea la correcta.')
                                                            ->persistent()
                                                            ->actions([
                                                                Action::make('back')
                                                                    ->button()
                                                                    ->label('Regresar')
                                                                    ->color('info')
                                                                    ->url(CorporateQuoteResource::getUrl('edit', ['record' => $corporate_quote_id])),
                                                            ])
                                                            ->send();
                                                        Notification::make()
                                                            ->color('warning')
                                                            ->iconColor('warning')
                                                            ->icon('heroicon-o-exclamation-triangle')
                                                            ->title('ATENCIÓN')
                                                            ->body('El número de personas de la población no coincide con el total de personas de la cotización.')
                                                            ->persistent()
                                                            ->send();

                                                        return;
                                                    }
                                                    // -------------------------------------------------------------------------------------------------------------------------------------------------

                                                    $set('total_amount', $subtotal_anual);
                                                }

                                                if ($get('payment_frequency') == 'TRIMESTRAL') {

                                                    // 4.- Verifico si el numero de personas de la poblacion es diferente al total de personas
                                                    // -------------------------------------------------------------------------------------------------------------------------------------------------
                                                    if ($total_persons != $poblacion) {

                                                        Notification::make()
                                                            ->color('info')
                                                            ->iconColor('info')
                                                            ->icon('heroicon-o-information-circle')
                                                            ->title('RECOMENDACIÓN')
                                                            ->body('Regresa al paso anterior donde seleccionaste la cobertura para afiliar y verifica que el numero de personas sea la correcta.')
                                                            ->persistent()
                                                            ->actions([
                                                                Action::make('back')
                                                                    ->button()
                                                                    ->label('Regresar')
                                                                    ->color('info')
                                                                    ->url(CorporateQuoteResource::getUrl('edit', ['record' => $corporate_quote_id])),
                                                            ])
                                                            ->send();
                                                        Notification::make()
                                                            ->color('warning')
                                                            ->iconColor('warning')
                                                            ->icon('heroicon-o-exclamation-triangle')
                                                            ->title('ATENCIÓN')
                                                            ->body('El número de personas de la población no coincide con el total de personas de la cotización.')
                                                            ->persistent()
                                                            ->send();

                                                        return;
                                                    }
                                                    // -------------------------------------------------------------------------------------------------------------------------------------------------

                                                    $set('total_amount', $subtotal_anual / 4);
                                                }

                                                if ($get('payment_frequency') == 'SEMESTRAL') {

                                                    // 4.- Verifico si el numero de personas de la poblacion es diferente al total de personas
                                                    // -------------------------------------------------------------------------------------------------------------------------------------------------
                                                    if ($total_persons != $poblacion) {

                                                        Notification::make()
                                                            ->color('info')
                                                            ->iconColor('info')
                                                            ->icon('heroicon-o-information-circle')
                                                            ->title('RECOMENDACIÓN')
                                                            ->body('Regresa al paso anterior donde seleccionaste la cobertura para afiliar y verifica que el numero de personas sea la correcta.')
                                                            ->persistent()
                                                            ->actions([
                                                                Action::make('back')
                                                                    ->button()
                                                                    ->label('Regresar')
                                                                    ->color('info')
                                                                    ->url(CorporateQuoteResource::getUrl('edit', ['record' => $corporate_quote_id])),
                                                            ])
                                                            ->send();
                                                        Notification::make()
                                                            ->color('warning')
                                                            ->iconColor('warning')
                                                            ->icon('heroicon-o-exclamation-triangle')
                                                            ->title('ATENCIÓN')
                                                            ->body('El número de personas de la población no coincide con el total de personas de la cotización.')
                                                            ->persistent()
                                                            ->send();

                                                        return;
                                                    }
                                                    // -------------------------------------------------------------------------------------------------------------------------------------------------

                                                    $set('total_amount', $subtotal_anual / 2);
                                                }

                                                $set('fee_anual', $subtotal_anual);
                                            }),
                                        TextInput::make('fee_anual')
                                            ->label('Tarifa anual')
                                            ->helperText('Punto(,) para separar decimales')
                                            ->prefix('US$')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->live(),
                                        TextInput::make('total_amount')
                                            ->label('Total a pagar')
                                            ->helperText('Punto(,) para separar decimales')
                                            ->prefix('US$')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->live(),
                                        Hidden::make('corporate_quote_id')->default(fn () => session()->get('data_records')[0]['corporate_quote_id']),

                                        Hidden::make('created_by')->default(Auth::user()->name),
                                        Hidden::make('status')->default('PRE-APROBADA'),
                                        // Jerarquia
                                        Hidden::make('code_agency'),
                                        Hidden::make('agent_id'),
                                        Hidden::make('owner_code'),
                                    ])->columnSpanFull(),

                                Fieldset::make('Asociar Agencia y/o Agente')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->hidden(fn ($operation) => Auth::user()->is_business_admin != 1 && $operation === 'edit')
                                    ->schema([
                                        Select::make('code_agency')
                                            ->hidden(fn ($state) => $state == 'TDG-100')
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
                                            Action::make('syncAffiliateCorporateBusinessContext')
                                                ->label('Sincronizar con afiliados')
                                                ->icon('heroicon-o-arrow-path')
                                                ->color('info')
                                                ->extraAttributes([
                                                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                                                ])
                                                ->requiresConfirmation()
                                                ->modalHeading('Sincronizar unidad y línea de servicio')
                                                ->modalDescription('Se actualizarán la unidad de negocio y la línea de servicio en todos los afiliados corporativos vinculados a esta afiliación, usando los valores seleccionados arriba.')
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

                                                    $affiliationCorporate = $livewire->getRecord();

                                                    if (! $affiliationCorporate instanceof AffiliationCorporate) {
                                                        return;
                                                    }

                                                    try {
                                                        $updatedCount = app(AffiliationCorporateAffiliateBusinessContextSynchronizer::class)->sync(
                                                            $affiliationCorporate,
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
                                                            ? 'No hay afiliados corporativos vinculados a esta afiliación.'
                                                            : "Se actualizaron {$updatedCount} afiliado(s) corporativo(s) con la unidad de negocio y línea de servicio seleccionadas.")
                                                        ->send();
                                                }),
                                        ])
                                            ->alignment(Alignment::Start)
                                            ->columnSpanFull(),
                                    ])->columnSpanFull()->columns(3),
                            ]),
                        Tab::make('Titular')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(3)
                                    ->extraAttributes(['class' => self::INNER_CARD])
                                    ->schema([
                                        TextInput::make('name_corporate')
                                            ->label('Nombre de la Empresa')
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('full_name_ti', strtoupper($state));
                                            })
                                            ->live(onBlur: true)
                                            ->prefixIcon('heroicon-s-identification')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo requerido',
                                            ])
                                            ->maxLength(255),
                                        TextInput::make('rif')
                                            ->label('Rif')
                                            ->prefixIcon('heroicon-s-identification')
                                            ->mask('999999999')
                                            ->rules([
                                                'regex:/^[0-9]+$/', // Acepta de 1 a 6 dígitos
                                            ])
                                            ->validationMessages([
                                                'numeric' => 'El campo es numerico',
                                            ])
                                            ->required(),
                                        Select::make('country_code')
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
                                        TextInput::make('phone')
                                            ->prefixIcon('heroicon-s-phone')
                                            ->tel()
                                            ->label('Número de teléfono')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $countryCode = $get('country_code');
                                                if ($countryCode) {
                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                    $set('phone', $countryCode.$cleanNumber);
                                                }
                                            }),
                                        TextInput::make('email')
                                            ->label('Correo Electrónico')
                                            ->email()
                                            ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                            ->validationMessages([
                                                'required' => 'Campo requerido',
                                                'email' => 'El correo no es valido',
                                                'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                            ]),
                                        TextInput::make('address')
                                            ->label('Dirección')
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('address', strtoupper($state));
                                            })
                                            ->live(onBlur: true)
                                            ->prefixIcon('heroicon-s-identification')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->maxLength(255),

                                        Select::make('country_id')
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
                                        Select::make('state_id')
                                            ->label('Estado')
                                            ->options(function (Get $get) {
                                                return State::where('country_id', $get('country_id'))->pluck('definition', 'id');
                                            })
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $region_id = State::where('id', $state)->value('region_id');
                                                $region = Region::where('id', $region_id)->value('definition');
                                                $set('region_id', $region);
                                            })
                                            ->live()
                                            ->searchable()
                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->preload(),
                                        TextInput::make('region_id')
                                            ->label('Región')
                                            ->prefixIcon('heroicon-m-map')
                                            ->disabled()
                                            ->dehydrated()
                                            ->maxLength(255),
                                        Select::make('city_id')
                                            ->label('Ciudad')
                                            ->options(function (Get $get) {
                                                return City::where('country_id', $get('country_id'))->where('state_id', $get('state_id'))->pluck('definition', 'id');
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
                                    ]),
                            ]),
                        Tab::make('Información de Contacto')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Fieldset::make('Datos de la persona de contacto')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        TextInput::make('full_name_contact')
                                            ->label('Nombre y Apellido')
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('full_name_contact', strtoupper($state));
                                            })
                                            ->live(onBlur: true)
                                            ->prefixIcon('heroicon-s-identification')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo requerido',
                                            ])
                                            ->maxLength(255),
                                        TextInput::make('nro_identificacion_contact')
                                            ->label('Nro. de Identificación')
                                            ->prefixIcon('heroicon-s-identification')
                                            ->mask('999999999')
                                            ->rules([
                                                'regex:/^[0-9]+$/', // Acepta de 1 a 6 dígitos
                                            ])
                                            ->validationMessages([
                                                'numeric' => 'El campo es numerico',
                                            ])
                                            ->required(),
                                        Select::make('country_code_contact')
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
                                        TextInput::make('phone_contact')
                                            ->prefixIcon('heroicon-s-phone')
                                            ->tel()
                                            ->label('Número de teléfono')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $countryCode = $get('country_code_contact');
                                                if ($countryCode) {
                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                    $set('phone_contact', $countryCode.$cleanNumber);
                                                }
                                            }),
                                        TextInput::make('email_contact')
                                            ->label('Correo Electrónico')
                                            ->email()
                                            ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                            ->validationMessages([
                                                'required' => 'Campo requerido',
                                                'email' => 'El correo no es valido',
                                                'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                            ]),
                                    ])->columns(3)->hidden(fn (Get $get) => $get('feedback_dos')),
                            ]),
                        Tab::make('Acuerdo y condiciones')
                            ->icon('heroicon-o-document-check')
                            ->hiddenOn('edit')
                            ->schema([
                                Section::make('Lea detenidamente las siguientes condiciones!')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->description(function () {
                                        return 'Certifico que he leído todas las respuestas y declaraciones en esta solicitud y que a mi mejor entendimiento, están completas y son verdaderas.
                                    Entiendo que cualquier omisión o declaración incompleta o incorrecta puede causar que las reclamaciones sean negadas y que el plan sea modificado, rescindido
                                    o cancelado.
                                    Estoy de acuerdo en aceptar la cobertura bajo los términos y condiciones con que sea emitida.
                                    De no ser así , notificaré mi desacuerdo por escrito a la compañía durante los quince (15) días siguientes al recibir el certificado de cobertura.
                                    Como Agente, acepto completa responsabilidad por el envío de esta solicitud, todas las tarifas cobradas y por la entrega del certificado de afiliación cuando sea emitida.
                                    Desconozco la existencia de cualquier condición que no haya sido revelada en esta solicitud que pudiera afectar la protección de los afiliados.';
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
