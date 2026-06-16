<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporates\Tables;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Http\Controllers\AffiliationCorporateController;
use App\Models\AffiliationCorporate;
use App\Support\AffiliationCorporateRifLabel;
use App\Support\AffiliationPaymentBcvRateCalculator;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
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
use Illuminate\Support\Facades\Auth;

class AffiliationCorporatesTable
{
    private const COLUMN_GROUP_HEADER_CLASS = '[&_th]:bg-gradient-to-r [&_th]:from-slate-100/95 [&_th]:via-slate-50/90 [&_th]:to-transparent dark:[&_th]:from-white/[0.08] dark:[&_th]:via-white/[0.04] dark:[&_th]:to-transparent [&_th]:font-semibold [&_th]:text-slate-800 dark:[&_th]:text-slate-100 [&_th]:border-b [&_th]:border-slate-200/80 dark:[&_th]:border-white/10';

    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todas')
                ->icon(Heroicon::OutlinedBuildingOffice2),
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
                $base = AffiliationCorporate::query()->with([
                    'agency',
                    'agent',
                    'accountManager',
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
            ->heading('Afiliaciones corporativas')
            ->description('Cliente corporativo, montos, ILS y estatus. Use pestañas y filtros para priorizar la gestión.')
            ->striped()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->recordTitleAttribute('code')
            ->emptyStateHeading('Sin afiliaciones corporativas')
            ->emptyStateDescription('No hay registros o no coinciden con la búsqueda y los filtros aplicados.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->recordUrl(fn (AffiliationCorporate $record): string => AffiliationCorporateResource::getUrl('view', ['record' => $record]))
            ->columns([
                ColumnGroup::make('Resumen', [
                    TextColumn::make('status')
                        ->label('Estatus')
                        ->badge()
                        ->color(fn (?string $state): string => self::statusColor($state))
                        ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                        ->searchable()
                        ->sortable()
                        ->icon(fn (?string $state): Heroicon => self::statusIcon($state)),
                    TextColumn::make('code')
                        ->label('Código')
                        ->badge()
                        ->color('azulOscuro')
                        ->searchable()
                        ->sortable()
                        ->copyable()
                        ->copyMessage('Código copiado')
                        ->tooltip('Clic para ver ficha corporativa')
                        ->extraAttributes(['class' => 'cursor-pointer']),
                    TextColumn::make('name_corporate')
                        ->label('Cliente corporativo')
                        ->badge()
                        ->color('azulOscuro')
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : '—')
                        ->sortable()
                        ->searchable()
                        ->wrap(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Estructura comercial', [
                    TextColumn::make('agency.name_corporative')
                        ->label('Agencia')
                        ->badge()
                        ->color('azulOscuro')
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : '—')
                        ->sortable()
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('agent.name')
                        ->label('Agente')
                        ->badge()
                        ->color('azulOscuro')
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : '—')
                        ->sortable()
                        ->icon(Heroicon::OutlinedUser)
                        ->searchable()
                        ->toggleable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Plan y montos', [
                    TextColumn::make('payment_frequency')
                        ->label('Frecuencia de pago')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('poblation')
                        ->label('Población')
                        ->alignCenter()
                        ->suffix(' persona(s)')
                        ->badge()
                        ->color(function (mixed $state): string {
                            if ($state > 0) {
                                return 'warning';
                            }

                            return 'danger';
                        })
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('fee_anual')
                        ->label('Tarifa Anual')
                        ->alignCenter()
                        ->money()
                        ->badge()
                        ->color(function (mixed $state): string {
                            if ($state > 0) {
                                return 'warning';
                            }

                            return 'danger';
                        })
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('total_amount')
                        ->label('Total a Pagar')
                        ->alignCenter()
                        ->money()
                        ->badge()
                        ->color(function (mixed $state): string {
                            if ($state > 0) {
                                return 'warning';
                            }

                            return 'danger';
                        })
                        ->sortable()
                        ->searchable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Contratante', [
                    TextColumn::make('rif')
                        ->label('RIF')
                        ->formatStateUsing(fn (?string $state): string => AffiliationCorporateRifLabel::withJPrefix($state))
                        ->badge()
                        ->color('verde')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('email')
                        ->label('Email')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->sortable()
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('phone')
                        ->label('Teléfono')
                        ->icon(Heroicon::OutlinedPhone)
                        ->sortable()
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('address')
                        ->label('Dirección')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->sortable()
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('city.definition')
                        ->label('Ciudad')
                        ->sortable()
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('state.definition')
                        ->label('Estado')
                        ->sortable()
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('country.name')
                        ->label('País')
                        ->sortable()
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('ILS', [
                    TextColumn::make('vaucher_ils')
                        ->label('Voucher ILS')
                        ->badge()
                        ->alignCenter()
                        ->color('success')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('date_payment_initial_ils')
                        ->label('ago ILS Desde')
                        ->badge()
                        ->alignCenter()
                        ->color('success')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('date_payment_final_ils')
                        ->label('Pago ILS Hasta')
                        ->badge()
                        ->alignCenter()
                        ->color('success')
                        ->sortable()
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Auditoría', [
                    TextColumn::make('created_by')
                        ->label('Creado por')
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                        ->sortable()
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('activated_at')
                        ->label('Fecha de emisión')
                        ->color('warning')
                        ->icon(Heroicon::OutlinedCalendar)
                        ->badge()
                        ->sortable()
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('effective_date')
                        ->label('Vigencia')
                        ->color('success')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->badge()
                        ->searchable()
                        ->sortable()
                        ->toggleable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
            ])
            ->recordClasses(fn (AffiliationCorporate $record): array => self::recordRowClasses($record))
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
                            $indicators['desde'] = 'Registro desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Registro hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon(Heroicon::OutlinedFunnel),
            )
            ->recordActions([
                ActionGroup::make([
                    Action::make('upload_info_ils')
                        ->label('Vaucher ILS')
                        ->color('warning')
                        ->icon('heroicon-o-paper-clip')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ExtraLarge)
                        ->modalHeading('Activar afiliacion')
                        ->form([
                            Section::make('ACTIVAR AFILIACIÓN')
                                ->description('Formulario de activación de afiliación. Campo Requerido(*)')
                                ->icon('heroicon-s-check-circle')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('vaucher_ils')
                                            ->label('Vaucher ILS')
                                            ->required(),
                                    ]),
                                    Grid::make(2)->schema([
                                        DatePicker::make('date_payment_initial_ils')
                                            ->label('Desde')
                                            ->format('d-m-Y')
                                            ->required(),
                                        DatePicker::make('date_payment_final_ils')
                                            ->label('Hasta')
                                            ->format('d-m-Y')
                                            ->required(),

                                    ]),
                                    Grid::make(1)->schema([
                                        FileUpload::make('document_ils')
                                            ->label('Documento/Comprobante ILS')
                                            ->required(),
                                    ]),
                                ]),
                        ])
                        ->action(function (AffiliationCorporate $record, array $data): void {
                            $authUser = Auth::user();

                            try {
                                $record->update([
                                    'vaucher_ils' => $data['vaucher_ils'],
                                    'date_payment_initial_ils' => $data['date_payment_initial_ils'],
                                    'date_payment_final_ils' => $data['date_payment_final_ils'],
                                    'document_ils' => $data['document_ils'],
                                ]);

                                $record->status_log_corporate_affiliations()->create([
                                    'affiliation_corporate_id' => $record->id,
                                    'action' => 'ACTIVACIÓN',
                                    'observation' => 'AFILIACIÓN ACTIVADA. FECHA: '.now()->format('d-m-Y'),
                                    'updated_by' => Auth::user()->name,
                                ]);

                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_ILS_VOUCHER_UPLOADED', 'administration.affiliation-corporates.upload-ils', [
                                    'panel' => 'administration',
                                    'affiliation_corporate_id' => $record->id,
                                    'affiliation_corporate_code' => $record->code,
                                    'voucher_ils' => $data['vaucher_ils'] ?? null,
                                    'date_initial' => $data['date_payment_initial_ils'] ?? null,
                                    'date_final' => $data['date_payment_final_ils'] ?? null,
                                    'uploaded_by' => $authUser?->name,
                                ], $authUser);

                                Notification::make()
                                    ->success()
                                    ->title('AFILIACIÓN ACTIVADA')
                                    ->send();
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_ILS_VOUCHER_UPLOAD_FAILED', 'administration.affiliation-corporates.upload-ils', [
                                    'panel' => 'administration',
                                    'affiliation_corporate_id' => $record->id,
                                    'affiliation_corporate_code' => $record->code,
                                    'voucher_ils' => $data['vaucher_ils'] ?? null,
                                    'uploaded_by' => $authUser?->name,
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
                        })
                        ->hidden(function (AffiliationCorporate $record): bool {
                            if ($record->vaucher_ils != null) {
                                return true;
                            }

                            return false;
                        }),

                    Action::make('upload')
                        ->label('Comprobante de Pago')
                        ->color('azul')
                        ->icon('heroicon-s-cloud-arrow-up')
                        ->modalWidth(Width::FourExtraLarge)
                        ->form([

                            /** INFORMACION PRINCIPAL */
                            Fieldset::make('INFORMACION PRINCIPAL')
                                ->schema([
                                    ...self::bcvRateManualStateHiddenFields(),
                                    Grid::make(2)->schema([
                                        TextInput::make('total_amount')
                                            ->label('Total a pagar')
                                            ->prefix('US$')
                                            ->default(function ($state, $set, Get $get, AffiliationCorporate $record) {

                                                $amount = $record->total_amount;

                                                return $amount;
                                            })
                                            ->numeric()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                                self::syncPaymentBcvRateFromTotal($get, $set, $state);
                                            }),
                                        DatePicker::make('date_payment_voucher')
                                            ->label('Fecha del Comprobante de Pago')
                                            ->required()
                                            ->format('d/m/Y'),
                                    ])->columnSpanFull(),
                                ])->columnSpanFull(),

                            /**FORMA DE PAGO */
                            Fieldset::make('FORMA DE PAGO')
                                ->schema([

                                    /**SELECCION DEL METODO DE PAGO */
                                    Grid::make()
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
                        ->action(function (AffiliationCorporate $record, array $data): void {
                            $authUser = Auth::user();
                            try {

                                $upload = AffiliationCorporateController::uploadPayment($record, $data, 'AGENTE');

                                if ($upload) {
                                    SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_PAYMENT_VOUCHER_UPLOADED', 'administration.affiliation-corporates.upload-payment', [
                                        'panel' => 'administration',
                                        'affiliation_corporate_id' => $record->id,
                                        'affiliation_corporate_code' => $record->code,
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

                                    redirect()->to(AffiliationCorporateResource::getUrl('edit', ['record' => $record->id], panel: 'administration').'?relation=2');

                                    return;
                                }

                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_PAYMENT_VOUCHER_UPLOAD_FAILED', 'administration.affiliation-corporates.upload-payment', [
                                    'panel' => 'administration',
                                    'affiliation_corporate_id' => $record->id,
                                    'affiliation_corporate_code' => $record->code,
                                    'status' => $record->status,
                                    'payment_method' => $data['payment_method'] ?? null,
                                    'reason' => 'controller_returned_false',
                                    'uploaded_by' => $authUser?->name,
                                ], $authUser);
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_PAYMENT_VOUCHER_UPLOAD_FAILED', 'administration.affiliation-corporates.upload-payment', [
                                    'panel' => 'administration',
                                    'affiliation_corporate_id' => $record->id,
                                    'affiliation_corporate_code' => $record->code,
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
                                    ->seconds(5)
                                    ->send();
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
                        ->action(function (AffiliationCorporate $record, array $data): void {
                            $authUser = Auth::user();
                            try {
                                if ($data['action'] == 'observation') {
                                    $record->status_log_corporate_affiliations()->create([
                                        'affiliation_corporate_id' => $record->id,
                                        'action' => 'AGREGO OBSERVACION',
                                        'observation' => $data['description'],
                                        'updated_by' => Auth::user()->name,
                                    ]);
                                    Notification::make()
                                        ->title('AFILIACION ACTUALIZADA')
                                        ->success()
                                        ->send();

                                    SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_STATUS_UPDATED', 'administration.affiliation-corporates.change-status', [
                                        'panel' => 'administration',
                                        'affiliation_corporate_id' => $record->id,
                                        'affiliation_corporate_code' => $record->code,
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
                                    $record->status_log_corporate_affiliations()->create([
                                        'affiliation_corporate_id' => $record->id,
                                        'action' => 'CAMBIO ESTATUS A: '.$data['status'],
                                        'observation' => $data['description'],
                                        'updated_by' => Auth::user()->name,
                                    ]);
                                    Notification::make()
                                        ->title('AFILIACION ACTUALIZADA')
                                        ->success()
                                        ->send();

                                    SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_STATUS_UPDATED', 'administration.affiliation-corporates.change-status', [
                                        'panel' => 'administration',
                                        'affiliation_corporate_id' => $record->id,
                                        'affiliation_corporate_code' => $record->code,
                                        'action_type' => 'status',
                                        'old_status' => $previousStatus,
                                        'new_status' => $data['status'],
                                        'description' => $data['description'] ?? null,
                                        'updated_by' => $authUser?->name,
                                    ], $authUser);

                                    return;
                                }

                                if ($data['action'] == 'exclude') {
                                    // dd($data, $record);
                                    $previousStatus = $record->status;
                                    $record->update([
                                        'status' => 'EXCLUIDO',
                                        'fee_anual' => 0.0,
                                        'activated_at' => null,
                                        'total_amount' => 0.0,
                                        'poblation' => 0,
                                    ]);
                                    $record->corporateAffiliates()->update([
                                        'status' => 'EXCLUIDO',
                                    ]);
                                    $record->status_log_corporate_affiliations()->create([
                                        'affiliation_corporate_id' => $record->id,
                                        'action' => 'EXCLUYO AFILIACION, FECHA DE EGRESO: '.$data['date_egress'],
                                        'observation' => $data['description'],
                                        'updated_by' => Auth::user()->name,
                                    ]);
                                    Notification::make()
                                        ->title('AFILIACION ACTUALIZADA')
                                        ->success()
                                        ->send();

                                    SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_STATUS_UPDATED', 'administration.affiliation-corporates.change-status', [
                                        'panel' => 'administration',
                                        'affiliation_corporate_id' => $record->id,
                                        'affiliation_corporate_code' => $record->code,
                                        'action_type' => 'exclude',
                                        'old_status' => $previousStatus,
                                        'new_status' => 'EXCLUIDO',
                                        'date_egress' => $data['date_egress'] ?? null,
                                        'description' => $data['description'] ?? null,
                                        'updated_by' => $authUser?->name,
                                    ], $authUser);

                                    return;
                                }

                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_STATUS_UPDATE_SKIPPED', 'administration.affiliation-corporates.change-status', [
                                    'panel' => 'administration',
                                    'affiliation_corporate_id' => $record->id,
                                    'affiliation_corporate_code' => $record->code,
                                    'action_type' => $data['action'] ?? null,
                                    'reason' => 'unsupported_action',
                                    'updated_by' => $authUser?->name,
                                ], $authUser);

                                Notification::make()
                                    ->title('AFILIACION ACTUALIZADA')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $th) {
                                SecurityAudit::log('AUDIT_ADMIN_AFFILIATION_CORPORATE_STATUS_UPDATE_FAILED', 'administration.affiliation-corporates.change-status', [
                                    'panel' => 'administration',
                                    'affiliation_corporate_id' => $record->id,
                                    'affiliation_corporate_code' => $record->code,
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
                    DeleteBulkAction::make(),
                ]),
            ]);
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
    private static function recordRowClasses(AffiliationCorporate $record): array
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
