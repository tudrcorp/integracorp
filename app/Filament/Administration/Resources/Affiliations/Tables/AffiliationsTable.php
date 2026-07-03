<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Affiliations\Tables;

use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Http\Controllers\AffiliateExportCsvController;
use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\AffiliationExportCsvController;
use App\Mail\UploadPayment;
use App\Models\Affiliation;
use App\Models\User;
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
use Filament\Actions\EditAction;
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
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class AffiliationsTable
{
    private const COLUMN_GROUP_HEADER_CLASS = '[&_th]:bg-gradient-to-r [&_th]:from-slate-100/95 [&_th]:via-slate-50/90 [&_th]:to-transparent dark:[&_th]:from-white/[0.08] dark:[&_th]:via-white/[0.04] dark:[&_th]:to-transparent [&_th]:font-semibold [&_th]:text-slate-800 dark:[&_th]:text-slate-100 [&_th]:border-b [&_th]:border-slate-200/80 dark:[&_th]:border-white/10';

    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todas')
                ->icon(Heroicon::OutlinedQueueList),
            'activa' => Tab::make('Activas')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'ACTIVA')),
            'pendiente' => Tab::make('Pendientes')
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'PENDIENTE')),
            'preaprobada' => Tab::make('Pre-aprobadas')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'PRE-APROBADA')),
            'excluido' => Tab::make('Excluidas')
                ->icon(Heroicon::OutlinedXCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'EXCLUIDO')),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (Builder $query): Builder {
                $base = Affiliation::query()->with([
                    'plan',
                    'coverage',
                    'individual_quote',
                    'accountManager',
                    'agency',
                    'agent',
                    'city',
                    'state',
                    'country',
                ]);

                if (Auth::user()->is_accountManagers) {
                    return $base->where('ownerAccountManagers', Auth::user()->id);
                }

                return $base;
            })
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Afiliaciones individuales')
            ->description('Plan, titular, montos y estatus. Las emitidas hoy en ACTIVA se resaltan en verde. Use pestañas y filtros para priorizar gestión.')
            ->striped()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->recordTitleAttribute('code')
            ->emptyStateHeading('Sin afiliaciones individuales')
            ->emptyStateDescription('No hay registros o no coinciden con la búsqueda y los filtros aplicados.')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->recordUrl(fn (Affiliation $record): string => AffiliationResource::getUrl('view', ['record' => $record]))
            ->columns([
                ColumnGroup::make('Resumen', [
                    TextColumn::make('status')
                        ->label('Estatus')
                        ->sortable()
                        ->badge()
                        ->color(fn (?string $state): string => self::statusColor($state))
                        ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                        ->searchable()
                        ->icon(fn (?string $state): Heroicon => self::statusIcon($state)),
                    TextColumn::make('code')
                        ->label('Código')
                        ->icon(function ($record) {
                            $now = Carbon::today();
                            if ($record->status == 'ACTIVA' && $record->created_at >= $now) {
                                return 'heroicon-c-star';
                            }

                            return 'heroicon-s-user-group';
                        })
                        ->iconColor(function ($record) {
                            $now = Carbon::today();
                            // Forzamos el color del icono a rojo (danger) solo cuando el if es true
                            if ($record->status == 'ACTIVA' && $record->created_at >= $now) {
                                return 'danger';
                            }

                            return null; // Color por defecto (blanco por el estilo extraAttributes)
                        })
                        ->badge(function ($record) {
                            $now = Carbon::today();
                            if ($record->status == 'ACTIVA' && $record->created_at >= $now) {
                                return false;
                            }

                            return true;
                        })
                        ->color(function ($record) {
                            return 'success';
                        })
                        ->searchable()
                        ->tooltip('Clic para ver ficha de afiliación')
                        ->extraAttributes(function ($record) {

                            /**
                             * Diseño optimizado con estilo iOS System Green.
                             * Utilizamos el verde oficial de Apple (#34C759) para máximo resaltado.
                             */
                            $iosGreen = '#34C759';
                            $iosGreenDark = '#248A3D'; // Para el texto, asegurando legibilidad

                            $now = Carbon::today();
                            // dd($now->diffInDays($record->created_at));

                            if ($record->status == 'ACTIVA' && $record->created_at >= $now) {
                                $iosGreen = '#34C759';
                                $iosGreenDark = '#248A3D';

                                return [
                                    'style' => "
                                        background-color: {$iosGreen} !important;
                                        color: #ffffff !important;
                                        font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
                                        font-weight: 700;
                                        font-size: 0.85rem;
                                        letter-spacing: -0.02em;
                                        padding: 0.2rem 0.8rem;
                                        border-radius: 20px;
                                        box-shadow: 0 4px 12px rgba(52, 199, 89, 0.35);
                                        border: 1px solid rgba(255, 255, 255, 0.2);
                                        text-shadow: 0px 1px 2px rgba(0, 0, 0, 0.1);
                                        display: inline-flex;
                                        align-items: center;
                                        margin-left: 2px;
                                    ",
                                ];
                            }

                            return [
                                'class' => 'cursor-pointer',
                            ];
                        }),
                    TextColumn::make('individual_quote.code')
                        ->label('Nro. cotización')
                        ->badge()
                        ->color('verde')
                        ->icon(Heroicon::OutlinedTag)
                        ->sortable()
                        ->searchable()
                        ->toggleable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Estructura comercial', [
                    TextColumn::make('accountManager.name')
                        ->label('Account Manager')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->badge()
                        ->sortable()
                        ->color(fn (?string $state): string => ($state === '-----' || blank($state)) ? 'gray' : 'success')
                        ->toggleable(),
                    TextColumn::make('agency.name_corporative')
                        ->label('Agencia')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : '—')
                        ->color('azulOscuro')
                        ->sortable()
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('agent.name')
                        ->label('Agente')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : '—')
                        ->color('azulOscuro')
                        ->sortable()
                        ->icon(Heroicon::OutlinedUser)
                        ->searchable()
                        ->toggleable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Plan y montos', [
                    TextColumn::make('plan.description')
                        ->label('Plan')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('coverage.price')
                        ->label('Covertura')
                        ->alignCenter()
                        ->numeric()
                        ->badge()
                        ->color('success')
                        ->suffix(' US$')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('payment_frequency')
                        ->label('Frecuencia de pago')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('family_members')
                        ->label('Poblacion')
                        ->alignCenter()
                        ->suffix(' persona(s)')
                        ->badge()
                        ->sortable()
                        ->color(function (mixed $state): string {
                            if ($state > 0) {
                                return 'warning';
                            }

                            return 'danger';
                        })
                        ->searchable(),
                    TextColumn::make('fee_anual')
                        ->label('Tarifa Anual')
                        ->alignCenter()
                        ->money()
                        ->badge()
                        ->sortable()
                        ->color(function (mixed $state): string {
                            if ($state > 0) {
                                return 'warning';
                            }

                            return 'danger';
                        })
                        ->searchable(),
                    // total_amount
                    TextColumn::make('total_amount')
                        ->label('Total a Pagar')
                        ->alignCenter()
                        ->money()
                        ->badge()
                        ->sortable()
                        ->color(function (mixed $state): string {
                            if ($state > 0) {
                                return 'warning';
                            }

                            return 'danger';
                        })
                        ->searchable(),
                    TextColumn::make('businessUnit.definition')
                        ->label('Unidad de Negocio')
                        ->badge()
                        ->sortable()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('businessLine.definition')
                        ->label('Linea de Servicio')
                        ->badge()
                        ->sortable()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('service_providers')
                        ->label('Proveedor(es) de Servicio')
                        ->badge()
                        ->sortable()
                        ->color('success')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Titular', [
                    TextColumn::make('full_name_ti')
                        ->label('Nombre titular')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                        ->sortable()
                        ->color('azulOscuro')
                        ->searchable(),
                    TextColumn::make('nro_identificacion_ti')
                        ->label('CI. titular')
                        ->badge()
                        ->sortable()
                        ->color('azulOscuro')
                        ->searchable(),
                    TextColumn::make('sex_ti')
                        ->label('Sexo')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('birth_date_ti')
                        ->label('Fecha de nacimiento')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('phone_ti')
                        ->label('Telefono titular')
                        ->sortable()
                        ->icon('heroicon-m-phone')
                        ->searchable(),
                    TextColumn::make('email_ti')
                        ->label('Email titular')
                        ->sortable()
                        ->icon('fontisto-email')
                        ->searchable(),
                    TextColumn::make('adress_ti')
                        ->label('Direccion')
                        ->icon('fontisto-map-marker-alt')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('city.definition')
                        ->sortable()
                        ->label('Ciudad')
                        ->searchable(),
                    TextColumn::make('state.definition')
                        ->sortable()
                        ->label('Estado')
                        ->searchable(),
                    TextColumn::make('region_ti')
                        ->sortable()
                        ->label('Region')
                        ->searchable(),
                    TextColumn::make('country.name')
                        ->sortable()
                        ->label('País')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Tomador', [
                    TextColumn::make('full_name_payer')
                        ->label('Nombre y Apellido')
                        ->badge()
                        ->alignCenter()
                        ->color('azulOscuro')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('nro_identificacion_payer')
                        ->label('Numero de Identificación')
                        ->badge()
                        ->alignCenter()
                        ->sortable()
                        ->color('azulOscuro')
                        ->searchable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Auditoría', [
                    TextColumn::make('created_by')
                        ->label('Creado por')
                        ->sortable()
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('activated_at')
                        ->sortable()
                        ->label('Fecha de emisión')
                        ->color('warning')
                        ->icon(Heroicon::OutlinedCalendar)
                        ->badge()
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('effective_date')
                        ->sortable()
                        ->label('Vigencia')
                        ->color('success')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->badge()
                        ->searchable()
                        ->toggleable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
            ])
            ->recordClasses(fn (Affiliation $record): array => self::recordRowClasses($record))
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'ACTIVA' => 'Activa',
                        'PENDIENTE' => 'Pendiente',
                        'PRE-APROBADA' => 'Pre-aprobada',
                        'EXCLUIDO' => 'Excluida',
                    ])
                    ->multiple(),
                Filter::make('created_at')
                    ->label('Fecha de registro')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
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
                    EditAction::make()
                        ->label('Editar')
                        ->color('warning')
                        ->icon('heroicon-o-pencil-square'),

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
                            $authUser = Auth::user();
                            try {

                                $upload = AffiliationController::uploadPayment($record, $data, 'AGENTE');

                                if ($upload) {
                                    SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_PAYMENT_VOUCHER_UPLOADED', 'administration.affiliations.upload-payment', [
                                        'panel' => 'administration',
                                        'affiliation_id' => $record->id,
                                        'affiliation_code' => $record->code,
                                        'status' => $record->status,
                                        'payment_method' => $data['payment_method'] ?? null,
                                        'voucher_date' => $data['date_payment_voucher'] ?? null,
                                        'uploaded_by' => $authUser?->name,
                                    ], $authUser);

                                    Notification::make()
                                        ->title('NOTIFICACION')
                                        ->body('El comprobante de pago se ha registrado con exito')
                                        ->icon('heroicon-m-user-plus')
                                        ->iconColor('success')
                                        ->success()
                                        ->seconds(5)
                                        ->send();

                                    redirect()->to(AffiliationResource::getUrl('edit', ['record' => $record->id], panel: 'administration').'?relation=1');

                                    // Notificacion para Admin
                                    $recipient = User::where('is_admin', 1)->get();
                                    foreach ($recipient as $user) {
                                        $recipient_for_user = User::find($user->id);
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
                                            ->sendToDatabase($recipient);
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

                                    return;
                                }

                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_PAYMENT_VOUCHER_UPLOAD_FAILED', 'administration.affiliations.upload-payment', [
                                    'panel' => 'administration',
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'status' => $record->status,
                                    'payment_method' => $data['payment_method'] ?? null,
                                    'reason' => 'controller_returned_false',
                                    'uploaded_by' => $authUser?->name,
                                ], $authUser);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_PAYMENT_VOUCHER_UPLOAD_FAILED', 'administration.affiliations.upload-payment', [
                                    'panel' => 'administration',
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'status' => $record->status,
                                    'payment_method' => $data['payment_method'] ?? null,
                                    'uploaded_by' => $authUser?->name,
                                    'error_message' => $th->getMessage(),
                                    'error_class' => $th::class,
                                    'error_file' => $th->getFile(),
                                    'error_line' => $th->getLine(),
                                ], $authUser);

                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-m-user-plus')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();

                                return;
                            }

                        }),

                    Action::make('change_status')
                        ->label('Actualizar estatus')
                        ->color('azulOscuro')
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
                                                'PENDIENTE' => 'PENDIENTE',
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
                            $authUser = Auth::user();
                            try {
                                if ($data['action'] == 'observation') {
                                    $record->status_log_affiliations()->create([
                                        'affiliation_id' => $record->id,
                                        'action' => 'AGREGO OBSERVACION',
                                        'observation' => $data['description'],
                                        'updated_by' => Auth::user()->name,
                                    ]);
                                    Notification::make()
                                        ->title('AFILIACION ACTUALIZADA')
                                        ->success()
                                        ->send();

                                    SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_STATUS_UPDATED', 'administration.affiliations.change-status', [
                                        'panel' => 'administration',
                                        'affiliation_id' => $record->id,
                                        'affiliation_code' => $record->code,
                                        'action_type' => 'observation',
                                        'status' => $record->status,
                                        'description' => $data['description'] ?? null,
                                        'updated_by' => $authUser?->name,
                                    ], $authUser);

                                    return;
                                }

                                if ($data['action'] == 'status') {
                                    $previousStatus = $record->status;
                                    $record->update([
                                        'status' => $data['status'],
                                    ]);
                                    $record->status_log_affiliations()->create([
                                        'affiliation_id' => $record->id,
                                        'action' => 'CAMBIO ESTATUS A: '.$data['status'],
                                        'observation' => $data['description'],
                                        'updated_by' => Auth::user()->name,
                                    ]);
                                    Notification::make()
                                        ->title('AFILIACION ACTUALIZADA')
                                        ->success()
                                        ->send();

                                    SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_STATUS_UPDATED', 'administration.affiliations.change-status', [
                                        'panel' => 'administration',
                                        'affiliation_id' => $record->id,
                                        'affiliation_code' => $record->code,
                                        'action_type' => 'status',
                                        'old_status' => $previousStatus,
                                        'new_status' => $data['status'],
                                        'description' => $data['description'] ?? null,
                                        'updated_by' => $authUser?->name,
                                    ], $authUser);

                                    return;
                                }

                                if ($data['action'] == 'exclude') {
                                    $previousStatus = $record->status;
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
                                    Notification::make()
                                        ->title('AFILIACION ACTUALIZADA')
                                        ->success()
                                        ->send();

                                    SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_STATUS_UPDATED', 'administration.affiliations.change-status', [
                                        'panel' => 'administration',
                                        'affiliation_id' => $record->id,
                                        'affiliation_code' => $record->code,
                                        'action_type' => 'exclude',
                                        'old_status' => $previousStatus,
                                        'new_status' => 'EXCLUIDO',
                                        'date_egress' => $data['date_egress'] ?? null,
                                        'description' => $data['description'] ?? null,
                                        'updated_by' => $authUser?->name,
                                    ], $authUser);

                                    return;
                                }

                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_STATUS_UPDATE_SKIPPED', 'administration.affiliations.change-status', [
                                    'panel' => 'administration',
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'action_type' => $data['action'] ?? null,
                                    'reason' => 'unsupported_action',
                                    'updated_by' => $authUser?->name,
                                ], $authUser);

                                Notification::make()
                                    ->title('AFILIACION ACTUALIZADA')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_STATUS_UPDATE_FAILED', 'administration.affiliations.change-status', [
                                    'panel' => 'administration',
                                    'affiliation_id' => $record->id,
                                    'affiliation_code' => $record->code,
                                    'action_type' => $data['action'] ?? null,
                                    'updated_by' => $authUser?->name,
                                    'error_message' => $th->getMessage(),
                                    'error_class' => $th::class,
                                    'error_file' => $th->getFile(),
                                    'error_line' => $th->getLine(),
                                ], $authUser);

                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),

                ])
                    ->label('Acciones')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->button()
                    ->color('gray')
                    ->hidden(fn ($record) => $record->status == 'EXCLUIDO'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
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
                            ], 'administration');

                            return redirect()->route('administration.affiliations.export-csv', ['token' => $token]);
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
                            ], 'administration');

                            return redirect()->route('administration.affiliates.export-csv', ['token' => $token]);
                        }),
                    DeleteBulkAction::make(),
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
                                        Hidden::make('base_total_amount')
                                            ->default(fn (): float => (float) array_sum(array_column(session()->get('data', []), 'total_amount')))
                                            ->dehydrated(),
                                        ...self::bcvRateManualStateHiddenFields(),
                                        Grid::make(['default' => 1, 'md' => 2])->schema([
                                            TextInput::make('total_amount')
                                                ->label('Total a pagar')
                                                ->prefix('US$')
                                                ->default(fn (): float => (float) array_sum(array_column(session()->get('data', []), 'total_amount')))
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

                                        /* PAGO EN DOLARES - LINK DE PAGO */
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
                            ];
                        })
                        ->action(function (Collection $records, array $data) {
                            $authUser = Auth::user();
                            try {
                                $recordIds = $records->pluck('id')->values()->all();
                                $recordCodes = $records->pluck('code')->values()->all();
                                $totalAmount = (float) $records->sum('total_amount');

                                $upload = AffiliationController::uploadPaymentMultipleAffiliations($records, $data, 'AGENTE');

                                if ($upload) {
                                    SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_BULK_PAYMENT_VOUCHER_UPLOADED', 'administration.affiliations.bulk-upload-payment', [
                                        'panel' => 'administration',
                                        'affiliation_ids' => $recordIds,
                                        'affiliation_codes' => $recordCodes,
                                        'records_count' => count($recordIds),
                                        'sum_total_amount' => $totalAmount,
                                        'payment_method' => $data['payment_method'] ?? null,
                                        'voucher_date' => $data['date_payment_voucher'] ?? null,
                                        'uploaded_by' => $authUser?->name,
                                    ], $authUser);

                                    Notification::make()
                                        ->title('NOTIFICACION')
                                        ->body('El comprobante de pago se ha registrado con exito')
                                        ->icon('heroicon-m-user-plus')
                                        ->iconColor('success')
                                        ->success()
                                        ->seconds(5)
                                        ->send();

                                    return;
                                }

                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_BULK_PAYMENT_VOUCHER_UPLOAD_FAILED', 'administration.affiliations.bulk-upload-payment', [
                                    'panel' => 'administration',
                                    'affiliation_ids' => $recordIds,
                                    'affiliation_codes' => $recordCodes,
                                    'records_count' => count($recordIds),
                                    'sum_total_amount' => $totalAmount,
                                    'payment_method' => $data['payment_method'] ?? null,
                                    'reason' => 'controller_returned_false',
                                    'uploaded_by' => $authUser?->name,
                                ], $authUser);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_BULK_PAYMENT_VOUCHER_UPLOAD_FAILED', 'administration.affiliations.bulk-upload-payment', [
                                    'panel' => 'administration',
                                    'affiliation_ids' => $records->pluck('id')->values()->all(),
                                    'affiliation_codes' => $records->pluck('code')->values()->all(),
                                    'records_count' => $records->count(),
                                    'payment_method' => $data['payment_method'] ?? null,
                                    'uploaded_by' => $authUser?->name,
                                    'error_message' => $th->getMessage(),
                                    'error_class' => $th::class,
                                    'error_file' => $th->getFile(),
                                    'error_line' => $th->getLine(),
                                ], $authUser);

                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-m-user-plus')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
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

    private static function syncBcvRateManualFlag(Get $get, Set $set, mixed $state): void
    {
        $calculated = $get('tasa_bcv_calculated');

        if ($calculated === null || $calculated === '') {
            return;
        }

        $set('tasa_bcv_manual', ! self::bcvRatesMatch($state, $calculated));
    }

    private static function bcvRatesMatch(mixed $a, mixed $b): bool
    {
        if (! is_numeric($a) || ! is_numeric($b)) {
            return false;
        }

        return round((float) $a, 6) === round((float) $b, 6);
    }

    private static function statusColor(?string $state): string
    {
        return match (strtoupper(trim((string) $state))) {
            'ACTIVA', 'PRE-APROBADA' => 'success',
            'PENDIENTE' => 'warning',
            'EXCLUIDO' => 'danger',
            default => 'gray',
        };
    }

    private static function statusIcon(?string $state): Heroicon
    {
        return match (strtoupper(trim((string) $state))) {
            'ACTIVA' => Heroicon::OutlinedCheckCircle,
            'PRE-APROBADA' => Heroicon::OutlinedInformationCircle,
            'PENDIENTE' => Heroicon::OutlinedExclamationCircle,
            'EXCLUIDO' => Heroicon::OutlinedXCircle,
            default => Heroicon::OutlinedMinusCircle,
        };
    }

    private static function statusLabel(?string $state): string
    {
        return match (strtoupper(trim((string) $state))) {
            'ACTIVA' => 'Activa',
            'PRE-APROBADA' => 'Pre-aprobada',
            'PENDIENTE' => 'Pendiente',
            'EXCLUIDO' => 'Excluida',
            default => (string) ($state ?? '—'),
        };
    }

    /**
     * @return list<string>
     */
    private static function recordRowClasses(Affiliation $record): array
    {
        return match (strtoupper(trim((string) $record->status))) {
            'EXCLUIDO' => ['bg-red-50/80 dark:bg-red-950/20 border-l-4 border-red-500'],
            'PENDIENTE' => ['bg-amber-50/70 dark:bg-amber-950/20 border-l-4 border-amber-400'],
            'PRE-APROBADA' => ['bg-sky-50/60 dark:bg-sky-950/20 border-l-4 border-sky-400'],
            'ACTIVA' => ['border-l-4 border-emerald-400/80'],
            default => ['border-l-4 border-transparent'],
        };
    }
}
