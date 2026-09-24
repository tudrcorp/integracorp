<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Enums\FormaPago;
use App\Enums\StatusPago;
use App\Enums\StatusVaucher;
use App\Filament\Administration\Resources\TdevReports\Actions\TdevReportPaymentModalActions;
use App\Filament\Administration\Resources\TdevReports\TdevReportResource;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Http\Controllers\LogController;
use App\Models\TdevReport;
use App\Services\TdevReports\TdevReportCommissionFromPercentageUpdater;
use App\Services\TdevReports\TdevReportVaucherStatusUpdater;
use App\Support\Filament\FilamentIosButton;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use UnitEnum;

class CompensacionVaucher extends Page implements HasTable
{
    use AuthorizesDepartmentNavigation;
    use InteractsWithTable;

    /**
     * Tope de vouchers por búsqueda: todos los formularios actualizan en bloque lo encontrado,
     * así que un término demasiado amplio no debe arrastrar media tabla.
     */
    public const MAX_RESULTS = 200;

    public const MIN_SEARCH_LENGTH = 3;

    protected static string|UnitEnum|null $navigationGroup = 'COMPENSACION TDEV';

    protected static ?string $navigationLabel = 'Compensacion de Vaucher';

    protected static ?string $title = 'Compensación de voucher';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed>|null */
    public ?array $searchData = [];

    /** @var array<string, mixed>|null */
    public ?array $paymentData = [];

    /** @var array<string, mixed>|null */
    public ?array $statusData = [];

    /** @var array<string, mixed>|null */
    public ?array $commissionData = [];

    /**
     * Bloqueado: los guardados en bloque actualizan exactamente estos ids, así que el cliente no puede alterarlos.
     *
     * @var array<int>
     */
    #[Locked]
    public array $resultRecordIds = [];

    #[Locked]
    public string $lastSearchTerm = '';

    #[Locked]
    public float $resultTotalMontoPvp = 0.0;

    #[Locked]
    public float $resultTotalMontoComision = 0.0;

    /** Suma de (precio_upgrade + PVP): base sobre la que se calcula la comisión. */
    #[Locked]
    public float $resultTotalBaseComision = 0.0;

    #[Locked]
    public int $resultPaidCount = 0;

    #[Locked]
    public int $resultPendingCount = 0;

    #[Locked]
    public int $resultAnnulledCount = 0;

    #[Locked]
    public int $resultVoucherCount = 0;

    #[Locked]
    public int $resultAgencyCount = 0;

    #[Locked]
    public float $resultTotalUpgrade = 0.0;

    #[Locked]
    public bool $paymentDataIsMixed = false;

    /** Parámetro de la URL con el que otras pantallas abren la compensación de un voucher concreto. */
    public const VOUCHER_QUERY_PARAMETER = 'vaucher';

    public function mount(): void
    {
        $this->searchForm->fill();
        $this->fillActionForms(null);

        $linkedVoucher = request()->query(self::VOUCHER_QUERY_PARAMETER);
        if (! is_string($linkedVoucher)) {
            return;
        }

        $linkedVoucher = trim($linkedVoucher);
        $length = mb_strlen($linkedVoucher);
        if ($length < self::MIN_SEARCH_LENGTH || $length > 100) {
            return;
        }

        $this->searchForm->fill(['term' => $linkedVoucher]);
        $this->runSearch($linkedVoucher, exact: true);
    }

    /**
     * URL que abre la página con el voucher ya buscado (coincidencia exacta).
     */
    public static function getUrlForVoucher(string $voucher): string
    {
        return static::getUrl([self::VOUCHER_QUERY_PARAMETER => $voucher], panel: 'administration');
    }

    public function getHeading(): string|Htmlable
    {
        return new HtmlString(
            <<<'HTML'
            <div class="flex flex-col items-start gap-3 py-1">
                <img src="/image/logo-tdev.png" alt="Tu Doctor En Viajes" class="h-16 w-auto max-w-[12rem] object-contain drop-shadow-md sm:h-20 sm:max-w-[14rem]">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-cyan-700 dark:text-cyan-300">
                        Tu Doctor En Viajes
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                        Compensación de voucher
                    </h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Busque un voucher y actualice en bloque el pago, el estatus o la comisión de todos los registros encontrados.
                    </p>
                </div>
            </div>
            HTML
        );
    }

    public function searchForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('term')
                    ->hiddenLabel()
                    ->placeholder('Número de voucher, ej. TD-0J1EM0')
                    ->prefixIcon(Heroicon::MagnifyingGlass)
                    ->autocomplete(false)
                    ->autofocus()
                    ->required()
                    ->minLength(self::MIN_SEARCH_LENGTH)
                    ->maxLength(100)
                    ->validationMessages([
                        'required' => 'Escriba un número de voucher para buscar.',
                        'min' => 'Escriba al menos '.self::MIN_SEARCH_LENGTH.' caracteres.',
                    ]),
            ])
            ->statePath('searchData');
    }

    public function paymentForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        FileUpload::make('comprobante_pago')
                            ->label('Comprobante de pago')
                            ->disk('public')
                            ->directory('tdev-reports/compensacion-vaucher/comprobantes')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'])
                            ->imagePreviewHeight('140')
                            ->downloadable()
                            ->openable()
                            ->helperText('JPG, PNG, WebP, GIF o PDF, hasta 5 MB. Al cargar uno nuevo, el pago pasa a «Pagado».')
                            ->columnSpan(['default' => 1, 'lg' => 1]),
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                Select::make('forma_pago')
                                    ->label('Forma de pago')
                                    ->prefixIcon(Heroicon::OutlinedCreditCard)
                                    ->options(FormaPago::options())
                                    ->native(false)
                                    ->placeholder('Seleccione…'),
                                Select::make('estatus_pago')
                                    ->label('Estatus de pago')
                                    ->prefixIcon(Heroicon::OutlinedCheckBadge)
                                    ->options(StatusPago::options())
                                    ->native(false)
                                    ->placeholder('Seleccione…'),
                                TextInput::make('entidad_bancaria_receptora')
                                    ->label('Entidad bancaria receptora')
                                    ->prefixIcon(Heroicon::OutlinedBuildingLibrary)
                                    ->maxLength(255),
                                TextInput::make('referencia_bancaria_pago_vaucher_credito')
                                    ->label('Referencia bancaria')
                                    ->prefixIcon(Heroicon::OutlinedHashtag)
                                    ->maxLength(255),
                                TextInput::make('tasa_bcv')
                                    ->label('Tasa BCV')
                                    ->prefixIcon(Heroicon::OutlinedChartBar)
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.0001')
                                    ->live(debounce: 500),
                                TextInput::make('monto_abonado_en_cuenta_vaucher_credito')
                                    ->label('Monto abonado')
                                    ->prefix('VES')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->helperText(fn (Get $get): ?string => $this->suggestedAmountVes($get('tasa_bcv')) !== null
                                        ? 'Sugerido: VES '.self::formatNumber((float) $this->suggestedAmountVes($get('tasa_bcv'))).' (total PVP × tasa BCV).'
                                        : 'Indique la tasa BCV para ver el monto sugerido.')
                                    ->suffixAction(
                                        Action::make('useSuggestedAmount')
                                            ->icon(Heroicon::OutlinedCalculator)
                                            ->tooltip('Usar monto sugerido')
                                            ->visible(fn (Get $get): bool => $this->suggestedAmountVes($get('tasa_bcv')) !== null)
                                            ->action(fn (Get $get, Set $set) => $set(
                                                'monto_abonado_en_cuenta_vaucher_credito',
                                                $this->suggestedAmountVes($get('tasa_bcv')),
                                            )),
                                    ),
                                DatePicker::make('fecha_pago_vaucher_credito')
                                    ->label('Fecha de pago')
                                    ->prefixIcon(Heroicon::OutlinedCalendarDays)
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now()->endOfDay())
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 2]),
                    ]),
            ])
            ->statePath('paymentData');
    }

    public function statusForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('estatus_vaucher')
                    ->label('Nuevo estatus del voucher')
                    ->prefixIcon(Heroicon::OutlinedFlag)
                    ->options(StatusVaucher::options())
                    ->native(false)
                    ->required()
                    ->live(),
                Callout::make('Anular también anula el pago y la comisión')
                    ->description(fn (): string => 'Se afectarán '.count($this->resultRecordIds).' voucher(s). El motivo se añade al historial de observaciones con su nombre y la fecha.')
                    ->warning()
                    ->visible(fn (Get $get): bool => self::isAnulado($get('estatus_vaucher'))),
                RichEditor::make('observacion_anulacion')
                    ->label('Motivo de la anulación')
                    ->placeholder('Explique por qué se anulan estos vouchers…')
                    ->fileAttachments(false)
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike'],
                        ['bulletList', 'orderedList'],
                        ['undo', 'redo'],
                    ])
                    ->visible(fn (Get $get): bool => self::isAnulado($get('estatus_vaucher')))
                    ->required(fn (Get $get): bool => self::isAnulado($get('estatus_vaucher'))),
            ])
            ->statePath('statusData');
    }

    public function commissionForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('porcentaje_comision')
                            ->label('Porcentaje de comisión')
                            ->suffix('%')
                            ->prefixIcon(Heroicon::OutlinedReceiptPercent)
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step('0.0001')
                            ->live(debounce: 500)
                            ->helperText('Déjelo vacío para quitar la comisión.'),
                        TextEntry::make('commission_current')
                            ->label('Comisión actual')
                            ->state(fn (): string => self::formatUsd($this->resultTotalMontoComision)),
                        TextEntry::make('commission_projected')
                            ->label('Comisión con el nuevo porcentaje')
                            ->state(fn (Get $get): string => self::formatUsd($this->projectedCommission($get('porcentaje_comision'))))
                            ->color('primary')
                            ->weight(FontWeight::SemiBold)
                            ->helperText('(Upgrade + PVP) × porcentaje, sumado en todos los vouchers.'),
                    ]),
            ])
            ->statePath('commissionData');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        Form::make([EmbeddedSchema::make('searchForm')])
                            ->id('compensacion-vaucher-search')
                            ->livewireSubmitHandler('searchVouchers')
                            ->footer([
                                Actions::make([
                                    Action::make('search')
                                        ->label('Buscar')
                                        ->icon(Heroicon::MagnifyingGlass)
                                        ->color('warning')
                                        ->submit('searchVouchers')
                                        ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('warning')]),
                                    $this->clearResultsAction(),
                                ]),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),

                EmptyState::make('Busque un voucher para comenzar')
                    ->description('Presione Enter o «Buscar». Verá los vouchers encontrados y podrá registrar el pago, cambiar el estatus o recalcular la comisión de todos a la vez.')
                    ->icon(Heroicon::OutlinedTicket)
                    ->visible(fn (): bool => ! $this->hasResults()),

                Grid::make(['default' => 2, 'lg' => 4])
                    ->schema([
                        $this->statCard(
                            'stat_passengers',
                            'Pasajeros',
                            Heroicon::OutlinedUsers,
                            'info',
                            fn (): string => (string) count($this->resultRecordIds),
                            fn (): string => $this->resultVoucherCount.' '.($this->resultVoucherCount === 1 ? 'voucher' : 'vouchers')
                                .' · '.$this->resultAgencyCount.' '.($this->resultAgencyCount === 1 ? 'agencia' : 'agencias'),
                        ),
                        $this->statCard(
                            'stat_pvp',
                            'Total PVP',
                            Heroicon::OutlinedCurrencyDollar,
                            'primary',
                            fn (): string => self::formatUsd($this->resultTotalMontoPvp),
                            fn (): string => 'Promedio '.self::formatUsd($this->averagePvp()).' por pasajero'
                                .($this->resultTotalUpgrade > 0 ? ' · upgrades '.self::formatUsd($this->resultTotalUpgrade) : ''),
                        ),
                        $this->statCard(
                            'stat_commission',
                            'Total comisión',
                            Heroicon::OutlinedReceiptPercent,
                            'success',
                            fn (): string => self::formatUsd($this->resultTotalMontoComision),
                            fn (): string => $this->effectiveCommissionRate() !== null
                                ? number_format((float) $this->effectiveCommissionRate(), 2, ',', '.').' % efectivo sobre PVP + upgrade'
                                : 'Sin base para calcular porcentaje',
                        ),
                        $this->statCard(
                            'stat_paid',
                            'Estado de cobro',
                            Heroicon::OutlinedBanknotes,
                            fn (): string => $this->paidStatusColor(),
                            fn (): string => $this->resultPaidCount.' de '.count($this->resultRecordIds).' pagados',
                            fn (): string => $this->paymentBreakdown(),
                            valueColor: fn (): string => $this->paidStatusColor(),
                        ),
                    ])
                    ->visible(fn (): bool => $this->hasResults())
                    ->columnSpanFull(),

                Section::make('Vouchers encontrados')
                    ->description(fn (): string => 'Resultado de «'.$this->lastSearchTerm.'». Todo lo que guarde abajo se aplica a estos registros.')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->collapsible()
                    ->schema([EmbeddedTable::make()])
                    ->visible(fn (): bool => $this->hasResults())
                    ->columnSpanFull(),

                Tabs::make('Actualización en bloque')
                    ->tabs([
                        Tab::make('Pago')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                Callout::make('Los vouchers tienen datos de pago distintos')
                                    ->description('El formulario muestra los del voucher más reciente. Al guardar, todos quedarán con estos mismos datos.')
                                    ->warning()
                                    ->visible(fn (): bool => $this->paymentDataIsMixed),
                                Form::make([EmbeddedSchema::make('paymentForm')])
                                    ->id('compensacion-vaucher-payment')
                                    ->livewireSubmitHandler('savePaymentTab')
                                    ->footer([
                                        $this->saveActions(
                                            Action::make('savePayment')
                                                ->label('Guardar pago')
                                                ->icon(Heroicon::OutlinedCheck)
                                                ->color('success')
                                                ->submit('savePaymentTab')
                                                ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('success')]),
                                        ),
                                    ]),
                            ]),
                        Tab::make('Estatus')
                            ->icon(Heroicon::OutlinedFlag)
                            ->schema([
                                Form::make([EmbeddedSchema::make('statusForm')])
                                    ->id('compensacion-vaucher-status')
                                    ->livewireSubmitHandler('requestStatusSave')
                                    ->footer([
                                        $this->saveActions(
                                            Action::make('requestStatus')
                                                ->label('Guardar estatus')
                                                ->icon(Heroicon::OutlinedCheck)
                                                ->color(fn (): string => $this->statusColor())
                                                ->submit('requestStatusSave')
                                                ->extraAttributes(fn (): array => [
                                                    'class' => FilamentIosButton::extraClassForFilamentColor($this->statusColor()),
                                                ]),
                                        ),
                                    ]),
                            ]),
                        Tab::make('Comisión')
                            ->icon(Heroicon::OutlinedReceiptPercent)
                            ->schema([
                                Form::make([EmbeddedSchema::make('commissionForm')])
                                    ->id('compensacion-vaucher-commission')
                                    ->livewireSubmitHandler('saveCommissionTab')
                                    ->footer([
                                        $this->saveActions(
                                            Action::make('saveCommission')
                                                ->label('Recalcular y guardar')
                                                ->icon(Heroicon::OutlinedCalculator)
                                                ->color('success')
                                                ->submit('saveCommissionTab')
                                                ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('success')]),
                                        ),
                                    ]),
                            ]),
                    ])
                    ->visible(fn (): bool => $this->hasResults())
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => TdevReport::query()->whereKey($this->resultRecordIds))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('vaucher')
                    ->label('Voucher')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('Voucher copiado'),
                TextColumn::make('pasajero')
                    ->label('Pasajero')
                    ->wrap(),
                TextColumn::make('agencia')
                    ->label('Agencia')
                    ->limit(40)
                    ->tooltip(fn (TdevReport $record): string => (string) $record->agencia)
                    ->toggleable(),
                TextColumn::make('monto_pvp_precio_de_venta')
                    ->label('PVP')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatUsd((float) $state)),
                TextColumn::make('monto_comision')
                    ->label('Comisión')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatUsd((float) $state))
                    ->toggleable(),
                TextColumn::make('estatus_vaucher')
                    ->label('Voucher')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => StatusVaucher::labelFromMixed($state))
                    ->color(fn ($state): string => StatusVaucher::filamentColorFromMixed($state)),
                TextColumn::make('estatus_pago')
                    ->label('Pago')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => StatusPago::labelFromMixed($state))
                    ->color(fn ($state): string => StatusPago::filamentColorFromMixed($state)),
            ])
            ->recordActions([
                Action::make('viewReport')
                    ->label('Ver detalle')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (TdevReport $record): string => TdevReportResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Sin vouchers')
            ->emptyStateDescription('Realice una búsqueda para ver resultados.');
    }

    public function searchVouchers(): void
    {
        $term = trim((string) ($this->searchForm->getState()['term'] ?? ''));

        $this->runSearch($term, exact: false);
    }

    /**
     * La búsqueda escrita es parcial; la que llega por enlace es exacta, porque el analista
     * eligió un voucher concreto y los guardados en bloque no deben arrastrar vouchers parecidos.
     */
    private function runSearch(string $term, bool $exact): void
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        $records = TdevReport::query()
            ->when(
                $exact,
                fn ($query) => $query->where('vaucher', $term),
                fn ($query) => $query->where('vaucher', 'like', '%'.$escaped.'%'),
            )
            ->orderByDesc('id')
            ->limit(self::MAX_RESULTS + 1)
            ->get();

        if ($records->isEmpty()) {
            $this->resetResults();
            $this->auditAction('TDEV_COMPENSACION_SEARCH_NO_RESULTS', [
                'term' => $term,
                'results_count' => 0,
            ]);

            Notification::make()
                ->title('Sin resultados')
                ->body('No se encontraron vouchers que coincidan con «'.$term.'».')
                ->warning()
                ->send();

            return;
        }

        if ($records->count() > self::MAX_RESULTS) {
            $this->resetResults();
            $this->auditAction('TDEV_COMPENSACION_SEARCH_TOO_MANY', [
                'term' => $term,
                'results_count' => $records->count(),
            ]);

            Notification::make()
                ->title('Búsqueda demasiado amplia')
                ->body('Hay más de '.self::MAX_RESULTS.' vouchers que coinciden. Escriba el número completo para no actualizar registros de más.')
                ->warning()
                ->send();

            return;
        }

        $this->lastSearchTerm = $term;
        $this->hydrateResults($records);
        $this->resetTable();

        $this->auditAction('TDEV_COMPENSACION_SEARCH_RESULTS', [
            'term' => $term,
            'origin' => $exact ? 'enlace' : 'busqueda',
            'results_count' => $records->count(),
            'record_ids' => $records->pluck('id')->take(20)->all(),
        ]);
    }

    public function clearResults(): void
    {
        $this->auditAction('TDEV_COMPENSACION_RESULTS_CLEARED', [
            'result_ids_count' => count($this->resultRecordIds),
            'result_ids_sample' => array_slice($this->resultRecordIds, 0, 20),
        ]);

        $this->resetResults();
        $this->searchForm->fill();
    }

    public function savePaymentTab(): void
    {
        $records = $this->getResultRecords();
        if ($records->isEmpty()) {
            $this->notifyNoActiveResults('TDEV_COMPENSACION_SAVE_PAYMENT_EMPTY');

            return;
        }

        $data = $this->paymentForm->getState();

        $formPath = $data['comprobante_pago'] ?? null;
        if (is_array($formPath)) {
            $formPath = $formPath[0] ?? null;
        }
        $formPath = is_string($formPath) && $formPath !== '' ? $formPath : null;

        $existingPaths = $records->pluck('comprobante_pago_path')->filter()->all();
        $uploadedPath = $formPath !== null && ! in_array($formPath, $existingPaths, true) ? $formPath : null;

        $rawEstatusPago = $data['estatus_pago'] ?? null;
        $formEstatusPago = is_string($rawEstatusPago) && $rawEstatusPago !== '' ? $rawEstatusPago : null;

        DB::transaction(function () use ($records, $data, $uploadedPath, $formEstatusPago): void {
            foreach ($records as $record) {
                $previousPath = is_string($record->comprobante_pago_path) ? $record->comprobante_pago_path : null;
                $path = $uploadedPath ?? $previousPath;

                $estatusPago = $path !== null && $path !== ''
                    ? TdevReportPaymentModalActions::resolveEstatusPagoAfterComprobanteUpload($previousPath, $path, $formEstatusPago)
                    : $formEstatusPago;

                $record->update([
                    'comprobante_pago_path' => $path,
                    'forma_pago' => ($data['forma_pago'] ?? null) ?: null,
                    'estatus_pago' => $estatusPago,
                    'entidad_bancaria_receptora' => ($data['entidad_bancaria_receptora'] ?? null) ?: null,
                    'referencia_bancaria_pago_vaucher_credito' => ($data['referencia_bancaria_pago_vaucher_credito'] ?? null) ?: null,
                    'tasa_bcv' => ($data['tasa_bcv'] ?? null) ?: 0,
                    'monto_abonado_en_cuenta_vaucher_credito' => ($data['monto_abonado_en_cuenta_vaucher_credito'] ?? null) ?: null,
                    'fecha_pago_vaucher_credito' => ($data['fecha_pago_vaucher_credito'] ?? null) ?: null,
                ]);
            }
        });

        $this->hydrateResults($records->fresh());
        $this->auditAction('TDEV_COMPENSACION_SAVE_PAYMENT_SUCCESS', [
            'affected_records' => $records->count(),
            'record_ids' => $records->pluck('id')->take(20)->all(),
            'has_uploaded_comprobante' => $uploadedPath !== null,
            'forma_pago' => ($data['forma_pago'] ?? null) ?: null,
            'estatus_pago' => $formEstatusPago,
            'referencia' => ($data['referencia_bancaria_pago_vaucher_credito'] ?? null) ?: null,
            'fecha_pago' => ($data['fecha_pago_vaucher_credito'] ?? null) ?: null,
        ]);

        $body = 'Se guardaron los datos de pago en '.$records->count().' voucher(s).';
        if ($uploadedPath !== null) {
            $body .= ' Por el comprobante nuevo, el pago quedó en «Pagado».';
        }

        Notification::make()
            ->title('Pago actualizado')
            ->body($body)
            ->success()
            ->send();
    }

    public function clearResultsAction(): Action
    {
        return Action::make('clearResults')
            ->label('Nueva búsqueda')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => $this->hasResults())
            ->action(fn () => $this->clearResults());
    }

    /**
     * Valida antes de pedir confirmación: si la validación fallara dentro de la modal,
     * quedaría abierta con los errores ocultos detrás.
     */
    public function requestStatusSave(): void
    {
        $data = $this->statusForm->getState();

        if (self::isAnulado($data['estatus_vaucher'] ?? null)) {
            $this->mountAction('saveStatus');

            return;
        }

        $this->saveStatusTab();
    }

    public function saveStatusAction(): Action
    {
        return Action::make('saveStatus')
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading('¿Anular los vouchers encontrados?')
            ->modalDescription(fn (): string => 'Se anularán '.count($this->resultRecordIds).' voucher(s), junto con su pago y su comisión. Esta acción no se deshace desde aquí.')
            ->modalSubmitActionLabel('Sí, anular')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->action(fn () => $this->saveStatusTab());
    }

    public function saveStatusTab(): void
    {
        $records = $this->getResultRecords();
        if ($records->isEmpty()) {
            $this->notifyNoActiveResults('TDEV_COMPENSACION_SAVE_STATUS_EMPTY');

            return;
        }

        $data = $this->statusForm->getState();

        $raw = (string) ($data['estatus_vaucher'] ?? '');
        $nuevo = StatusVaucher::tryFrom($raw) ?? StatusVaucher::fromStored($raw);
        if ($nuevo === null) {
            $this->auditAction('TDEV_COMPENSACION_SAVE_STATUS_INVALID', [
                'raw_status' => $raw,
            ]);

            Notification::make()
                ->title('Estatus no válido')
                ->body('Seleccione un estatus de voucher válido.')
                ->danger()
                ->send();

            return;
        }

        $obsHtml = null;
        if ($nuevo === StatusVaucher::Anulado) {
            $obsHtml = (string) ($data['observacion_anulacion'] ?? '');
            $plainLength = mb_strlen(trim(html_entity_decode(strip_tags($obsHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($plainLength < 3) {
                $this->auditAction('TDEV_COMPENSACION_SAVE_STATUS_OBSERVATION_REQUIRED', [
                    'status' => $raw,
                    'observation_plain_length' => $plainLength,
                ]);

                Notification::make()
                    ->title('Motivo requerido')
                    ->body('Para anular, escriba un motivo de al menos 3 caracteres.')
                    ->warning()
                    ->send();

                return;
            }
        }

        DB::transaction(function () use ($records, $nuevo, $obsHtml): void {
            foreach ($records as $record) {
                TdevReportVaucherStatusUpdater::apply($record, $nuevo, $obsHtml);
            }
        });

        $this->hydrateResults($records->fresh());
        $this->auditAction('TDEV_COMPENSACION_SAVE_STATUS_SUCCESS', [
            'affected_records' => $records->count(),
            'record_ids' => $records->pluck('id')->take(20)->all(),
            'status' => $raw,
            'has_observation' => filled($obsHtml),
        ]);

        Notification::make()
            ->title('Estatus actualizado')
            ->body('Se cambió a «'.$nuevo->label().'» el estatus de '.$records->count().' voucher(s).')
            ->success()
            ->send();
    }

    public function saveCommissionTab(): void
    {
        $records = $this->getResultRecords();
        if ($records->isEmpty()) {
            $this->notifyNoActiveResults('TDEV_COMPENSACION_SAVE_COMMISSION_EMPTY');

            return;
        }

        $percentage = $this->commissionForm->getState()['porcentaje_comision'] ?? null;

        DB::transaction(function () use ($records, $percentage): void {
            foreach ($records as $record) {
                TdevReportCommissionFromPercentageUpdater::apply($record, $percentage);
            }
        });

        $this->hydrateResults($records->fresh());
        $this->auditAction('TDEV_COMPENSACION_SAVE_COMMISSION_SUCCESS', [
            'affected_records' => $records->count(),
            'record_ids' => $records->pluck('id')->take(20)->all(),
            'porcentaje_comision' => $percentage,
        ]);

        Notification::make()
            ->title('Comisión recalculada')
            ->body('Nuevo total: '.self::formatUsd($this->resultTotalMontoComision).' en '.$records->count().' voucher(s).')
            ->success()
            ->send();
    }

    public function hasResults(): bool
    {
        return $this->resultRecordIds !== [];
    }

    public function suggestedAmountVes(mixed $tasaBcv): ?float
    {
        if (! is_numeric($tasaBcv) || (float) $tasaBcv <= 0 || $this->resultTotalMontoPvp <= 0) {
            return null;
        }

        return round($this->resultTotalMontoPvp * (float) $tasaBcv, 2);
    }

    public function projectedCommission(mixed $percentage): float
    {
        if (! is_numeric($percentage)) {
            return 0.0;
        }

        return round($this->resultTotalBaseComision * ((float) $percentage / 100), 2);
    }

    public static function formatUsd(float $amount): string
    {
        return 'US$ '.self::formatNumber($amount);
    }

    private static function formatNumber(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }

    private function statusColor(): string
    {
        return self::isAnulado($this->statusData['estatus_vaucher'] ?? null) ? 'danger' : 'success';
    }

    private static function isAnulado(mixed $status): bool
    {
        return $status === StatusVaucher::Anulado->value;
    }

    /**
     * Tarjeta de métrica: la sección aporta el marco, el icono y la etiqueta; la entrada, la cifra y su contexto.
     */
    private function statCard(string $name, string $label, Heroicon $icon, string|\Closure $iconColor, \Closure $state, \Closure $context, ?\Closure $valueColor = null): Section
    {
        return Section::make($label)
            ->icon($icon)
            ->iconColor($iconColor)
            ->compact()
            ->schema([
                TextEntry::make($name)
                    ->hiddenLabel()
                    ->state($state)
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold)
                    ->color($valueColor)
                    ->helperText($context),
            ])
            ->columnSpan(1);
    }

    public function averagePvp(): float
    {
        $count = count($this->resultRecordIds);

        return $count > 0 ? round($this->resultTotalMontoPvp / $count, 2) : 0.0;
    }

    public function effectiveCommissionRate(): ?float
    {
        if ($this->resultTotalBaseComision <= 0) {
            return null;
        }

        return round($this->resultTotalMontoComision / $this->resultTotalBaseComision * 100, 2);
    }

    public function paymentBreakdown(): string
    {
        $total = count($this->resultRecordIds);
        if ($total === 0) {
            return '';
        }

        if ($this->resultPaidCount === $total) {
            return 'Cobro completo';
        }

        $parts = [];
        if ($this->resultPendingCount > 0) {
            $parts[] = $this->resultPendingCount.' '.($this->resultPendingCount === 1 ? 'pendiente' : 'pendientes');
        }
        if ($this->resultAnnulledCount > 0) {
            $parts[] = $this->resultAnnulledCount.' '.($this->resultAnnulledCount === 1 ? 'anulado' : 'anulados');
        }
        $withoutStatus = $total - $this->resultPaidCount - $this->resultPendingCount - $this->resultAnnulledCount;
        if ($withoutStatus > 0) {
            $parts[] = $withoutStatus.' sin estatus';
        }

        return implode(' · ', $parts);
    }

    private function paidStatusColor(): string
    {
        $total = count($this->resultRecordIds);

        return match (true) {
            $total > 0 && $this->resultPaidCount === $total => 'success',
            $total > 0 && $this->resultAnnulledCount === $total => 'danger',
            default => 'warning',
        };
    }

    private function saveActions(Action $action): Actions
    {
        return Actions::make([$action])
            ->alignEnd()
            ->fullWidth(false);
    }

    private function notifyNoActiveResults(string $auditCode): void
    {
        $this->auditAction($auditCode, [
            'message' => 'Intento de guardar sin resultados activos',
        ]);

        Notification::make()
            ->title('Sin vouchers')
            ->body('Busque un voucher antes de guardar.')
            ->warning()
            ->send();
    }

    private function resetResults(): void
    {
        $this->resultRecordIds = [];
        $this->lastSearchTerm = '';
        $this->resultTotalMontoPvp = 0.0;
        $this->resultTotalMontoComision = 0.0;
        $this->resultTotalBaseComision = 0.0;
        $this->resultPaidCount = 0;
        $this->resultPendingCount = 0;
        $this->resultAnnulledCount = 0;
        $this->resultVoucherCount = 0;
        $this->resultAgencyCount = 0;
        $this->resultTotalUpgrade = 0.0;
        $this->paymentDataIsMixed = false;
        $this->fillActionForms(null);
    }

    /**
     * @param  Collection<int, TdevReport>  $records
     */
    private function hydrateResults(Collection $records): void
    {
        $this->resultRecordIds = $records->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->resultTotalMontoPvp = (float) $records->sum('monto_pvp_precio_de_venta');
        $this->resultTotalMontoComision = (float) $records->sum('monto_comision');
        $this->resultTotalBaseComision = (float) $records->sum(
            fn (TdevReport $record): float => (float) $record->precio_upgrade + (float) $record->monto_pvp_precio_de_venta
        );
        $this->resultPaidCount = $records->filter(fn (TdevReport $record): bool => $record->estatus_pago === StatusPago::Pagado)->count();
        $this->resultPendingCount = $records->filter(fn (TdevReport $record): bool => $record->estatus_pago === StatusPago::Pendiente)->count();
        $this->resultAnnulledCount = $records->filter(fn (TdevReport $record): bool => $record->estatus_pago === StatusPago::Anulado)->count();
        $this->resultVoucherCount = $records->pluck('vaucher')->map(fn ($v): string => mb_strtoupper(trim((string) $v)))->unique()->count();
        $this->resultAgencyCount = $records->pluck('agencia')->map(fn ($a): string => mb_strtoupper(trim((string) $a)))->filter()->unique()->count();
        $this->resultTotalUpgrade = (float) $records->sum(fn (TdevReport $record): float => (float) $record->precio_upgrade);
        $this->paymentDataIsMixed = $records
            ->map(fn (TdevReport $record): string => json_encode(self::paymentSnapshot($record)) ?: '')
            ->unique()
            ->count() > 1;

        /** @var TdevReport|null $first */
        $first = $records->sortByDesc('id')->first();
        $this->fillActionForms($first);
    }

    private function fillActionForms(?TdevReport $record): void
    {
        $this->paymentForm->fill($record !== null ? [
            'comprobante_pago' => $record->comprobante_pago_path,
            ...self::paymentSnapshot($record),
        ] : []);

        $this->statusForm->fill([
            'estatus_vaucher' => $record?->estatus_vaucher?->value ?? $record?->getRawOriginal('estatus_vaucher'),
            'observacion_anulacion' => null,
        ]);

        $this->commissionForm->fill([
            'porcentaje_comision' => $record?->porcentaje_comision,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function paymentSnapshot(TdevReport $record): array
    {
        return [
            'forma_pago' => $record->forma_pago?->value ?? $record->getRawOriginal('forma_pago'),
            'estatus_pago' => $record->estatus_pago?->value ?? $record->getRawOriginal('estatus_pago'),
            'entidad_bancaria_receptora' => $record->entidad_bancaria_receptora,
            'referencia_bancaria_pago_vaucher_credito' => $record->referencia_bancaria_pago_vaucher_credito,
            'tasa_bcv' => $record->tasa_bcv,
            'monto_abonado_en_cuenta_vaucher_credito' => $record->monto_abonado_en_cuenta_vaucher_credito,
            'fecha_pago_vaucher_credito' => self::parseDateForPicker($record->fecha_pago_vaucher_credito),
        ];
    }

    private static function parseDateForPicker(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, TdevReport>
     */
    private function getResultRecords(): Collection
    {
        if ($this->resultRecordIds === []) {
            return collect();
        }

        return TdevReport::query()
            ->whereKey($this->resultRecordIds)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function auditAction(string $action, array $details = []): void
    {
        $user = Auth::user();
        $traceId = (string) Str::uuid();

        $payload = [
            'trace_id' => $traceId,
            'component' => self::class,
            'user' => [
                'id' => $user?->id,
                'email' => $user?->email,
                'name' => $user?->name,
            ],
            'url' => request()->fullUrl(),
            'details' => $details,
        ];

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        LogController::log(
            (int) ($user?->id ?? 0),
            $action,
            'administration.compensacion-vaucher',
            Str::limit((string) $encodedPayload, 2000),
        );
    }
}
