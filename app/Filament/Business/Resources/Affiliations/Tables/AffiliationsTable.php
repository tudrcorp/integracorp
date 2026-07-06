<?php

namespace App\Filament\Business\Resources\Affiliations\Tables;

use App\Filament\Resources\Affiliations\AffiliationResource;
use App\Http\Controllers\AffiliateExportCsvController;
use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\AffiliationExportCsvController;
use App\Mail\UploadPayment;
use App\Models\Affiliation;
use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Agent;
use App\Models\User;
use App\Services\AffiliationBusinessDocumentsService;
use App\Support\AffiliationPaymentBcvRateCalculator;
use App\Support\AffiliationPaymentTotalAdjustment;
use App\Support\BcvOfficialRate;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

class AffiliationsTable
{
    private const COLUMN_GROUP_HEADER_CLASS = '[&_th]:bg-gradient-to-r [&_th]:from-slate-100/95 [&_th]:via-slate-50/90 [&_th]:to-transparent dark:[&_th]:from-white/[0.08] dark:[&_th]:via-white/[0.04] dark:[&_th]:to-transparent [&_th]:font-semibold [&_th]:text-slate-800 dark:[&_th]:text-slate-100 [&_th]:border-b [&_th]:border-slate-200/80 dark:[&_th]:border-white/10';

    public static function configure(Table $table): Table
    {
        return $table
            // ->query(Affiliation::query()->where('ownerAccountManagers', Auth::user()->id))
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    return Affiliation::query()->where('ownerAccountManagers', Auth::user()->id);
                }

                return Affiliation::query();
            })
            ->defaultSort('created_at', 'desc')
            ->heading('Afiliaciones individuales')
            ->description('Listado completo: plan, titular, tomador, montos y estatus. Las filas con emisión hoy se resaltan en verde.')
            ->emptyStateHeading('Sin afiliaciones')
            ->emptyStateDescription('No hay registros o no coinciden con la búsqueda y los filtros aplicados.')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->icon(function ($record) {
                        $now = Carbon::today();
                        if ($record->status == 'ACTIVA' && $record->created_at >= $now) {
                            return 'heroicon-c-star';
                        }

                        return 'heroicon-s-user-group';
                    })
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('individual_quote.code')
                    ->label('Nº cotización')
                    ->badge()
                    ->color('verde')
                    ->icon(Heroicon::OutlinedHashtag)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('accountManager.name')
                    ->label('Account manager')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('agency.name_corporative')
                    ->label('Agencia')
                    ->badge()
                    ->color('azulOscuro')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('agent.name')
                    ->label('Agente')
                    ->badge()
                    ->color('azulOscuro')
                    ->icon(Heroicon::OutlinedUser)
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),

                ColumnGroup::make('Plan afiliado', [
                    TextColumn::make('plan.description')
                        ->label('Plan')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->searchable()
                        ->wrap(),
                    TextColumn::make('coverage.price')
                        ->label('Cobertura (US$)')
                        ->alignCenter()
                        ->money('USD')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('payment_frequency')
                        ->label('Frecuencia de pago')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('family_members')
                        ->label('Población')
                        ->alignCenter()
                        ->suffix(' pers.')
                        ->badge()
                        ->color(function (mixed $state): string {
                            if ((int) $state > 0) {
                                return 'warning';
                            }

                            return 'danger';
                        })
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('fee_anual')
                        ->label('Tarifa anual')
                        ->alignCenter()
                        ->money('USD')
                        ->color(function (mixed $state): string {
                            if ((float) $state > 0) {
                                return 'warning';
                            }

                            return 'danger';
                        })
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('total_amount')
                        ->label('Total a pagar')
                        ->alignCenter()
                        ->money('USD')
                        ->weight('semibold')
                        ->color(function (mixed $state): string {
                            if ((float) $state > 0) {
                                return 'warning';
                            }

                            return 'danger';
                        })
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('businessUnit.definition')
                        ->label('Unidad de negocio')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('businessLine.definition')
                        ->label('Línea de servicio')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('service_providers')
                        ->label('Proveedor(es)')
                        ->badge()
                        ->color('success')
                        ->formatStateUsing(function (mixed $state): ?string {
                            if (blank($state)) {
                                return null;
                            }
                            if (is_array($state)) {
                                return implode(', ', array_filter(array_map('strval', $state)));
                            }

                            return (string) $state;
                        })
                        ->searchable()
                        ->limit(32)
                        ->tooltip(function (mixed $state): ?string {
                            $text = is_array($state)
                                ? implode(', ', array_filter(array_map('strval', $state)))
                                : (string) $state;
                            if ($text === '' || strlen($text) <= 32) {
                                return null;
                            }

                            return $text;
                        })
                        ->wrap(),
                ])
                    ->extraHeaderAttributes([
                        'class' => self::COLUMN_GROUP_HEADER_CLASS,
                    ]),

                ColumnGroup::make('Información del titular', [
                    TextColumn::make('full_name_ti')
                        ->label('Nombre titular')
                        ->icon(Heroicon::OutlinedUser)
                        ->weight('medium')
                        ->searchable(),
                    TextColumn::make('nro_identificacion_ti')
                        ->label('CI titular')
                        ->badge()
                        ->color('gray')
                        ->copyable()
                        ->copyMessage('CI copiada')
                        ->searchable(),
                    TextColumn::make('sex_ti')
                        ->label('Sexo')
                        ->badge()
                        ->color('gray')
                        ->searchable(),
                    TextColumn::make('birth_date_ti')
                        ->label('Fecha de nacimiento')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->formatStateUsing(function (mixed $state): ?string {
                            if (blank($state)) {
                                return null;
                            }
                            try {
                                return Carbon::parse($state)->format('d/m/Y');
                            } catch (\Throwable) {
                                return (string) $state;
                            }
                        })
                        ->placeholder('—')
                        ->searchable(),
                    TextColumn::make('phone_ti')
                        ->label('Teléfono titular')
                        ->icon(Heroicon::OutlinedPhone)
                        ->copyable()
                        ->copyMessage('Teléfono copiado')
                        ->searchable(),
                    TextColumn::make('email_ti')
                        ->label('Correo titular')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->copyable()
                        ->copyMessage('Correo copiado')
                        ->searchable(),
                    TextColumn::make('adress_ti')
                        ->label('Dirección')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->searchable()
                        ->wrap()
                        ->lineClamp(2)
                        ->tooltip(fn (Affiliation $record): string => trim((string) $record->adress_ti))
                        ->sortable()
                        ->extraCellAttributes(fn (): array => [
                            'class' => 'min-w-52 sm:min-w-64 lg:min-w-72 max-w-[28rem] align-top',
                        ]),
                    TextColumn::make('city.definition')
                        ->label('Ciudad')
                        ->icon(Heroicon::OutlinedBuildingOffice2)
                        ->searchable(),
                    TextColumn::make('state.definition')
                        ->label('Estado')
                        ->icon(Heroicon::OutlinedMap)
                        ->searchable(),
                    TextColumn::make('region_ti')
                        ->label('Región')
                        ->searchable(),
                    TextColumn::make('country.name')
                        ->label('País')
                        ->icon(Heroicon::OutlinedGlobeAmericas)
                        ->searchable(),
                ])
                    ->extraHeaderAttributes([
                        'class' => self::COLUMN_GROUP_HEADER_CLASS,
                    ]),

                ColumnGroup::make('Información del tomador', [
                    TextColumn::make('full_name_payer')
                        ->label('Nombre y apellido')
                        ->weight('medium')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->searchable(),
                    TextColumn::make('nro_identificacion_payer')
                        ->label('Nº identificación')
                        ->badge()
                        ->color('gray')
                        ->copyable()
                        ->copyMessage('Identificación copiada')
                        ->searchable(),
                ])
                    ->extraHeaderAttributes([
                        'class' => self::COLUMN_GROUP_HEADER_CLASS,
                    ]),

                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->searchable(),

                TextInputColumn::make('activated_at')
                    ->label('Fecha de emisión')
                    ->prefixIcon(Heroicon::OutlinedCalendarDays)
                    ->searchable(),

                TextInputColumn::make('effective_date')
                    ->label('Fecha de vigencia')
                    ->prefixIcon(Heroicon::OutlinedCalendar)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'success',
                            'ACTIVA' => 'success',
                            'PENDIENTE' => 'warning',
                            'EXCLUIDO' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->searchable()
                    ->sortable()
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA' => 'heroicon-c-information-circle',
                            'ACTIVA' => 'heroicon-s-check-circle',
                            'PENDIENTE' => 'heroicon-s-exclamation-circle',
                            'EXCLUIDO' => 'heroicon-c-x-circle',
                            default => null,
                        };
                    }),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                SelectFilter::make('plan_id')
                    ->label('Plan(es) afiliado(s)')
                    ->relationship('plan', 'description')
                    ->multiple(),
                SelectFilter::make('payment_frequency')
                    ->label('Frecuencia de Pago')
                    ->options([
                        'ANUAL' => 'ANUAL',
                        'TRIMESTRAL' => 'TRIMESTRAL',
                        'SEMESTRAL' => 'SEMESTRAL',
                    ]),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon(Heroicon::OutlinedFunnel),
            )
            ->recordActions([
                ActionGroup::make([

                    Action::make('upload')
                        ->label('Comprobante de Pago')
                        ->color('azul')
                        ->icon('heroicon-s-cloud-arrow-up')
                        ->modalWidth(Width::FourExtraLarge)
                        ->form([

                            /** INFORMACION PRINCIPAL */
                            Fieldset::make('INFORMACION PRINCIPAL')
                                ->schema([
                                    Hidden::make('base_total_amount')
                                        ->default(fn (Affiliation $record): float => (float) ($record->total_amount ?? 0))
                                        ->dehydrated(),
                                    ...self::bcvRateManualStateHiddenFields(),
                                    Grid::make(['default' => 1, 'md' => 2])->schema([
                                        TextInput::make('total_amount')
                                            ->label('Total a pagar')
                                            ->helperText(function (Affiliation $record): string {
                                                if (isset($record->coverage_id)) {
                                                    return 'Plan: '.$record->plan->description.' - Cobertura: '.$record->coverage->price.' - Frecuencia: '.$record->payment_frequency;
                                                }

                                                return 'Plan: '.$record->plan->description.' - Frecuencia: '.$record->payment_frequency;
                                            })
                                            ->prefix('US$')
                                            ->default(fn (Affiliation $record): float => (float) ($record->total_amount ?? 0))
                                            ->numeric()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                                self::syncPaymentBcvRateFromTotal($get, $set, $state);
                                            }),
                                        TextInput::make('payment_adjustment_percentage')
                                            ->label('Ajuste del total (%)')
                                            ->helperText('Valores positivos aumentan y negativos disminuyen el total a pagar.')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('%')
                                            ->live(onBlur: false)
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                self::applyPaymentTotalPercentageAdjustment($get, $set);
                                            }),
                                    ])->columnSpanFull(),
                                    Placeholder::make('payment_total_preview')
                                        ->label('Cálculo del total')
                                        ->content(fn (Get $get): HtmlString => self::paymentTotalPreviewHtml($get))
                                        ->columnSpanFull(),
                                    DatePicker::make('date_payment_voucher')
                                        ->label('Fecha del Comprobante de Pago')
                                        ->required()
                                        ->format('d/m/Y')
                                        ->columnSpanFull(),
                                ])->columnSpanFull(),

                            /**FORMA DE PAGO */
                            Fieldset::make('FORMA DE PAGO')
                                ->schema([

                                    /**SELECCION DEL METODO DE PAGO */
                                    Grid::make(1)
                                        ->schema([
                                            Select::make('payment_method')
                                                ->native(false)
                                                ->label('Método de pago')
                                                ->options([
                                                    'ZELLE' => 'ZELLE',
                                                    'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                                    'EFECTIVO US$' => 'EFECTIVO US$',
                                                    'MULTIPLE' => 'MULTIPLE',
                                                    'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
                                                    'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                                                    'LINK DE PAGO' => 'LINK DE PAGO',
                                                ])
                                                ->live()
                                                ->required()
                                                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                                    self::resetBcvRateManualState($set);

                                                    if (in_array($state, ['PAGO MOVIL VES', 'TRANSFERENCIA VES', 'MULTIPLE'], true)) {
                                                        self::syncPaymentBcvRateFromTotal($get, $set, $get('total_amount'));
                                                    }
                                                })
                                                ->validationMessages([
                                                    'required' => 'Seleccione un tipo de pago',
                                                ]),
                                        ])->columnSpan(3),

                                    /* PAGO EN DOLARES ZELLE */
                                    Fieldset::make('INFORMACION DE PAGO EN ZELLE (US$)')
                                        ->schema([
                                            TextInput::make('name_ti_usd')
                                                ->label('Nombre del Titular')
                                                ->helperText('Debe colocar Nombre y Apellido')
                                                ->prefixIcon('heroicon-s-pencil')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Seleccione un tipo de pago',
                                                ]),
                                            TextInput::make('reference_payment_usd')
                                                ->label('Nro. de Referencia')
                                                ->helperText('Debe colocar el número de referencia completo')
                                                ->prefix('#')
                                                ->regex('/^[A-Za-z0-9\-]+$/')
                                                ->helperText('Solo se permiten letras, números y el guion (-)')
                                                ->required()
                                                ->validationMessages([
                                                    'regex' => 'Solo se permite el guion (-)',
                                                    'required' => 'Seleccione un tipo de pago',
                                                ]),

                                            Grid::make(1)->schema([
                                                FileUpload::make('document_usd')
                                                    ->label('Comprobante(US$)')
                                                    ->uploadingMessage('Cargando...')
                                                    ->required(),
                                            ]),
                                        ])->columnSpanFull()->hidden(function (Get $get) {
                                            if ($get('payment_method') == 'ZELLE') {
                                                return false;
                                            }

                                            return true;
                                        }),

                                    /* PAGO EN DOLARES LINK DE PAGO */
                                    Fieldset::make('INFORMACION DE PAGO EN LINK DE PAGO (US$)')
                                        ->schema([
                                            TextInput::make('name_ti_usd')
                                                ->label('Nombre del Titular')
                                                ->helperText('Debe colocar Nombre y Apellido')
                                                ->prefixIcon('heroicon-s-pencil')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Seleccione un tipo de pago',
                                                ]),
                                            TextInput::make('reference_payment_usd')
                                                ->label('Nro. de Referencia')
                                                ->helperText('Debe colocar el número de referencia completo')
                                                ->prefix('#')
                                                ->regex('/^[A-Za-z0-9\-]+$/')
                                                ->helperText('Solo se permiten letras, números y el guion (-)')
                                                ->required()
                                                ->validationMessages([
                                                    'regex' => 'Solo se permite el guion (-)',
                                                    'required' => 'Seleccione un tipo de pago',
                                                ]),

                                            Grid::make(1)->schema([
                                                FileUpload::make('document_usd')
                                                    ->label('Comprobante(US$)')
                                                    ->uploadingMessage('Cargando...')
                                                    ->required(),
                                            ]),
                                        ])->columnSpanFull()->hidden(function (Get $get) {
                                            if ($get('payment_method') == 'LINK DE PAGO') {
                                                return false;
                                            }

                                            return true;
                                        }),

                                    /** PAGO EN TRANSFERENCIA US$ */
                                    Fieldset::make('INFORMACIÓN DE PAGO EN TRANSFERENCIA (US$)')
                                        ->schema([
                                            Grid::make()->schema([
                                                TextInput::make('name_ti_usd')
                                                    ->label('Nombre del Titular')
                                                    ->helperText('Debe colocar Nombre y Apellido')
                                                    ->prefixIcon('heroicon-s-pencil')
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                    ]),

                                                Select::make('bank_usd')
                                                    ->native(false)
                                                    ->label('Banco')
                                                    ->live()
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Seleccione un banco',
                                                    ])
                                                    ->options([
                                                        'CHASE BANK' => 'CHASE BANK',
                                                        'BANK OF AMERICA' => 'BANK OF AMERICA',
                                                        'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
                                                        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                                                        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
                                                    ])
                                                    ->searchable()
                                                    ->live()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa'),

                                                Grid::make(1)->schema([
                                                    FileUpload::make('document_usd')
                                                        ->label('Comprobante(US$)')
                                                        ->uploadingMessage('Cargando...')
                                                        ->required(),
                                                ]),
                                            ])->columnSpanFull(),
                                        ])->columnSpanFull()->hidden(function (Get $get) {
                                            if ($get('payment_method') == 'TRANSFERENCIA US$') {
                                                return false;
                                            }

                                            return true;
                                        }),

                                    /** PAGO EN EFECTIVO US$ */
                                    Fieldset::make('INFORMACIÓN DE PAGO EN EFECTIVO (US$)')
                                        ->schema([
                                            Grid::make(2)->schema([
                                                Select::make('bank_usd')
                                                    ->native(false)
                                                    ->label('Banco')
                                                    ->live()
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Seleccione un banco',
                                                    ])
                                                    ->options([
                                                        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                                                        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
                                                    ])
                                                    ->searchable()
                                                    ->live()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa'),

                                                Grid::make()->schema([
                                                    FileUpload::make('document_usd')
                                                        ->label('Comprobante(US$)')
                                                        ->uploadingMessage('Cargando...')
                                                        ->required(),
                                                ])->columnSpanFull(),
                                            ])->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'EFECTIVO US$') {
                                                    return false;
                                                }

                                                return true;
                                            })->columnSpanFull(),

                                        ])->columnSpanFull()->hidden(function (Get $get) {
                                            if ($get('payment_method') == 'EFECTIVO US$') {
                                                return false;
                                            }

                                            return true;
                                        }),

                                    /* PAGO MOVIL Y TRANSFERENCIA */
                                    Fieldset::make('INFORMACIÓN DE PAGO EN MONEDA NACIONAL (VES)')
                                        ->schema([
                                            Grid::make(2)->schema([

                                                TextInput::make('pay_amount_ves')
                                                    ->inputMode('numeric')
                                                    ->live(onBlur: true)
                                                    ->label('Monto recibido en VES')
                                                    ->helperText('Ingrese el monto en bolívares recibido. La tasa BCV se calcula automáticamente (VES ÷ total US$).')
                                                    ->prefix('VES')
                                                    ->numeric()
                                                    ->required(fn (Get $get): bool => in_array($get('payment_method'), ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true))
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                        'numeric' => 'El campo es numérico',
                                                    ])
                                                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                        self::syncPaymentBcvRateFromVesAmount($get, $set, $state);
                                                    }),
                                                self::bcvRateTextInput(),
                                                Select::make('bank_ves')
                                                    ->native(false)
                                                    ->label('Banco')
                                                    ->live()
                                                    ->options([
                                                        'BANCAMIGA(VES)' => 'BANCAMIGA',
                                                        'BANCO DE VENEZUELA(VES)' => 'BANCO DE VENEZUELA',
                                                    ])
                                                    ->searchable()
                                                    ->live()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                                    ->preload(),
                                                TextInput::make('reference_payment_ves')
                                                    ->label('Referencia de pago(VES)')
                                                    ->live()
                                                    ->inputMode('numeric') // activa teclado numérico en móvil
                                                    ->helperText('Últimos 6 dígitos del comprobante de pago')
                                                    ->mask('999999')
                                                    ->maxLength(6)
                                                    ->rules([
                                                        'regex:/^\d{1,6}$/', // Acepta de 1 a 6 dígitos
                                                    ])
                                                    ->prefix('Ref:'),
                                                Grid::make(1)->schema([
                                                    FileUpload::make('document_ves')
                                                        ->label('Comprobante de pago(VES)')
                                                        ->disk('public')
                                                        ->uploadingMessage('Cargando...')
                                                        ->required(),
                                                ]),

                                            ])->columnSpanFull(),
                                        ])->columnSpanFull()->hidden(function (Get $get): bool {
                                            return ! in_array($get('payment_method'), ['TRANSFERENCIA VES', 'PAGO MOVIL VES'], true);
                                        }),

                                    /** PAGO MULTIPLE */
                                    Fieldset::make('INFORMACIÓN DE PAGO MULTIPLE EN BOLIVARES (VES) Y DOLARES (US$)')
                                        ->schema([
                                            Grid::make(2)->schema([

                                                /* PAGO EN DOLARES(USD)) */
                                                Fieldset::make('PAGO EN DOLARES (US$)')
                                                    ->schema([
                                                        /**Metodo de pago en US$ */
                                                        Select::make('payment_method_usd')
                                                            ->live()
                                                            ->native(false)
                                                            ->label('Método de pago en dólares(US$)')
                                                            ->options([
                                                                'ZELLE' => 'ZELLE',
                                                                'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                                                'EFECTIVO US$' => 'EFECTIVO US$',
                                                            ])
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Seleccione un tipo de pago',
                                                            ]),

                                                        TextInput::make('pay_amount_usd')
                                                            ->inputMode('numeric') // activa teclado numérico en móvil
                                                            ->live(onBlur: true)
                                                            ->label('Monto US$:')
                                                            ->helperText('Punto(.) para separar decimales. Ingresa el monto en dólares(US$). La tasa BCV se recalcula con el monto en bolívares.')
                                                            ->prefix('US$')
                                                            ->numeric()
                                                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                                self::syncPaymentBcvRateFromUsdPart($get, $set, $state);
                                                            }),

                                                        TextInput::make('name_ti_usd')
                                                            ->label('Nombre del Titular')
                                                            ->helperText('Debe colocar Nombre y Apellido')
                                                            ->prefixIcon('heroicon-s-pencil')
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Seleccione un tipo de pago',
                                                            ])
                                                            ->hidden(function (Get $get) {
                                                                if ($get('payment_method_usd') == 'TRANSFERENCIA US$' || $get('payment_method_usd') == 'ZELLE') {
                                                                    return false;
                                                                }

                                                                return true;
                                                            }),

                                                        /**Banco US$ */
                                                        Select::make('bank_usd')
                                                            ->native(false)
                                                            ->label('Banco Moneda Extranjera(US$)')
                                                            ->live()
                                                            ->options([
                                                                'CHASE BANK' => 'CHASE BANK',
                                                                'BANK OF AMERICA' => 'BANK OF AMERICA',
                                                                'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
                                                                'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                                                                'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
                                                            ])
                                                            ->searchable()
                                                            ->prefixIcon('heroicon-s-globe-europe-africa'),

                                                        TextInput::make('reference_payment_usd')
                                                            ->label('Nro. de Referencia')
                                                            ->helperText('Debe colocar el número de referencia completo')
                                                            ->prefix('#')
                                                            ->regex('/^[A-Za-z0-9\-]+$/')
                                                            ->helperText('Solo se permiten letras, números y el guion (-)')
                                                            ->required()
                                                            ->validationMessages([
                                                                'regex' => 'Solo se permite el guion (-)',
                                                                'required' => 'Seleccione un tipo de pago',
                                                            ])
                                                            ->hidden(function (Get $get) {
                                                                if ($get('payment_method_usd') == 'ZELLE') {
                                                                    return false;
                                                                }

                                                                return true;
                                                            }),

                                                        FileUpload::make('document_usd')
                                                            ->label('Comprobante de pago(US$)')
                                                            ->disk('public')
                                                            ->uploadingMessage('Cargando...'),

                                                    ])->columns(1),

                                                /* PAGO EN BOLIVARES (VES) */
                                                Fieldset::make('PAGO EN BOLIVARES (VES)')
                                                    ->schema([
                                                        /**Metodo de pago en VES */
                                                        Select::make('payment_method_ves')
                                                            ->native(false)
                                                            ->label('Método de pago en bolivares(VES)')
                                                            ->options([
                                                                'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
                                                                'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                                                            ])
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Seleccione un tipo de pago',
                                                            ]),

                                                        TextInput::make('pay_amount_ves')
                                                            ->inputMode('numeric')
                                                            ->live(onBlur: true)
                                                            ->label('Monto VES:')
                                                            ->helperText('Ingrese el monto en bolívares del saldo restante. La tasa BCV se calcula automáticamente (VES ÷ saldo US$).')
                                                            ->prefix('VES')
                                                            ->numeric()
                                                            ->required(fn (Get $get): bool => $get('payment_method') === 'MULTIPLE')
                                                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                                self::syncPaymentBcvRateFromVesAmount($get, $set, $state);
                                                            }),

                                                        self::bcvRateTextInput(),

                                                        /**Banco VES */
                                                        Select::make('bank_ves')
                                                            ->native(false)
                                                            ->label('Banco Moneda Nacional(VES)')
                                                            ->options([
                                                                'BANCAMIGA - VES' => 'BANCAMIGA - VES',
                                                                'BANCO DE VENEZUELA - VES' => 'BANCO DE VENEZUELA - VES',
                                                            ])
                                                            ->searchable()
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Seleccione un banco',
                                                            ])
                                                            ->prefixIcon('heroicon-s-globe-europe-africa'),

                                                        TextInput::make('reference_payment_ves')
                                                            ->label('Referencia de pago(VES)')
                                                            ->inputMode('numeric') // activa teclado numérico en móvil
                                                            ->helperText('Ultimos 6 dígitos del comprobante de pago')
                                                            ->mask('999999')
                                                            ->maxLength(6)
                                                            ->rules([
                                                                'regex:/^\d{1,6}$/', // Acepta de 1 a 6 dígitos
                                                            ])
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Campo requerido',
                                                            ])
                                                            ->prefix('Ref:'),
                                                        FileUpload::make('document_ves')
                                                            ->label('Comprobante de pago(VES)')
                                                            ->disk('public')
                                                            ->uploadingMessage('Cargando...')
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'El comprobante es requerido',
                                                            ]),
                                                    ])->columns(1),

                                            ])->columnSpanFull(),
                                        ])->columnSpanFull()->hidden(function (Get $get): bool {
                                            return $get('payment_method') !== 'MULTIPLE';
                                        }),

                                ]),

                            /**OBSERVACIONES */
                            Grid::make(1)->schema([
                                Textarea::make('observations_payment')
                                    ->label('Observaciones')
                                    ->rows(2)
                                    ->autosize()
                                    ->dehydrated(),
                            ]),
                        ])
                        ->action(function (Affiliation $record, array $data): void {

                            try {

                                $upload = AffiliationController::uploadPayment($record, $data, 'AGENTE');

                                if ($upload) {
                                    Notification::make()
                                        ->title('NOTIFICACION')
                                        ->body('El comprobante de pago se ha registrado con exito')
                                        ->icon('heroicon-m-user-plus')
                                        ->iconColor('success')
                                        ->success()
                                        ->seconds(5)
                                        ->send();

                                    // Notificacion para Admin
                                    $recipient = User::where('is_admin', 1)->get();
                                    foreach ($recipient as $user) {
                                        Notification::make()
                                            ->title('REGISTRO DE COMPROBANTE')
                                            ->body('Se ha registrado un nuevo comprobante de pago de forma exitosa. Afiliacion Nro. '.$record->code)
                                            ->icon('heroicon-m-user-plus')
                                            ->iconColor('success')
                                            ->success()
                                            ->actions([
                                                Action::make('view')
                                                    ->label('Ver detalle de pago')
                                                    ->button()
                                                    ->url(AffiliationResource::getUrl('edit', ['record' => $record->id], panel: 'admin').'?activeRelationManager=1'),
                                            ])
                                            ->sendToDatabase($user);
                                    }
                                }

                                /**
                                 * Ejecutamos el Jobs para enviar la notificacion al
                                 * correo de administracion
                                 * ----------------------------------------------------------------------------------
                                 */
                                $info = [
                                    'code' => $record->code,
                                    'email' => config('parameters.EMAIL_ADMINISTRACION'),
                                ];
                                // dd($info);
                                Mail::to($info['email'])->send(new UploadPayment($info));

                                Log::info('NEGOCIOS-AFILIACIONES: Comprobante cargado desde tabla de afiliaciones.', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'uploaded' => (bool) $upload,
                                    'payment_method' => $data['payment_method'] ?? null,
                                    'updated_by' => Auth::user()?->id,
                                ]);
                                self::audit('AUDIT_BUSINESS_AFFILIATION_PAYMENT_UPLOAD', 'business.affiliations.upload-payment', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'uploaded' => (bool) $upload,
                                    'payment_method' => $data['payment_method'] ?? null,
                                ]);
                            } catch (\Throwable $th) {
                                Log::error($th);
                                self::audit('AUDIT_BUSINESS_AFFILIATION_PAYMENT_UPLOAD_FAILED', 'business.affiliations.upload-payment', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'payment_method' => $data['payment_method'] ?? null,
                                    'error' => $th->getMessage(),
                                ]);
                                Notification::make()
                                    ->title('ERROR')
                                    ->body('Ocurrio un error al registrar el comprobante de pago')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /**DESCARGAR CERTIFICADO PDF */
                    Action::make('download')
                        ->label('Descargar Certificado')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->requiresConfirmation()
                        ->modalHeading('DESCARGAR CERTIFICADO')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-s-arrow-down-on-square-stack')
                        ->modalDescription('Descargará un archivo PDF al hacer clic en confirmar!.')
                        ->action(function (Affiliation $record, array $data) {

                            try {

                                $path = AffiliationBusinessDocumentsService::resolveCertificateAbsolutePath($record);

                                if ($path === null) {
                                    throw new \RuntimeException('No se encontró el certificado. Use «Regenerar Documentos» para generarlo nuevamente.');
                                }

                                Log::info('NEGOCIOS-AFILIACIONES: Descarga de certificado iniciada.', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'path' => $path,
                                ]);
                                self::audit('AUDIT_BUSINESS_AFFILIATION_CERTIFICATE_DOWNLOADED', 'business.affiliations.download-certificate', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'path' => $path,
                                ]);

                                return response()->download($path);
                            } catch (\Throwable $th) {
                                self::audit('AUDIT_BUSINESS_AFFILIATION_CERTIFICATE_DOWNLOAD_FAILED', 'business.affiliations.download-certificate', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'error' => $th->getMessage(),
                                ]);
                                Notification::make()
                                    ->title('ERROR EN LA DESCARGA')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /**DESCARGAR CERTIFICADO PDF */
                    Action::make('downloadTarjeta')
                        ->label('Descargar Tarjeta')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->requiresConfirmation()
                        ->modalHeading('DESCARGAR TARJETA DEL AFILIADO')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-s-arrow-down-on-square-stack')
                        ->modalDescription('Descargará un archivo PDF al hacer clic en confirmar!.')
                        ->action(function (Affiliation $record, array $data) {

                            try {

                                $path = AffiliationBusinessDocumentsService::resolveTitularTarjetaAbsolutePath($record);

                                if ($path === null) {
                                    throw new \RuntimeException('No se encontró la tarjeta generada. Use «Regenerar Documentos» para crearla nuevamente.');
                                }

                                Log::info('NEGOCIOS-AFILIACIONES: Descarga de tarjeta iniciada.', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'path' => $path,
                                ]);
                                self::audit('AUDIT_BUSINESS_AFFILIATION_CARD_DOWNLOADED', 'business.affiliations.download-card', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'path' => $path,
                                ]);

                                return response()->download($path);
                            } catch (\Throwable $th) {
                                self::audit('AUDIT_BUSINESS_AFFILIATION_CARD_DOWNLOAD_FAILED', 'business.affiliations.download-card', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'error' => $th->getMessage(),
                                ]);
                                Notification::make()
                                    ->title('ERROR EN LA DESCARGA')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    /**REGENERAR CERTIFICADO PDF */
                    Action::make('regenerate')
                        ->label('Regenerar Documentos')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->modalHeading('Certificado y tarjetas de afiliación')
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalIcon('heroicon-o-arrow-path')
                        ->modalDescription('Se generan el certificado (único) y una tarjeta por cada familiar. Al abrir se cargan las vistas previas y puede enviar por correo al agente.')
                        ->modalContent(function (Affiliation $record): ViewContract {
                            try {
                                Log::info('NEGOCIOS-AFILIACIONES: Modal de regeneración de documentos abierto.', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'agent_id' => $record->agent_id,
                                    'status' => $record->status,
                                ]);

                                self::audit('AUDIT_BUSINESS_AFFILIATION_DOCUMENTS_REGENERATE_OPENED', 'business.affiliations.regenerate-documents', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'agent_id' => $record->agent_id,
                                    'status' => $record->status,
                                ]);
                            } catch (\Throwable $th) {
                                self::audit('AUDIT_BUSINESS_AFFILIATION_DOCUMENTS_REGENERATE_OPEN_FAILED', 'business.affiliations.regenerate-documents', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'error' => $th->getMessage(),
                                ]);

                                Log::error('NEGOCIOS-AFILIACIONES: Error al abrir modal de regeneración de documentos.', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'error' => $th->getMessage(),
                                ]);
                            }

                            return View::make('filament.business.affiliations.affiliation-documents-preview-modal', [
                                'affiliation' => $record,
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->action(fn () => null),

                    /**DESCARGAR O REENVIAR KIT DE AFILIACION */
                    Action::make('download_resend_kit')
                        ->label('Kit de Bienvenida')
                        ->icon('heroicon-o-book-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('DESCARGAR O REENVIAR KIT DE BIENVENIDA')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-o-book-open')
                        ->modalDescription('Podras descargar o Reenviar el kit de bienvenida al hacer clic en confirmar!.')
                        ->form([
                            Fieldset::make('Opciones:')
                                ->schema([
                                    Select::make('option')
                                        ->options([
                                            'DESCARGAR' => 'DESCARGAR',
                                            'REENVIAR' => 'REENVIAR',
                                        ])
                                        ->live()
                                        ->required()
                                        ->label('Selecciona una Opción'),
                                    TextInput::make('email')
                                        ->email()
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Correo Electrónico')
                                        ->default(fn (Affiliation $record) => Agent::where('id', $record->agent_id)->first()->email ?? '')
                                        ->hidden(fn (Get $get) => $get('option') == 'DESCARGAR'),
                                ])->columnSpanFull()->columns(1),
                        ])
                        ->action(function (Affiliation $record, array $data) {

                            try {

                                /**
                                 * LIGICA DE DESCARGA O REENVIO DEL KIT DE BIENVENIDA VIA EMAIL
                                 *
                                 * @version 2.0
                                 */
                                if ($data['option'] == 'DESCARGAR') {
                                    $path = AffiliationController::downloadResendKit($record, $data);

                                    self::audit('AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_DOWNLOADED', 'business.affiliations.welcome-kit', [
                                        'affiliation_id' => $record->id,
                                        'affiliation_code' => $record->code,
                                        'option' => $data['option'],
                                    ]);

                                    return response()->download($path);
                                } else {
                                    AffiliationController::downloadResendKit($record, $data);
                                    self::audit('AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_RESENT', 'business.affiliations.welcome-kit', [
                                        'affiliation_id' => $record->id,
                                        'affiliation_code' => $record->code,
                                        'option' => $data['option'],
                                        'email' => $data['email'] ?? null,
                                    ]);
                                }
                            } catch (\Throwable $th) {
                                self::audit('AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_FAILED', 'business.affiliations.welcome-kit', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'option' => $data['option'] ?? null,
                                    'email' => $data['email'] ?? null,
                                    'error' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('ERROR EN LA DESCARGA O ENVIO DEL KIT')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(fn (Affiliation $record) => Auth::user()->is_business_admin != 1 || $record->status != 'ACTIVA'),

                    /**EDITAR FRECUENCIA DE PAGO */
                    Action::make('edit_frequency')
                        ->label('Editar Frecuencia de Pago')
                        ->icon('heroicon-m-pencil')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('EDITAR FRECUENCIA DE PAGO')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-m-pencil')
                        ->modalDescription('Este procedimiento permitira editar la frecuencia de pago y posterior sera actualizado el monto a pagar de la afiliación!.')
                        ->form([
                            Fieldset::make('payment_frequency')
                                ->label('Seleciona la Frecuencia de Pago')
                                ->schema([
                                    Select::make('payment_frequency')
                                        ->label('Frecuencia de Pago')
                                        ->options([
                                            'MENSUAL' => 'MENSUAL',
                                            'TRIMESTRAL' => 'TRIMESTRAL',
                                            'SEMESTRAL' => 'SEMESTRAL',
                                            'ANUAL' => 'ANUAL',
                                        ]),

                                ])->columnSpanFull(),
                        ])
                        ->action(function (Affiliation $record, array $data) {

                            try {

                                $record->payment_frequency = $data['payment_frequency'];

                                if ($data['payment_frequency'] == 'ANUAL') {
                                    $record->total_amount = $record->fee_anual;
                                }

                                if ($data['payment_frequency'] == 'SEMESTRAL') {
                                    $record->total_amount = $record->fee_anual / 2;
                                }

                                if ($data['payment_frequency'] == 'TRIMESTRAL') {
                                    $record->total_amount = $record->fee_anual / 4;
                                }

                                if ($data['payment_frequency'] == 'MENSUAL') {
                                    $record->total_amount = $record->fee_anual / 12;
                                }

                                $record->save();

                                self::audit('AUDIT_BUSINESS_AFFILIATION_PAYMENT_FREQUENCY_UPDATED', 'business.affiliations.edit-frequency', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'payment_frequency' => $data['payment_frequency'] ?? null,
                                    'total_amount' => $record->total_amount,
                                ]);

                                Notification::make()
                                    ->title('ACTUALIACION EXITOSA')
                                    ->body('La frecuencia de pago se ha actualizado con exito.')
                                    ->icon('heroicon-s-check-circle')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $th) {
                                Log::error($th->getMessage());
                                self::audit('AUDIT_BUSINESS_AFFILIATION_PAYMENT_FREQUENCY_UPDATE_FAILED', 'business.affiliations.edit-frequency', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'payment_frequency' => $data['payment_frequency'] ?? null,
                                    'error' => $th->getMessage(),
                                ]);
                                Notification::make()
                                    ->title('ERROR AL ACTUALIZAR FRECUENCIA DE PAGO')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(fn () => ! in_array('SUPERADMIN', Auth::user()->departament)),

                    Action::make('change_status')
                        ->label('Actualizar Estatus')
                        ->color('info')
                        ->icon('heroicon-s-check-circle')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ExtraLarge)
                        ->modalHeading('ACCIONES')
                        ->form([
                            Section::make()
                                ->heading('ACCIONES')
                                ->description('Seleccione la accion que desea realizar')
                                ->icon('heroicon-s-check-circle')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Radio::make('action')
                                            ->label('Que accion deseas realizar?')
                                            ->options([
                                                'observation' => 'Anadir observaciones',
                                                'status' => 'Actualizar estatus',
                                                'exclude' => 'Excluir Afiliación',
                                            ])
                                            ->live()
                                            ->required(),
                                        // ->inline()
                                    ]),

                                    Grid::make(1)->schema([
                                        Textarea::make('description')
                                            ->label('Observaciones')
                                            ->autosize()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('description', strtoupper($state));
                                            }),
                                    ])->hidden(fn (Get $get) => $get('action') != 'observation'),

                                    Grid::make(1)->schema([
                                        Select::make('status')
                                            ->label('Estatus')
                                            ->options([
                                                'ACTIVA' => 'ACTIVA',
                                                'PRE-APROBADA' => 'PRE-APROBADA',
                                                'EXCLUIDO' => 'EXCLUIDO',
                                            ])
                                            ->searchable()
                                            ->preload(),
                                        Textarea::make('description')
                                            ->autosize()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('description', strtoupper($state));
                                            }),
                                    ])->hidden(fn (Get $get) => $get('action') != 'status'),

                                    Grid::make(1)->schema([
                                        DatePicker::make('date_egress')
                                            ->label('Fecha de egreso')
                                            ->format('d-m-Y'),
                                        Textarea::make('description')
                                            ->label('Observaciones')
                                            ->autosize()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('description', strtoupper($state));
                                            }),
                                    ])->hidden(fn (Get $get) => $get('action') != 'exclude'),
                                ]),
                        ])
                        ->action(function (Affiliation $record, array $data): void {
                            if ($data['action'] == 'observation') {
                                $record->status_log_affiliations()->create([
                                    'affiliation_id' => $record->id,
                                    'action' => 'AGREGO OBSERVACION',
                                    'observation' => $data['description'],
                                    'updated_by' => Auth::user()->name,
                                ]);
                                self::audit('AUDIT_BUSINESS_AFFILIATION_OBSERVATION_ADDED', 'business.affiliations.change-status', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'action' => $data['action'],
                                    'description' => $data['description'] ?? null,
                                ]);
                                Notification::make()
                                    ->title('AFILIACION ACTUALIZADA')
                                    ->success()
                                    ->send();

                                return;
                            }

                            if ($data['action'] == 'status') {
                                $record->update([
                                    'status' => $data['status'],
                                ]);
                                $record->status_log_affiliations()->create([
                                    'affiliation_id' => $record->id,
                                    'action' => 'CAMBIO ESTATUS A: '.$data['status'],
                                    'observation' => $data['description'],
                                    'updated_by' => Auth::user()->name,
                                ]);
                                self::audit('AUDIT_BUSINESS_AFFILIATION_STATUS_UPDATED', 'business.affiliations.change-status', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'action' => $data['action'],
                                    'status' => $data['status'] ?? null,
                                    'description' => $data['description'] ?? null,
                                ]);
                                Notification::make()
                                    ->title('AFILIACION ACTUALIZADA')
                                    ->success()
                                    ->send();

                                return;
                            }

                            if ($data['action'] == 'exclude') {
                                $record->update([
                                    'status' => 'EXCLUIDO',
                                    'fee_anual' => 0.0,
                                    'activated_at' => null,
                                    'total_amount' => 0.0,
                                    'family_members' => 0,
                                ]);
                                $record->affiliates()->update([
                                    'status' => 'EXCLUIDO',
                                ]);
                                $record->status_log_affiliations()->create([
                                    'affiliation_id' => $record->id,
                                    'action' => 'EXCLUYO AFILIACION, FECHA DE EGRESO: '.$data['date_egress'],
                                    'observation' => $data['description'],
                                    'updated_by' => Auth::user()->name,
                                ]);
                                self::audit('AUDIT_BUSINESS_AFFILIATION_EXCLUDED', 'business.affiliations.change-status', [
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'action' => $data['action'],
                                    'date_egress' => $data['date_egress'] ?? null,
                                    'description' => $data['description'] ?? null,
                                ]);
                                Notification::make()
                                    ->title('AFILIACION ACTUALIZADA')
                                    ->success()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('AFILIACION ACTUALIZADA')
                                ->success()
                                ->send();
                        }),

                ])->hidden(fn ($record) => $record->status == 'EXCLUIDO'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([

                    DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-m-trash')
                        ->label('Eliminar Registro(s)')
                        ->modalHeading('ELIMINAR REGOSTRO DE AFILIACION(ES)')
                        ->modalDescription('Esta accion elimina la afiliacion(es) seleccionada(s) de manera permanente, asi como tambien todas sus asociaciones.)')
                        ->action(function (Collection $records) {

                            try {
                                $recordIds = $records->pluck('id')->values()->all();

                                foreach ($records as $record) {

                                    // Elimina la afiliacion
                                    $record->delete();
                                    Log::info('AFILIACIONES: El usuario '.Auth::user()->name.' elimino la afiliacion: '.$record->id);

                                    // Elimina la Cotizacion individual
                                    $record->individual_quote()->delete();
                                    Log::info('AFILIACIONES: El usuario '.Auth::user()->name.' elimino la cotizacion individual: '.$record->individual_quote->id);

                                    // Eliminamos los afiliados
                                    $record->affiliates()->delete();
                                    Log::info('AFILIACIONES: El usuario '.Auth::user()->name.' elimino el afiliado: '.$record->affiliates()->first()->id);
                                }

                                self::audit('AUDIT_BUSINESS_AFFILIATIONS_BULK_DELETED', 'business.affiliations.bulk-delete', [
                                    'record_ids' => $recordIds,
                                    'total' => count($recordIds),
                                ]);
                            } catch (\Throwable $th) {
                                self::audit('AUDIT_BUSINESS_AFFILIATIONS_BULK_DELETE_FAILED', 'business.affiliations.bulk-delete', [
                                    'error' => $th->getMessage(),
                                ]);
                                Notification::make()
                                    ->title('REGISTRO NO ELIMINADO')
                                    ->body($th->getMessage().' Linea: '.$th->getLine().' Archivo: '.$th->getFile())
                                    ->icon('heroicon-m-x-circle')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    BulkAction::make('pay_multiple_affiliations')
                        ->label('Pagar afiliaciones')
                        ->icon('fontisto-share')
                        ->color('success')
                        ->modalHeading('PAGO MULTIPLE DE AFILIACIONES')
                        ->modalIcon('heroicon-m-shield-check')
                        ->modalWidth(Width::FourExtraLarge)
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->form(function (Collection $records) {

                            $data = $records->toArray();

                            // guardo la data en la sesion para usarla en el formulario
                            session()->put('data', $data);

                            return [

                                /** INFORMACION PRINCIPAL */
                                Fieldset::make('INFORMACION PRINCIPAL')
                                    ->schema([
                                        Grid::make(1)->schema([
                                            TextInput::make('total_amount')
                                                ->label('Total a pagar')
                                                ->prefix('US$')
                                                ->default(function () {
                                                    return array_sum(array_column(session()->get('data'), 'total_amount'));
                                                })
                                                ->numeric()
                                                ->live()
                                                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                                    if (in_array($get('payment_method'), ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true)) {
                                                        $rate = AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal($get('pay_amount_ves'), $state);
                                                        if ($rate !== null) {
                                                            $set('tasa_bcv', $rate);
                                                        }

                                                        return;
                                                    }

                                                    if ($get('payment_method') === 'MULTIPLE') {
                                                        $total = AffiliationPaymentBcvRateCalculator::positiveAmount($state);
                                                        $usdPart = AffiliationPaymentBcvRateCalculator::nonNegativeFloat($get('pay_amount_usd'));
                                                        if ($total === null || $usdPart === null) {
                                                            return;
                                                        }

                                                        $remainingUsd = $total - $usdPart;
                                                        if ($remainingUsd <= 0) {
                                                            return;
                                                        }

                                                        $rate = AffiliationPaymentBcvRateCalculator::rateFromVesAndRemainingUsd($get('pay_amount_ves'), $remainingUsd);
                                                        if ($rate !== null) {
                                                            $set('tasa_bcv', $rate);
                                                        }
                                                    }
                                                }),
                                        ])->columnSpanFull(),
                                    ])->columnSpanFull(),

                                /**FORMA DE PAGO */
                                Fieldset::make('FORMA DE PAGO')
                                    ->schema([

                                        /**SELECCION DEL METODO DE PAGO */
                                        Grid::make(1)
                                            ->schema([
                                                Select::make('payment_method')
                                                    ->native(false)
                                                    ->label('Método de pago')
                                                    ->options([
                                                        'ZELLE' => 'ZELLE',
                                                        'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                                        'EFECTIVO US$' => 'EFECTIVO US$',
                                                        'MULTIPLE' => 'MULTIPLE',
                                                        'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
                                                        'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',

                                                    ])
                                                    ->live()
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Seleccione un tipo de pago',
                                                    ]),
                                            ])->columnSpan(3),

                                        /* PAGO EN DOLARES ZELLE */
                                        Fieldset::make('INFORMACION DE PAGO EN ZELLE (US$)')
                                            ->schema([
                                                TextInput::make('name_ti_usd')
                                                    ->label('Nombre del Titular')
                                                    ->helperText('Debe colocar Nombre y Apellido')
                                                    ->prefixIcon('heroicon-s-pencil')
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Seleccione un tipo de pago',
                                                    ]),
                                                TextInput::make('reference_payment_usd')
                                                    ->label('Nro. de Referencia')
                                                    ->helperText('Debe colocar el número de referencia completo')
                                                    ->prefix('#')
                                                    ->regex('/^[A-Za-z0-9\-]+$/')
                                                    ->helperText('Solo se permiten letras, números y el guion (-)')
                                                    ->required()
                                                    ->validationMessages([
                                                        'regex' => 'Solo se permite el guion (-)',
                                                        'required' => 'Seleccione un tipo de pago',
                                                    ]),

                                                Grid::make(1)->schema([
                                                    FileUpload::make('document_usd')
                                                        ->label('Comprobante(US$)')
                                                        ->uploadingMessage('Cargando...')
                                                        ->required(),
                                                ]),
                                            ])->columnSpanFull()->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'ZELLE') {
                                                    return false;
                                                }

                                                return true;
                                            }),

                                        /** PAGO EN TRANSFERENCIA US$ */
                                        Fieldset::make('INFORMACIÓN DE PAGO EN TRANSFERENCIA (US$)')
                                            ->schema([
                                                Grid::make()->schema([
                                                    TextInput::make('name_ti_usd')
                                                        ->label('Nombre del Titular')
                                                        ->helperText('Debe colocar Nombre y Apellido')
                                                        ->prefixIcon('heroicon-s-pencil')
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => 'Campo requerido',
                                                        ]),

                                                    Select::make('bank_usd')
                                                        ->native(false)
                                                        ->label('Banco')
                                                        ->live()
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => 'Seleccione un banco',
                                                        ])
                                                        ->options([
                                                            'CHASE BANK' => 'CHASE BANK',
                                                            'BANK OF AMERICA' => 'BANK OF AMERICA',
                                                            'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
                                                            'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                                                            'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
                                                        ])
                                                        ->searchable()
                                                        ->live()
                                                        ->prefixIcon('heroicon-s-globe-europe-africa'),

                                                    Grid::make(1)->schema([
                                                        FileUpload::make('document_usd')
                                                            ->label('Comprobante(US$)')
                                                            ->uploadingMessage('Cargando...')
                                                            ->required(),
                                                    ]),
                                                ])->columnSpanFull(),
                                            ])->columnSpanFull()->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'TRANSFERENCIA US$') {
                                                    return false;
                                                }

                                                return true;
                                            }),

                                        /** PAGO EN EFECTIVO US$ */
                                        Fieldset::make('INFORMACIÓN DE PAGO EN EFECTIVO (US$)')
                                            ->schema([
                                                Grid::make(2)->schema([
                                                    Select::make('bank_usd')
                                                        ->native(false)
                                                        ->label('Banco')
                                                        ->live()
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => 'Seleccione un banco',
                                                        ])
                                                        ->options([
                                                            'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                                                            'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
                                                        ])
                                                        ->searchable()
                                                        ->live()
                                                        ->prefixIcon('heroicon-s-globe-europe-africa'),

                                                    Grid::make()->schema([
                                                        FileUpload::make('document_usd')
                                                            ->label('Comprobante(US$)')
                                                            ->uploadingMessage('Cargando...')
                                                            ->required(),
                                                    ])->columnSpanFull(),
                                                ])->hidden(function (Get $get) {
                                                    if ($get('payment_method') == 'EFECTIVO US$') {
                                                        return false;
                                                    }

                                                    return true;
                                                })->columnSpanFull(),

                                            ])->columnSpanFull()->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'EFECTIVO US$') {
                                                    return false;
                                                }

                                                return true;
                                            }),

                                        /* PAGO MOVIL Y TRANSFERENCIA */
                                        Fieldset::make('INFORMACIÓN DE PAGO EN MONEDA NACIONAL (VES)')
                                            ->schema([
                                                Grid::make(2)->schema([

                                                    TextInput::make('pay_amount_ves')
                                                        ->inputMode('numeric')
                                                        ->live(onBlur: true)
                                                        ->label('Monto a pagar en VES')
                                                        ->helperText('Ingrese el monto en bolívares. La tasa BCV se calcula automáticamente según el total en US$.')
                                                        ->prefix('VES')
                                                        ->numeric()
                                                        ->required(fn (Get $get): bool => in_array($get('payment_method'), ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true))
                                                        ->validationMessages([
                                                            'required' => 'Campo requerido',
                                                            'numeric' => 'El campo es numérico',
                                                        ])
                                                        ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                                            if (! in_array($get('payment_method'), ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true)) {
                                                                return;
                                                            }

                                                            $rate = AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal($state, $get('total_amount'));
                                                            if ($rate !== null) {
                                                                $set('tasa_bcv', $rate);
                                                            }
                                                        }),
                                                    TextInput::make('tasa_bcv')
                                                        ->label('Tasa BCV (calculada)')
                                                        ->helperText('Bs por US$: se calcula al dividir el monto en bolívares entre el total en US$.')
                                                        ->prefix('VES')
                                                        ->numeric()
                                                        ->disabled()
                                                        ->dehydrated(),
                                                    Select::make('bank_ves')
                                                        ->native(false)
                                                        ->label('Banco')
                                                        ->live()
                                                        ->options([
                                                            'BANCAMIGA(VES)' => 'BANCAMIGA',
                                                            'BANCO DE VENEZUELA(VES)' => 'BANCO DE VENEZUELA',
                                                        ])
                                                        ->searchable()
                                                        ->live()
                                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                                        ->preload(),
                                                    TextInput::make('reference_payment_ves')
                                                        ->label('Referencia de pago(VES)')
                                                        ->live()
                                                        ->inputMode('numeric') // activa teclado numérico en móvil
                                                        ->helperText('Últimos 6 dígitos del comprobante de pago')
                                                        ->mask('999999')
                                                        ->maxLength(6)
                                                        ->rules([
                                                            'regex:/^\d{1,6}$/', // Acepta de 1 a 6 dígitos
                                                        ])
                                                        ->prefix('Ref:'),
                                                    Grid::make(1)->schema([
                                                        FileUpload::make('document_ves')
                                                            ->label('Comprobante de pago(VES)')
                                                            ->disk('public')
                                                            ->uploadingMessage('Cargando...')
                                                            ->required(),
                                                    ]),

                                                ])->columnSpanFull(),
                                            ])->columnSpanFull()->hidden(function (Get $get): bool {
                                                return ! in_array($get('payment_method'), ['TRANSFERENCIA VES', 'PAGO MOVIL VES'], true);
                                            }),

                                        /** PAGO MULTIPLE */
                                        Fieldset::make('INFORMACIÓN DE PAGO MULTIPLE EN BOLIVARES (VES) Y DOLARES (US$)')
                                            ->schema([
                                                Grid::make(2)->schema([

                                                    /* PAGO EN DOLARES(USD)) */
                                                    Fieldset::make('PAGO EN DOLARES (US$)')
                                                        ->schema([
                                                            /**Metodo de pago en US$ */
                                                            Select::make('payment_method_usd')
                                                                ->live()
                                                                ->native(false)
                                                                ->label('Método de pago en dólares(US$)')
                                                                ->options([
                                                                    'ZELLE' => 'ZELLE',
                                                                    'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                                                    'EFECTIVO US$' => 'EFECTIVO US$',
                                                                ])
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required' => 'Seleccione un tipo de pago',
                                                                ]),

                                                            TextInput::make('pay_amount_usd')
                                                                ->inputMode('numeric') // activa teclado numérico en móvil
                                                                ->live(onBlur: true)
                                                                ->label('Monto US$:')
                                                                ->helperText('Punto(.) para separar decimales. Ingresa el monto en dólares(US$). La tasa BCV se recalcula con el monto en bolívares.')
                                                                ->prefix('US$')
                                                                ->numeric()
                                                                ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                                                    if ($get('payment_method') !== 'MULTIPLE') {
                                                                        return;
                                                                    }

                                                                    $total = AffiliationPaymentBcvRateCalculator::positiveAmount($get('total_amount'));
                                                                    $usdPart = AffiliationPaymentBcvRateCalculator::nonNegativeFloat($state);
                                                                    if ($total === null || $usdPart === null) {
                                                                        return;
                                                                    }

                                                                    $remainingUsd = $total - $usdPart;
                                                                    if ($remainingUsd <= 0) {
                                                                        return;
                                                                    }

                                                                    $rate = AffiliationPaymentBcvRateCalculator::rateFromVesAndRemainingUsd($get('pay_amount_ves'), $remainingUsd);
                                                                    if ($rate !== null) {
                                                                        $set('tasa_bcv', $rate);
                                                                    }
                                                                }),

                                                            TextInput::make('name_ti_usd')
                                                                ->label('Nombre del Titular')
                                                                ->helperText('Debe colocar Nombre y Apellido')
                                                                ->prefixIcon('heroicon-s-pencil')
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required' => 'Seleccione un tipo de pago',
                                                                ])
                                                                ->hidden(function (Get $get) {
                                                                    if ($get('payment_method_usd') == 'TRANSFERENCIA US$' || $get('payment_method_usd') == 'ZELLE') {
                                                                        return false;
                                                                    }

                                                                    return true;
                                                                }),

                                                            /**Banco US$ */
                                                            Select::make('bank_usd')
                                                                ->native(false)
                                                                ->label('Banco Moneda Extranjera(US$)')
                                                                ->live()
                                                                ->options([
                                                                    'CHASE BANK' => 'CHASE BANK',
                                                                    'BANK OF AMERICA' => 'BANK OF AMERICA',
                                                                    'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
                                                                    'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                                                                    'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
                                                                ])
                                                                ->searchable()
                                                                ->prefixIcon('heroicon-s-globe-europe-africa'),

                                                            TextInput::make('reference_payment_usd')
                                                                ->label('Nro. de Referencia')
                                                                ->helperText('Debe colocar el número de referencia completo')
                                                                ->prefix('#')
                                                                ->regex('/^[A-Za-z0-9\-]+$/')
                                                                ->helperText('Solo se permiten letras, números y el guion (-)')
                                                                ->required()
                                                                ->validationMessages([
                                                                    'regex' => 'Solo se permite el guion (-)',
                                                                    'required' => 'Seleccione un tipo de pago',
                                                                ])
                                                                ->hidden(function (Get $get) {
                                                                    if ($get('payment_method_usd') == 'ZELLE') {
                                                                        return false;
                                                                    }

                                                                    return true;
                                                                }),

                                                            FileUpload::make('document_usd')
                                                                ->label('Comprobante de pago(US$)')
                                                                ->disk('public')
                                                                ->uploadingMessage('Cargando...'),

                                                        ])->columns(1),

                                                    /* PAGO EN BOLIVARES (VES) */
                                                    Fieldset::make('PAGO EN BOLIVARES (VES)')
                                                        ->schema([
                                                            /**Metodo de pago en VES */
                                                            Select::make('payment_method_ves')
                                                                ->native(false)
                                                                ->label('Método de pago en bolivares(VES)')
                                                                ->options([
                                                                    'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
                                                                    'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                                                                ])
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required' => 'Seleccione un tipo de pago',
                                                                ]),

                                                            TextInput::make('pay_amount_ves')
                                                                ->inputMode('numeric')
                                                                ->live(onBlur: true)
                                                                ->label('Monto VES:')
                                                                ->helperText('Ingrese el monto en bolívares correspondiente al saldo en US$. La tasa BCV se calcula con el total en US$ menos el monto en dólares.')
                                                                ->prefix('VES')
                                                                ->numeric()
                                                                ->required(fn (Get $get): bool => $get('payment_method') === 'MULTIPLE')
                                                                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                                                    if ($get('payment_method') !== 'MULTIPLE') {
                                                                        return;
                                                                    }

                                                                    $total = AffiliationPaymentBcvRateCalculator::positiveAmount($get('total_amount'));
                                                                    $usdPart = AffiliationPaymentBcvRateCalculator::nonNegativeFloat($get('pay_amount_usd'));
                                                                    if ($total === null || $usdPart === null) {
                                                                        return;
                                                                    }

                                                                    $remainingUsd = $total - $usdPart;
                                                                    if ($remainingUsd <= 0) {
                                                                        return;
                                                                    }

                                                                    $rate = AffiliationPaymentBcvRateCalculator::rateFromVesAndRemainingUsd($state, $remainingUsd);
                                                                    if ($rate !== null) {
                                                                        $set('tasa_bcv', $rate);
                                                                    }
                                                                }),

                                                            TextInput::make('tasa_bcv')
                                                                ->label('Tasa BCV (calculada)')
                                                                ->helperText('Bs por US$: monto en bolívares dividido entre el saldo en US$ (total menos monto en dólares).')
                                                                ->prefix('VES')
                                                                ->numeric()
                                                                ->disabled()
                                                                ->dehydrated(),

                                                            /**Banco VES */
                                                            Select::make('bank_ves')
                                                                ->native(false)
                                                                ->label('Banco Moneda Nacional(VES)')
                                                                ->options([
                                                                    'BANCAMIGA - VES' => 'BANCAMIGA - VES',
                                                                    'BANCO DE VENEZUELA - VES' => 'BANCO DE VENEZUELA - VES',
                                                                ])
                                                                ->searchable()
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required' => 'Seleccione un banco',
                                                                ])
                                                                ->prefixIcon('heroicon-s-globe-europe-africa'),

                                                            TextInput::make('reference_payment_ves')
                                                                ->label('Referencia de pago(VES)')
                                                                ->inputMode('numeric') // activa teclado numérico en móvil
                                                                ->helperText('Ultimos 6 dígitos del comprobante de pago')
                                                                ->mask('999999')
                                                                ->maxLength(6)
                                                                ->rules([
                                                                    'regex:/^\d{1,6}$/', // Acepta de 1 a 6 dígitos
                                                                ])
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required' => 'Campo requerido',
                                                                ])
                                                                ->prefix('Ref:'),
                                                            FileUpload::make('document_ves')
                                                                ->label('Comprobante de pago(VES)')
                                                                ->disk('public')
                                                                ->uploadingMessage('Cargando...')
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required' => 'El comprobante es requerido',
                                                                ]),
                                                        ])->columns(1),

                                                ])->columnSpanFull(),
                                            ])->columnSpanFull()->hidden(function (Get $get): bool {
                                                return $get('payment_method') !== 'MULTIPLE';
                                            }),

                                    ]),

                                /**OBSERVACIONES */
                                Grid::make(1)->schema([
                                    Textarea::make('observations_payment')
                                        ->label('Observaciones')
                                        ->rows(2)
                                        ->autosize()
                                        ->dehydrated(),
                                ]),
                            ];
                        })
                        ->action(function (Collection $records, array $data) {
                            $recordIds = $records->pluck('id')->values()->all();
                            try {
                                $upload = AffiliationController::uploadPaymentMultipleAffiliations($records, $data, 'AGENTE');

                                if ($upload) {
                                    Notification::make()
                                        ->title('NOTIFICACION')
                                        ->body('El comprobante de pago se ha registrado con exito')
                                        ->icon('heroicon-m-user-plus')
                                        ->iconColor('success')
                                        ->success()
                                        ->seconds(5)
                                        ->send();
                                }

                                self::audit('AUDIT_BUSINESS_AFFILIATIONS_BULK_PAYMENT_UPLOAD', 'business.affiliations.bulk-upload-payment', [
                                    'record_ids' => $recordIds,
                                    'total' => count($recordIds),
                                    'uploaded' => (bool) $upload,
                                    'payment_method' => $data['payment_method'] ?? null,
                                ]);
                            } catch (\Throwable $th) {
                                self::audit('AUDIT_BUSINESS_AFFILIATIONS_BULK_PAYMENT_UPLOAD_FAILED', 'business.affiliations.bulk-upload-payment', [
                                    'record_ids' => $recordIds,
                                    'total' => count($recordIds),
                                    'payment_method' => $data['payment_method'] ?? null,
                                    'error' => $th->getMessage(),
                                ]);
                                throw $th;
                            }
                        }),

                    BulkAction::make('edit_frequency_payment')
                        ->hidden(fn () => Auth::user()->is_business_admin != 1)
                        ->label('Editar Frecuencia de Pago')
                        ->modalWidth(Width::ExtraLarge)
                        ->color('warning')
                        ->icon('heroicon-m-pencil')
                        ->form([
                            Select::make('frequency_payment')
                                ->native(false)
                                ->label('Frecuencia de pago')
                                ->options([
                                    'TRIMESTRAL' => 'TRIMESTRAL',
                                    'SEMESTRAL' => 'SEMESTRAL',
                                    'MENSUAL' => 'MENSUAL',
                                    'ANUAL' => 'ANUAL',
                                ])
                                ->required()
                                ->validationMessages([
                                    'required' => 'Seleccione una frecuencia de pago',
                                ]),
                        ])
                        ->action(function (Collection $records, array $data) {

                            try {
                                $recordIds = $records->pluck('id')->values()->all();

                                // Actualizamos el registro para cambiar la frecuencia de pago
                                foreach ($records as $record) {

                                    $record->payment_frequency = $data['frequency_payment'];

                                    if ($data['frequency_payment'] == 'TRIMESTRAL') {
                                        $record->total_amount = $record->fee_anual / 4;
                                    }
                                    if ($data['frequency_payment'] == 'SEMESTRAL') {
                                        $record->total_amount = $record->fee_anual / 2;
                                    }
                                    if ($data['frequency_payment'] == 'MENSUAL') {
                                        $record->total_amount = $record->fee_anual / 12;
                                    }
                                    if ($data['frequency_payment'] == 'ANUAL') {
                                        $record->total_amount = $record->fee_anual;
                                    }

                                    $record->save();
                                }

                                Log::info('Frecuencia de pago actualizada con exito. Usuario: '.Auth::user()->name);
                                Notification::make()
                                    ->title('NOTIFICACION')
                                    ->body('La frecuencia de pago se ha actualizado con exito')
                                    ->icon('heroicon-m-user-plus')
                                    ->iconColor('success')
                                    ->success()
                                    ->send();

                                self::audit('AUDIT_BUSINESS_AFFILIATIONS_BULK_FREQUENCY_UPDATED', 'business.affiliations.bulk-edit-frequency', [
                                    'record_ids' => $recordIds,
                                    'total' => count($recordIds),
                                    'frequency_payment' => $data['frequency_payment'] ?? null,
                                ]);
                            } catch (\Throwable $th) {
                                Log::error($th->getMessage());
                                self::audit('AUDIT_BUSINESS_AFFILIATIONS_BULK_FREQUENCY_UPDATE_FAILED', 'business.affiliations.bulk-edit-frequency', [
                                    'frequency_payment' => $data['frequency_payment'] ?? null,
                                    'error' => $th->getMessage(),
                                ]);
                                Notification::make()
                                    ->title('¡ERROR!')
                                    ->icon('heroicon-m-exclamation-triangle')
                                    ->body('Hubo un error al actualizar la frecuencia de pago. Por favor, contacte con el administrador del Sistema.')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    BulkAction::make('reassign_affiliation')
                        ->hidden(fn () => Auth::user()->is_business_admin != 1)
                        ->label('Reasignar Afiliación')
                        ->modalWidth(Width::ExtraLarge)
                        ->color('warning')
                        ->icon('heroicon-s-squares-plus')
                        ->form([
                            Section::make('REASIGNAR AFILIACION')
                                ->description('En esta seccion realizaras una reasignacion de las afiliaciones seleccionadas')
                                ->schema([
                                    Select::make('owner_code')
                                        ->label('Pertenece a una Agencia Master?')
                                        ->helperText('Si el agente pertenece a una agencia master, debes seleccionarla')
                                        ->options(function (Get $get, $record) {
                                            return Agency::all()
                                                ->where('status', 'ACTIVO')
                                                ->mapWithKeys(function ($agency) {
                                                    $type = AgencyType::find($agency->agency_type_id)->definition;

                                                    return [$agency->code => "{$type} - {$agency->code}"];
                                                });
                                        })
                                        ->searchable()
                                        ->preload(),
                                    Select::make('code_agency')
                                        ->label('Pertenece a una Agencia General?')
                                        ->helperText('Si el agente pertenece a una agencia general, debes seleccionarla')
                                        ->options(function (Get $get, $record) {
                                            return Agency::all()
                                                ->where('status', 'ACTIVO')
                                                ->where('agency_type_id', 3)
                                                ->mapWithKeys(function ($agency) {
                                                    $type = AgencyType::find($agency->agency_type_id)->definition;

                                                    return [$agency->code => "{$type} - {$agency->code}"];
                                                });
                                        })
                                        ->searchable()
                                        ->preload(),
                                    Select::make('agent_id')
                                        ->native(false)
                                        ->label('Propietario')
                                        ->options(function (Get $get, $record) {
                                            return Agent::all()
                                                ->where('status', 'ACTIVO')
                                                ->mapWithKeys(function ($agent) {
                                                    return [$agent->id => "{$agent->owner_code} - {$agent->name}"];
                                                });
                                        })
                                        ->searchable()
                                        ->preload(),
                                ]),
                        ])
                        ->action(function (Collection $records, array $data) {

                            try {
                                $recordIds = $records->pluck('id')->values()->all();

                                // 1.- Master
                                if ($data['owner_code'] != null && $data['code_agency'] == null && $data['agent_id'] == null) {
                                    $records->each(function ($record) use ($data) {
                                        $record->update([
                                            'owner_code' => $data['owner_code'],
                                            'code_agency' => $data['owner_code'],
                                            'agent_id' => null,
                                        ]);
                                    });
                                }

                                // 2.- General
                                if ($data['code_agency'] != null && $data['owner_code'] == null && $data['agent_id'] == null) {
                                    $records->each(function ($record) use ($data) {
                                        $record->update([
                                            'owner_code' => $data['code_agency'],
                                            'code_agency' => $data['code_agency'],
                                            'agent_id' => null,
                                        ]);
                                    });
                                }

                                // 3.- Agente
                                if ($data['owner_code'] == null && $data['code_agency'] == null && $data['agent_id'] != null) {
                                    $records->each(function ($record) use ($data) {
                                        $record->update([
                                            'owner_code' => 'TDG-100',
                                            'code_agency' => 'TDG-100',
                                            'agent_id' => $data['agent_id'],
                                        ]);
                                    });
                                }

                                // 4.- Master + General + Agente
                                if ($data['owner_code'] != null && $data['code_agency'] != null && $data['agent_id'] != null) {
                                    $records->each(function ($record) use ($data) {
                                        $record->update([
                                            'owner_code' => $data['owner_code'],
                                            'code_agency' => $data['code_agency'],
                                            'agent_id' => $data['agent_id'],
                                        ]);
                                    });
                                }

                                // 5.- Master + General
                                if ($data['owner_code'] != null && $data['code_agency'] != null && $data['agent_id'] == null) {
                                    $records->each(function ($record) use ($data) {
                                        $record->update([
                                            'owner_code' => $data['owner_code'],
                                            'code_agency' => $data['code_agency'],
                                            'agent_id' => null,
                                        ]);
                                    });
                                }

                                // 6.- Master + Agente
                                if ($data['owner_code'] != null && $data['code_agency'] == null && $data['agent_id'] != null) {
                                    $records->each(function ($record) use ($data) {
                                        $record->update([
                                            'owner_code' => $data['owner_code'],
                                            'code_agency' => $data['owner_code'],
                                            'agent_id' => $data['agent_id'],
                                        ]);
                                    });
                                }

                                // 7.- General + Agente
                                if ($data['owner_code'] == null && $data['code_agency'] != null && $data['agent_id'] != null) {
                                    $records->each(function ($record) use ($data) {
                                        $record->update([
                                            'owner_code' => $data['code_agency'],
                                            'code_agency' => $data['code_agency'],
                                            'agent_id' => $data['agent_id'],
                                        ]);
                                    });
                                }

                                Notification::make()
                                    ->title('Actualización Exitosa')
                                    ->icon('heroicon-m-check-circle')
                                    ->body('Los códigos han sido actualizados correctamente.')
                                    ->success()
                                    ->send();

                                self::audit('AUDIT_BUSINESS_AFFILIATIONS_BULK_REASSIGNED', 'business.affiliations.bulk-reassign', [
                                    'record_ids' => $recordIds,
                                    'total' => count($recordIds),
                                    'owner_code' => $data['owner_code'] ?? null,
                                    'code_agency' => $data['code_agency'] ?? null,
                                    'agent_id' => $data['agent_id'] ?? null,
                                ]);
                            } catch (\Throwable $th) {

                                // 4. Registro de error con contexto para debugging senior
                                Log::error('NEGOCIOS-AFILIACIONES: Fallo al actualizar registros de pago', [
                                    'input_data' => $data,
                                    'exception_message' => $th->getMessage(),
                                    'file' => $th->getFile(),
                                    'line' => $th->getLine(),
                                ]);
                                self::audit('AUDIT_BUSINESS_AFFILIATIONS_BULK_REASSIGN_FAILED', 'business.affiliations.bulk-reassign', [
                                    'owner_code' => $data['owner_code'] ?? null,
                                    'code_agency' => $data['code_agency'] ?? null,
                                    'agent_id' => $data['agent_id'] ?? null,
                                    'error' => $th->getMessage(),
                                ]);

                                // 5. Notificación de error amigable y persistente
                                Notification::make()
                                    ->title('Error de Actualización')
                                    ->icon('heroicon-m-exclamation-triangle')
                                    ->body('No se pudo procesar la actualización de los códigos.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                // Re-lanzamos si estamos dentro de una transacción mayor o queremos que Filament maneje el rollback
                                throw $th;
                            }
                        }),

                    BulkAction::make('exportAffiliationsCsv')
                        ->label('Exportar Afiliaciones')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos una afiliación')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $token = AffiliationExportCsvController::storeFiltersAndGetToken([
                                'affiliation_ids' => $records->pluck('id')->all(),
                            ], 'business');

                            return redirect()->route('business.affiliations.export-csv', ['token' => $token]);
                        }),
                    BulkAction::make('exportAffiliatesCsv')
                        ->label('Exportar Afiliados')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos una afiliación')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $token = AffiliateExportCsvController::storeFiltersAndGetToken([
                                'affiliation_ids' => $records->pluck('id')->all(),
                            ], 'business');

                            return redirect()->route('business.affiliates.export-csv', ['token' => $token]);
                        }),
                ]),
            ])
            ->recordClasses(fn (Affiliation $record): array => self::rowClassesForAffiliationActivatedToday($record))
            ->striped();
    }

    /**
     * Fila resaltada cuando la afiliación se activó hoy (activated_at en formato d/m/Y).
     *
     * @return array<int, string>
     */
    private static function rowClassesForAffiliationActivatedToday(Affiliation $record): array
    {
        $activatedAt = $record->getAttribute('activated_at');
        if ($activatedAt === null || $activatedAt === '') {
            return [];
        }

        $today = Carbon::today()->format('d/m/Y');
        if (trim((string) $activatedAt) !== $today) {
            return [];
        }

        return [
            'bg-[#34C759]/14 dark:bg-[#34C759]/18 border-l-[3px] border-[#34C759] shadow-[inset_0_1px_0_rgba(255,255,255,0.55)] dark:border-[#30D158] dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]',
        ];
    }

    private static function paymentTotalPreviewHtml(Get $get): HtmlString
    {
        $base = AffiliationPaymentTotalAdjustment::parseAmount($get('base_total_amount'))
            ?? AffiliationPaymentTotalAdjustment::parseAmount($get('total_amount'))
            ?? 0.0;
        $percentage = is_numeric($get('payment_adjustment_percentage'))
            ? (float) $get('payment_adjustment_percentage')
            : 0.0;
        $bcvRate = AffiliationPaymentTotalAdjustment::parseAmount($get('tasa_bcv'))
            ?? BcvOfficialRate::resolve();

        return AffiliationPaymentTotalAdjustment::previewHtml($base, $percentage, $bcvRate);
    }

    private static function applyPaymentTotalPercentageAdjustment(Get $get, Set $set): void
    {
        $base = AffiliationPaymentTotalAdjustment::parseAmount($get('base_total_amount'));

        if ($base === null) {
            return;
        }

        $percentage = is_numeric($get('payment_adjustment_percentage'))
            ? (float) $get('payment_adjustment_percentage')
            : 0.0;

        $adjustedTotal = AffiliationPaymentTotalAdjustment::adjust($base, $percentage);
        $set('total_amount', $adjustedTotal);
        self::syncPaymentBcvRateFromTotal($get, $set, $adjustedTotal);
    }

    private static function syncPaymentBcvRateFromTotal(Get $get, Set $set, mixed $totalAmount): void
    {
        if (! self::shouldAutoCalculateBcvRate($get)) {
            return;
        }

        $paymentMethod = $get('payment_method');

        if (in_array($paymentMethod, ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true)) {
            self::applyCalculatedBcvRate(
                $set,
                AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal($get('pay_amount_ves'), $totalAmount),
            );

            return;
        }

        if ($paymentMethod === 'MULTIPLE') {
            self::syncMultiplePaymentBcvRate($get, $set, $totalAmount);
        }
    }

    private static function syncPaymentBcvRateFromVesAmount(Get $get, Set $set, mixed $vesAmount): void
    {
        if (! self::shouldAutoCalculateBcvRate($get)) {
            return;
        }

        if (in_array($get('payment_method'), ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true)) {
            self::applyCalculatedBcvRate(
                $set,
                AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal($vesAmount, $get('total_amount')),
            );

            return;
        }

        if ($get('payment_method') === 'MULTIPLE') {
            self::syncMultiplePaymentBcvRate($get, $set, $get('total_amount'), $vesAmount);
        }
    }

    private static function syncPaymentBcvRateFromUsdPart(Get $get, Set $set, mixed $usdPart): void
    {
        if (! self::shouldAutoCalculateBcvRate($get)) {
            return;
        }

        if ($get('payment_method') !== 'MULTIPLE') {
            return;
        }

        self::syncMultiplePaymentBcvRate($get, $set, $get('total_amount'), $get('pay_amount_ves'), $usdPart);
    }

    private static function syncMultiplePaymentBcvRate(
        Get $get,
        Set $set,
        mixed $totalAmount,
        mixed $vesAmount = null,
        mixed $usdPart = null,
    ): void {
        $total = AffiliationPaymentBcvRateCalculator::positiveAmount($totalAmount ?? $get('total_amount'));
        $usd = AffiliationPaymentBcvRateCalculator::nonNegativeFloat($usdPart ?? $get('pay_amount_usd'));

        if ($total === null || $usd === null) {
            return;
        }

        $remainingUsd = $total - $usd;

        if ($remainingUsd <= 0) {
            return;
        }

        self::applyCalculatedBcvRate(
            $set,
            AffiliationPaymentBcvRateCalculator::rateFromVesAndRemainingUsd(
                $vesAmount ?? $get('pay_amount_ves'),
                $remainingUsd,
            ),
        );
    }

    private static function applyCalculatedBcvRate(Set $set, ?string $rate): void
    {
        if ($rate === null) {
            return;
        }

        self::setCalculatedBcvRate($set, $rate);
    }

    /**
     * @return array<int, Hidden>
     */
    private static function bcvRateManualStateHiddenFields(): array
    {
        return [
            Hidden::make('tasa_bcv_manual')
                ->default(false)
                ->dehydrated(false),
            Hidden::make('tasa_bcv_calculated')
                ->dehydrated(false),
        ];
    }

    private static function bcvRateTextInput(string $helperText = ''): TextInput
    {
        $helper = 'Bs por US$: se calcula automáticamente al dividir el monto en bolívares entre el total en US$.';
        if ($helperText !== '') {
            $helper .= ' '.$helperText;
        }

        return TextInput::make('tasa_bcv')
            ->label('Tasa BCV (calculada)')
            ->helperText($helper)
            ->prefix('VES')
            ->numeric()
            ->disabled()
            ->dehydrated();
    }

    private static function setCalculatedBcvRate(Set $set, ?string $rate): void
    {
        if ($rate === null) {
            return;
        }

        $set('tasa_bcv_calculated', $rate);
        $set('tasa_bcv', $rate);
        $set('tasa_bcv_manual', false);
    }

    private static function resetBcvRateManualState(Set $set): void
    {
        $set('tasa_bcv_manual', false);
        $set('tasa_bcv_calculated', null);
    }

    private static function shouldAutoCalculateBcvRate(Get $get): bool
    {
        return ! filter_var($get('tasa_bcv_manual'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function audit(string $event, string $route, array $context = []): void
    {
        SecurityAudit::log($event, $route, [
            'panel' => 'business',
            'module' => 'affiliations',
            ...$context,
        ]);
    }
}
