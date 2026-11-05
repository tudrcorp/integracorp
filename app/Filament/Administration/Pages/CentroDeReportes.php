<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Enums\AdministrationReportType;
use App\Enums\OperationReportFormat;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Jobs\GenerateAdministrationReportJob;
use App\Jobs\GenerateCapitationBillingReportJob;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\CapitationBillingClosing;
use App\Models\User;
use App\Support\Administration\CapitationBilling\CapitationBillingClosingService;
use App\Support\Administration\CapitationBilling\CapitationBillingReport;
use App\Support\Administration\Reports\AdministrationReportGenerator;
use App\Support\Administration\Reports\CollectionReportBuilder;
use App\Support\Administration\Reports\CollectionReportFilters;
use App\Support\Administration\Reports\CollectionReportNetwork;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use UnitEnum;

/**
 * Centro de Reportes de Administración.
 *
 * Cuatro pasos en una sola pantalla: reporte, alcance (solo en el reporte por
 * estructura), periodo y formato. El de comisiones filtra por fecha de cálculo y no
 * lleva estatus de cobro. Lo pequeño se descarga al instante; lo grande
 * se genera en cola, avisa en la campana y aparece en «Mis reportes recientes».
 * La vista de reportes y la lista de recientes se comparten con el generador de
 * Operaciones.
 */
class CentroDeReportes extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Centro de reportes';

    protected static ?string $title = 'Centro de reportes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'REPORTES';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'centro-de-reportes';

    private const SEARCH_LIMIT = 50;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Archivos que se están generando en cola para este usuario.
     *
     * @var list<string>
     */
    public array $pendingReports = [];

    /**
     * @var array{key: string, count: int, network: array{agencies: int, agents: int}|null}|null
     */
    private ?array $countMemo = null;

    public function mount(): void
    {
        $requested = AdministrationReportType::tryFrom((string) request()->query('reporte', ''));

        if ($requested?->isCapitation() && ! CapitationBillingReport::userCan()) {
            $requested = null;
        }

        $this->form->fill([
            'type' => ($requested ?? AdministrationReportType::CollectionsGeneral)->value,
            'scope' => CollectionReportFilters::SCOPE_AGENCY,
            'include_subagents' => true,
            'period' => $requested === AdministrationReportType::DroppedAffiliationCollections
                ? CollectionReportFilters::PERIOD_ALL
                : CollectionReportFilters::PERIOD_THIS_MONTH,
            'collection_status' => CollectionReportFilters::STATUS_ALL,
            'format' => OperationReportFormat::Excel->value,
            'cap_period' => CarbonImmutable::now()->format('Y-m'),
            'cap_supplier_id' => array_key_first(CapitationBillingReport::supplierOptions()),
            'discounts' => [],
            'bcv_rate' => CapitationBillingReport::userCan() ? CapitationBillingReport::suggestedBcvRate() : null,
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Elija el reporte, el periodo y el formato, y pulse «Generar reporte». Los reportes de cobranza traen los mismos datos de «Cuentas por cobrar»; el de afiliaciones dadas de baja, una fila por cuota pendiente; el de comisiones, una fila por comisión con toda la estructura comercial.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('1. ¿Qué reporte necesita?')
                    ->schema([
                        ViewField::make('type')
                            ->hiddenLabel()
                            ->view('filament.operations.pages.partials.report-type-picker')
                            ->viewData(['types' => self::availableTypes()])
                            ->required()
                            ->in(array_column(self::availableTypes(), 'value'))
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                /** Cada reporte tiene sus periodos: si el elegido no existe en el nuevo, vuelve a «Este mes». */
                                if (! array_key_exists((string) $get('period'), CollectionReportFilters::periodOptionsFor(self::reportType($get)))) {
                                    $set('period', CollectionReportFilters::PERIOD_THIS_MONTH);
                                }
                            }),
                    ])
                    ->columnSpanFull(),
                Section::make('2. ¿De quién?')
                    ->description('Por agencia trae también la cobranza de las agencias y agentes de su estructura.')
                    ->visible(fn (Get $get): bool => self::isStructure($get))
                    ->schema([
                        ToggleButtons::make('scope')
                            ->hiddenLabel()
                            ->options(CollectionReportFilters::scopeOptions())
                            ->icons([
                                CollectionReportFilters::SCOPE_AGENCY => 'heroicon-o-building-office-2',
                                CollectionReportFilters::SCOPE_AGENT => 'heroicon-o-user',
                            ])
                            ->inline()
                            ->required(fn (Get $get): bool => self::isStructure($get))
                            ->live(),
                        Select::make('agency_code')
                            ->label('Agencia')
                            ->placeholder('Escriba el código o el nombre de la agencia')
                            ->helperText('Se incluyen automáticamente sus agencias y agentes, a cualquier nivel.')
                            ->searchable()
                            ->searchDebounce(350)
                            ->getSearchResultsUsing(fn (string $search): array => self::searchAgencies($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => self::agencyOptionLabel((string) $value))
                            ->visible(fn (Get $get): bool => $get('scope') === CollectionReportFilters::SCOPE_AGENCY)
                            ->required(fn (Get $get): bool => self::isStructure($get) && $get('scope') === CollectionReportFilters::SCOPE_AGENCY)
                            ->validationMessages(['required' => 'Seleccione la agencia del reporte.'])
                            ->live(),
                        Grid::make(['default' => 1, 'md' => 3])
                            ->visible(fn (Get $get): bool => $get('scope') === CollectionReportFilters::SCOPE_AGENT)
                            ->schema([
                                Select::make('agent_id')
                                    ->label('Agente')
                                    ->placeholder('Escriba el nombre o el código del agente')
                                    ->searchable()
                                    ->searchDebounce(350)
                                    ->getSearchResultsUsing(fn (string $search): array => self::searchAgents($search))
                                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::agentOptionLabel((int) $value))
                                    ->required(fn (Get $get): bool => self::isStructure($get) && $get('scope') === CollectionReportFilters::SCOPE_AGENT)
                                    ->validationMessages(['required' => 'Seleccione el agente del reporte.'])
                                    ->live()
                                    ->columnSpan(['md' => 2]),
                                Toggle::make('include_subagents')
                                    ->label('Incluir sus subagentes')
                                    ->inline(false)
                                    ->live(),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('2. Proveedor y mes')
                    ->description('Solo los proveedores de la red Outsourcing. La tarifa de cada afiliado es la de la columna «Capitado por afiliado» (plan o ajuste negociado).')
                    ->visible(fn (Get $get): bool => self::isCapitation($get))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Select::make('cap_supplier_id')
                                    ->label('Proveedor')
                                    ->options(fn (): array => CapitationBillingReport::supplierOptions())
                                    ->placeholder('Seleccione el proveedor')
                                    ->native(false)
                                    ->required(fn (Get $get): bool => self::isCapitation($get))
                                    ->validationMessages(['required' => 'Seleccione el proveedor.'])
                                    ->live(),
                                Select::make('cap_period')
                                    ->label('Mes')
                                    ->options(fn (): array => CapitationBillingReport::periodOptions())
                                    ->native(false)
                                    ->selectablePlaceholder(false)
                                    ->required(fn (Get $get): bool => self::isCapitation($get))
                                    ->live(),
                            ]),
                        Placeholder::make('cap_status')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => $this->capitationStatusHtml($get)),
                        Actions::make([
                            Action::make('closeCapitationMonth')
                                ->label('Cerrar el mes')
                                ->icon(Heroicon::OutlinedLockClosed)
                                ->color('success')
                                ->requiresConfirmation()
                                ->modalHeading('Cerrar la pre-facturación del mes')
                                ->modalDescription('Se guarda la foto exacta de afiliados, tarifas, prorrateos, retiros, descuentos y tasa BCV. Desde ese momento el reporte del mes sale siempre igual, aunque cambien los afiliados o las tarifas. Para corregirlo habrá que reabrirlo con una razón.')
                                ->modalSubmitActionLabel('Sí, cerrar el mes')
                                ->visible(fn (Get $get): bool => self::isCapitation($get) && ! ($this->capitationClosing($get)?->isClosed() ?? false))
                                ->action(fn () => $this->closeCapitationMonth()),
                            Action::make('reopenCapitationMonth')
                                ->label('Reabrir el mes')
                                ->icon(Heroicon::OutlinedLockOpen)
                                ->color('danger')
                                ->modalHeading('Reabrir la pre-facturación del mes')
                                ->modalDescription('El reporte volverá a calcularse con los datos de hoy hasta que se cierre de nuevo. La razón queda en la bitácora del cierre.')
                                ->schema([
                                    Textarea::make('reason')
                                        ->label('Razón para reabrir')
                                        ->required()
                                        ->minLength(10)
                                        ->maxLength(2000)
                                        ->rows(3),
                                ])
                                ->modalSubmitActionLabel('Reabrir el mes')
                                ->visible(fn (Get $get): bool => self::isCapitation($get) && ($this->capitationClosing($get)?->isClosed() ?? false))
                                ->action(fn (array $data) => $this->reopenCapitationMonth((string) ($data['reason'] ?? ''))),
                        ]),
                    ])
                    ->columnSpanFull(),
                Section::make('3. Descuentos y tasa BCV')
                    ->description(fn (Get $get): string => ($this->capitationClosing($get)?->isClosed() ?? false)
                        ? 'El mes está cerrado: valen los descuentos y la tasa guardados en el cierre.'
                        : 'Opcionales. Se restan del monto a pagar y quedan guardados al cerrar el mes.')
                    ->visible(fn (Get $get): bool => self::isCapitation($get))
                    ->schema([
                        Repeater::make('discounts')
                            ->label('Descuentos')
                            ->schema([
                                TextInput::make('concept')
                                    ->label('Concepto')
                                    ->placeholder('Ej.: Medicamentos TDC1534 · paciente de convenio')
                                    ->required()
                                    ->maxLength(250)
                                    ->columnSpan(['md' => 3]),
                                TextInput::make('amount')
                                    ->label('Monto')
                                    ->prefix('US$')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(1_000_000)
                                    ->required(),
                            ])
                            ->columns(['default' => 1, 'md' => 4])
                            ->defaultItems(0)
                            ->maxItems(CapitationBillingClosingService::MAX_DISCOUNTS)
                            ->addActionLabel('Agregar descuento')
                            ->reorderable(false)
                            ->disabled(fn (Get $get): bool => $this->capitationClosing($get)?->isClosed() ?? false),
                        TextInput::make('bcv_rate')
                            ->label('Tasa BCV (Bs. por US$)')
                            ->helperText('Se toma la tasa oficial del día; puede cambiarla. Déjela vacía para no convertir a bolívares.')
                            ->numeric()
                            ->minValue(0.0001)
                            ->maxValue(1_000_000)
                            ->prefix('Bs.')
                            ->disabled(fn (Get $get): bool => $this->capitationClosing($get)?->isClosed() ?? false),
                    ])
                    ->columnSpanFull(),
                Section::make(fn (Get $get): string => self::isStructure($get) ? '3. Periodo' : '2. Periodo')
                    ->description(fn (Get $get): string => self::isCommissions($get)
                        ? 'Por fecha de cálculo de la comisión.'
                        : 'Por fecha de vencimiento de la cuota pendiente.')
                    ->visible(fn (Get $get): bool => ! self::isCapitation($get))
                    ->schema([
                        ToggleButtons::make('period')
                            ->hiddenLabel()
                            ->options(fn (Get $get): array => CollectionReportFilters::periodOptionsFor(self::reportType($get)))
                            ->inline()
                            ->required(fn (Get $get): bool => ! self::isCapitation($get))
                            ->live(),
                        Grid::make(2)
                            ->visible(fn (Get $get): bool => $get('period') === CollectionReportFilters::PERIOD_CUSTOM)
                            ->schema([
                                DatePicker::make('from')
                                    ->label('Desde')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->required(fn (Get $get): bool => $get('period') === CollectionReportFilters::PERIOD_CUSTOM)
                                    ->live(onBlur: true),
                                DatePicker::make('to')
                                    ->label('Hasta')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->afterOrEqual('from')
                                    ->validationMessages(['after_or_equal' => '«Hasta» debe ser igual o posterior a «Desde».'])
                                    ->required(fn (Get $get): bool => $get('period') === CollectionReportFilters::PERIOD_CUSTOM)
                                    ->live(onBlur: true),
                            ]),
                        ToggleButtons::make('collection_status')
                            ->label('Estatus de cobro')
                            ->options(CollectionReportFilters::collectionStatusOptions())
                            ->colors([
                                CollectionReportFilters::STATUS_ALL => 'gray',
                                CollectionReceivableReport::STATUS_PENDING => 'warning',
                                CollectionReceivableReport::STATUS_OVERDUE => 'danger',
                            ])
                            ->inline()
                            ->visible(fn (Get $get): bool => ! self::isCommissions($get) && ! self::isCapitation($get))
                            ->required(fn (Get $get): bool => ! self::isCommissions($get) && ! self::isCapitation($get))
                            ->live(),
                    ])
                    ->columnSpanFull(),
                Section::make(fn (Get $get): string => self::isStructure($get) || self::isCapitation($get) ? '4. Formato' : '3. Formato')
                    ->schema([
                        ToggleButtons::make('format')
                            ->hiddenLabel()
                            ->options(collect(OperationReportFormat::cases())
                                ->mapWithKeys(fn (OperationReportFormat $format): array => [$format->value => $format->label()])
                                ->all())
                            ->icons(collect(OperationReportFormat::cases())
                                ->mapWithKeys(fn (OperationReportFormat $format): array => [$format->value => $format->icon()])
                                ->all())
                            ->in(array_column(OperationReportFormat::cases(), 'value'))
                            ->inline()
                            ->required()
                            ->live(),
                        Placeholder::make('summary')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => $this->summaryHtml($get)),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('administration-report-center-form')
                    ->livewireSubmitHandler('generate')
                    ->footer([
                        Actions::make([
                            Action::make('generate')
                                ->label('Generar reporte')
                                ->icon(Heroicon::OutlinedArrowDownTray)
                                ->color('primary')
                                ->submit('generate')
                                ->extraAttributes([
                                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                                ]),
                        ])->fullWidth(false),
                    ]),
                View::make('filament.operations.pages.partials.recent-reports'),
            ]);
    }

    public function generate(): ?BinaryFileResponse
    {
        $data = $this->form->getState();
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        $format = OperationReportFormat::from((string) $data['format']);

        if (AdministrationReportType::tryFrom((string) ($data['type'] ?? ''))?->isCapitation()) {
            return $user instanceof User ? $this->generateCapitation($data, $format, $user) : null;
        }

        try {
            $filters = CollectionReportFilters::fromArray($data);
        } catch (InvalidArgumentException $exception) {
            $this->notifyError('Revise los datos del reporte', $exception->getMessage());

            return null;
        }

        $count = CollectionReportBuilder::count($filters);

        if ($count === 0) {
            $this->notifyError('No hay datos', $filters->type->isCommissions()
                ? 'No hay comisiones calculadas en ese periodo. Pruebe con «Este año» o «Todo el historial».'
                : 'No hay datos con el periodo y los filtros seleccionados. Pruebe con «Toda la cartera» o cambie el estatus de cobro.', 'warning');

            return null;
        }

        $userId = (int) $user->getAuthIdentifier();
        $filename = AdministrationReportGenerator::filename($filters->type, $format);
        $queued = AdministrationReportGenerator::shouldQueue($format, $count);

        SecurityAudit::log('AUDIT_ADMIN_REPORT_CENTER_REQUESTED', 'administration.report-center.generate', [
            'panel' => 'administration',
            'format' => $format->value,
            'rows' => $count,
            'queued' => $queued,
            'filters' => $filters->toArray(),
        ], $user);

        if ($queued) {
            GenerateAdministrationReportJob::dispatch($format->value, $filters->toArray(), $userId, $filename);
            $this->pendingReports[] = $filename;

            Notification::make()
                ->title('Estamos generando su reporte')
                ->body(number_format($count, 0, ',', '.').' '.$filters->type->rowNoun()[1].'. Puede seguir trabajando: le avisaremos en la campana y aparecerá en «Mis reportes recientes».')
                ->info()
                ->send();

            return null;
        }

        try {
            AdministrationReportGenerator::store($format, $filters, $userId, $filename);
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError('No se pudo generar el reporte', 'Intente nuevamente. Si el problema continúa, acote el periodo.');

            return null;
        }

        $path = AdministrationReportGenerator::absolutePathFor($userId, $filename);

        return $path === null ? null : response()->download($path, $filename, ['Content-Type' => $format->mimeType()]);
    }

    /**
     * Revisa si los reportes en cola ya están listos (la vista solo sondea
     * mientras haya alguno pendiente).
     */
    public function checkPendingReports(): void
    {
        $userId = (int) Auth::id();
        $ready = array_values(array_filter(
            $this->pendingReports,
            fn (string $filename): bool => AdministrationReportGenerator::exists($userId, $filename),
        ));

        if ($ready === []) {
            return;
        }

        $this->pendingReports = array_values(array_diff($this->pendingReports, $ready));

        Notification::make()
            ->title(count($ready) === 1 ? 'Su reporte está listo' : 'Sus reportes están listos')
            ->body('Descárguelo en «Mis reportes recientes».')
            ->success()
            ->send();
    }

    /**
     * @return list<array{name: string, label: string, size: string, created_at: \Carbon\CarbonImmutable, format: OperationReportFormat|null}>
     */
    public function getRecentReportsProperty(): array
    {
        $userId = Auth::id();

        return $userId === null ? [] : AdministrationReportGenerator::recent((int) $userId);
    }

    public function downloadUrl(string $filename): string
    {
        return route('administration.reports.download', ['file' => $filename]);
    }

    /**
     * @return array<string, string>
     */
    public static function searchAgencies(string $search): array
    {
        $search = trim($search);

        return Agency::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name_corporative', 'like', "%{$search}%")))
            ->orderBy('code')
            ->limit(self::SEARCH_LIMIT)
            ->get(['code', 'name_corporative', 'agency_type_id'])
            ->filter(fn (Agency $agency): bool => trim((string) $agency->code) !== '')
            ->mapWithKeys(fn (Agency $agency): array => [trim((string) $agency->code) => self::agencyLabel($agency)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function searchAgents(string $search): array
    {
        $search = trim($search);
        $idSearch = str_ireplace('AGT-', '', $search);

        return Agent::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code_agent', 'like', "%{$search}%")
                ->when(ctype_digit($idSearch), fn ($query) => $query->orWhere('id', (int) $idSearch))))
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'name', 'code_agent', 'owner_code', 'agent_type_id'])
            ->mapWithKeys(fn (Agent $agent): array => [(int) $agent->id => self::agentLabel($agent)])
            ->all();
    }

    private static function agencyOptionLabel(string $code): ?string
    {
        $agency = Agency::query()->where('code', $code)->first(['code', 'name_corporative', 'agency_type_id']);

        return $agency instanceof Agency ? self::agencyLabel($agency) : null;
    }

    private static function agentOptionLabel(int $agentId): ?string
    {
        $agent = Agent::query()->find($agentId, ['id', 'name', 'code_agent', 'owner_code', 'agent_type_id']);

        return $agent instanceof Agent ? self::agentLabel($agent) : null;
    }

    private static function agencyLabel(Agency $agency): string
    {
        $type = match ((int) $agency->agency_type_id) {
            CollectionReportNetwork::AGENCY_TYPE_MASTER => 'Master',
            CollectionReportNetwork::AGENCY_TYPE_GENERAL => 'General',
            default => null,
        };

        return trim((string) $agency->code).' · '.(trim((string) $agency->name_corporative) ?: 'Sin razón social').($type === null ? '' : ' ('.$type.')');
    }

    private static function agentLabel(Agent $agent): string
    {
        $agency = trim((string) $agent->owner_code);
        $role = (int) $agent->agent_type_id === CollectionReportNetwork::AGENT_TYPE_SUBAGENT ? 'Subagente' : 'Agente';

        return CollectionReportBuilder::agentLabel($agent).' · '.$role.($agency === '' ? '' : ' de '.$agency);
    }

    private function summaryHtml(Get $get): HtmlString
    {
        $format = OperationReportFormat::tryFrom((string) $get('format'));

        if (self::isCapitation($get)) {
            return new HtmlString('<div class="flex flex-col gap-0.5 rounded-xl bg-gray-50 px-4 py-3 text-sm dark:bg-white/5">'
                .'<span class="text-gray-900 dark:text-white">Cuatro hojas: afiliados del mes, resumen por tomador con el resumen de pago, prorrateo de nuevos afiliados y retiros.</span>'
                .'<span class="text-gray-500 dark:text-gray-400">'.e($format === OperationReportFormat::Pdf
                    ? 'El PDF se genera en segundo plano y le avisaremos en la campana.'
                    : ($format === OperationReportFormat::Csv ? 'La descarga es inmediata. El CSV trae todas las filas con la columna SECCION.' : 'La descarga es inmediata.')).'</span>'
                .'</div>');
        }

        try {
            $filters = CollectionReportFilters::fromArray([
                'type' => $get('type'),
                'scope' => $get('scope'),
                'agency_code' => $get('agency_code'),
                'agent_id' => $get('agent_id'),
                'include_subagents' => $get('include_subagents'),
                'period' => $get('period'),
                'from' => $get('from'),
                'to' => $get('to'),
                'collection_status' => $get('collection_status'),
            ]);
        } catch (InvalidArgumentException $exception) {
            return new HtmlString('<p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">'.e($exception->getMessage()).'</p>');
        }

        ['count' => $count, 'network' => $network] = $this->matchingCount($filters);
        $formatted = number_format($count, 0, ',', '.');

        [$singular, $plural] = $filters->type->rowNoun();
        $headline = $count === 0
            ? 'No hay datos con estos criterios.'
            : 'Se incluirán <strong>'.$formatted.'</strong> '.($count === 1 ? $singular : $plural).'.';

        $structure = match (true) {
            $network === null => null,
            $filters->scope === CollectionReportFilters::SCOPE_AGENCY => 'Estructura: '.$network['agencies'].' '.($network['agencies'] === 1 ? 'agencia' : 'agencias').' y '.$network['agents'].' '.($network['agents'] === 1 ? 'agente' : 'agentes').'.',
            default => 'Estructura: '.$network['agents'].' '.($network['agents'] === 1 ? 'agente' : 'agentes').' (el agente'.($network['agents'] > 1 ? ' y sus subagentes' : '').').',
        };

        $hint = match (true) {
            $count === 0 && $filters->type->isCommissions() => 'Pruebe con «Este año» o «Todo el historial».',
            $count === 0 => 'Pruebe con «Toda la cartera» o cambie el estatus de cobro.',
            $format === OperationReportFormat::Pdf && $count > AdministrationReportGenerator::PDF_ROW_LIMIT => 'El PDF mostrará las primeras '.number_format(AdministrationReportGenerator::PDF_ROW_LIMIT, 0, ',', '.').' '.$plural.' y se generará en segundo plano. Para el listado completo use Excel o CSV.',
            $format !== null && AdministrationReportGenerator::shouldQueue($format, $count) => 'Es un reporte grande: se generará en segundo plano y le avisaremos en la campana al terminar.',
            $format === OperationReportFormat::Csv => 'La descarga es inmediata. El CSV trae solo las filas de datos, listo para procesar.',
            $format === OperationReportFormat::Pdf => 'La descarga es inmediata. El PDF trae las columnas principales; Excel y CSV traen todas.',
            default => 'La descarga es inmediata.',
        };

        return new HtmlString(
            '<div class="flex flex-col gap-0.5 rounded-xl bg-gray-50 px-4 py-3 text-sm dark:bg-white/5">'
            .'<span class="text-gray-900 dark:text-white">'.$headline.'</span>'
            .($structure === null ? '' : '<span class="text-gray-600 dark:text-gray-300">'.e($structure).'</span>')
            .'<span class="text-gray-500 dark:text-gray-400">'.e($hint).'</span>'
            .'</div>'
        );
    }

    /**
     * @return array{count: int, network: array{agencies: int, agents: int}|null}
     */
    private function matchingCount(CollectionReportFilters $filters): array
    {
        $key = md5(serialize($filters->toArray()));

        if ($this->countMemo !== null && $this->countMemo['key'] === $key) {
            return ['count' => $this->countMemo['count'], 'network' => $this->countMemo['network']];
        }

        $network = $filters->type === AdministrationReportType::CollectionsByStructure
            ? CollectionReportBuilder::network($filters)
            : null;

        $count = CollectionReportBuilder::count($filters, $network);
        $this->countMemo = ['key' => $key, 'count' => $count, 'network' => $network?->size()];

        return ['count' => $count, 'network' => $this->countMemo['network']];
    }

    /**
     * La pre-facturación de capitado solo aparece con su permiso.
     *
     * @return list<AdministrationReportType>
     */
    public static function availableTypes(): array
    {
        return array_values(array_filter(
            AdministrationReportType::cases(),
            fn (AdministrationReportType $type): bool => ! $type->isCapitation() || CapitationBillingReport::userCan(),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function generateCapitation(array $data, OperationReportFormat $format, User $user): ?BinaryFileResponse
    {
        if (! CapitationBillingReport::userCan()) {
            $this->notifyError('Sin permiso', 'No tiene permiso para la pre-facturación de capitado.');

            return null;
        }

        try {
            $supplier = CapitationBillingReport::supplier($data['cap_supplier_id'] ?? null);
            $period = CapitationBillingReport::period($data['cap_period'] ?? null);
            $closing = CapitationBillingReport::closing($supplier, $period);
            $closed = $closing?->isClosed() ?? false;
            $discounts = $closed ? [] : CapitationBillingClosingService::normalizeDiscounts(array_values((array) ($data['discounts'] ?? [])));
            $bcvRate = $closed ? null : CapitationBillingReport::rate($data['bcv_rate'] ?? null);
        } catch (InvalidArgumentException $exception) {
            $this->notifyError('Revise los datos del reporte', $exception->getMessage());

            return null;
        }

        $userId = (int) $user->getAuthIdentifier();
        $filename = CapitationBillingReport::filename($format);

        SecurityAudit::log('AUDIT_ADMIN_REPORT_CENTER_REQUESTED', 'administration.report-center.generate', [
            'panel' => 'administration',
            'report' => AdministrationReportType::CapitationBilling->value,
            'format' => $format->value,
            'supplier_id' => $supplier->getKey(),
            'period' => $period->format('Y-m'),
            'closed' => $closed,
            'discounts' => count($discounts),
        ], $user);

        if (CapitationBillingReport::shouldQueue($format)) {
            GenerateCapitationBillingReportJob::dispatch($format->value, (int) $supplier->getKey(), $period->format('Y-m'), $discounts, $bcvRate, $userId, $filename);
            $this->pendingReports[] = $filename;

            Notification::make()
                ->title('Estamos generando la pre-facturación')
                ->body($supplier->name.' · '.ucfirst($period->locale('es')->translatedFormat('F Y')).'. Puede seguir trabajando: le avisaremos en la campana y aparecerá en «Mis reportes recientes».')
                ->info()
                ->send();

            return null;
        }

        try {
            $snapshot = CapitationBillingReport::store($format, $supplier, $period, $discounts, $bcvRate, $userId, $filename);
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError('No se pudo generar el reporte', 'Intente nuevamente. Si el problema continúa, avise a soporte.');

            return null;
        }

        if ($snapshot->monthRows === [] && $snapshot->prorateRows === []) {
            Notification::make()
                ->title('Reporte sin afiliados')
                ->body('No hay afiliados con capitado de '.$supplier->name.' en '.mb_strtolower($snapshot->periodLabel()).'. Revise que el proveedor tenga un plan de capitado vigente para los planes de sus afiliados.')
                ->warning()
                ->send();
        }

        $path = AdministrationReportGenerator::absolutePathFor($userId, $filename);

        return $path === null ? null : response()->download($path, $filename, ['Content-Type' => $format->mimeType()]);
    }

    public function closeCapitationMonth(): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! CapitationBillingReport::userCan()) {
            $this->notifyError('Sin permiso', 'No tiene permiso para cerrar la pre-facturación de capitado.');

            return;
        }

        $data = $this->data ?? [];

        try {
            $supplier = CapitationBillingReport::supplier($data['cap_supplier_id'] ?? null);
            $period = CapitationBillingReport::period($data['cap_period'] ?? null);
            $closing = CapitationBillingClosingService::close(
                $supplier,
                $period,
                CapitationBillingClosingService::normalizeDiscounts(array_values((array) ($data['discounts'] ?? []))),
                CapitationBillingReport::rate($data['bcv_rate'] ?? null),
                $user,
            );
        } catch (InvalidArgumentException $exception) {
            $this->notifyError('No se cerró el mes', $exception->getMessage(), 'warning');

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError('No se cerró el mes', 'Ocurrió un error y no se guardó nada. Intente nuevamente.');

            return;
        }

        Notification::make()
            ->title('Mes cerrado')
            ->body($closing->supplier_name.' · '.ucfirst($closing->period->locale('es')->translatedFormat('F Y')).': '.number_format((int) $closing->affiliates_count, 0, ',', '.').' afiliados, total a pagar US$ '.number_format((float) $closing->total_amount, 2, ',', '.').'.')
            ->success()
            ->send();
    }

    public function reopenCapitationMonth(string $reason): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! CapitationBillingReport::userCan()) {
            $this->notifyError('Sin permiso', 'No tiene permiso para reabrir la pre-facturación de capitado.');

            return;
        }

        $data = $this->data ?? [];

        try {
            $supplier = CapitationBillingReport::supplier($data['cap_supplier_id'] ?? null);
            $closing = CapitationBillingReport::closing($supplier, CapitationBillingReport::period($data['cap_period'] ?? null))
                ?? throw new InvalidArgumentException('Ese mes no está cerrado.');

            CapitationBillingClosingService::reopen($closing, $reason, $user);
        } catch (InvalidArgumentException $exception) {
            $this->notifyError('No se reabrió el mes', $exception->getMessage(), 'warning');

            return;
        }

        Notification::make()
            ->title('Mes reabierto')
            ->body('El reporte vuelve a calcularse con los datos de hoy hasta que lo cierre de nuevo.')
            ->warning()
            ->send();
    }

    private function capitationClosing(Get $get): ?CapitationBillingClosing
    {
        try {
            return CapitationBillingReport::closing(
                CapitationBillingReport::supplier($get('cap_supplier_id')),
                CapitationBillingReport::period($get('cap_period')),
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function capitationStatusHtml(Get $get): HtmlString
    {
        $closing = $this->capitationClosing($get);

        [$tone, $text] = match (true) {
            $closing?->isClosed() === true => ['bg-success-50 text-success-800 dark:bg-success-500/10 dark:text-success-300', 'Mes cerrado el '.$closing->closed_at?->format('d/m/Y h:i A').' por '.$closing->closed_by_name.': '.number_format((int) $closing->affiliates_count, 0, ',', '.').' afiliados, total a pagar US$ '.number_format((float) $closing->total_amount, 2, ',', '.').'. El reporte reproduce ese cierre.'],
            $closing !== null => ['bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300', 'Reabierto el '.$closing->reopened_at?->format('d/m/Y').' por '.$closing->reopened_by_name.' («'.$closing->reopen_reason.'»). El reporte es preliminar hasta que lo cierre de nuevo.'],
            default => ['bg-gray-50 text-gray-700 dark:bg-white/5 dark:text-gray-300', 'Mes sin cerrar: el reporte es preliminar y se calcula con los datos de hoy. Al cerrarlo queda fijo.'],
        };

        return new HtmlString('<p class="rounded-xl px-4 py-3 text-sm '.$tone.'">'.e($text).'</p>');
    }

    private static function isCapitation(Get $get): bool
    {
        return self::reportType($get)?->isCapitation() ?? false;
    }

    private static function isStructure(Get $get): bool
    {
        return $get('type') === AdministrationReportType::CollectionsByStructure->value;
    }

    private static function isCommissions(Get $get): bool
    {
        return self::reportType($get)?->isCommissions() ?? false;
    }

    private static function reportType(Get $get): ?AdministrationReportType
    {
        return AdministrationReportType::tryFrom((string) $get('type'));
    }

    private function notifyError(string $title, string $body, string $status = 'danger'): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->status($status)
            ->send();
    }
}
