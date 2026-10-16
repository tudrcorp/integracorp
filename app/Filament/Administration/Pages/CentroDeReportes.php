<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Enums\AdministrationReportType;
use App\Enums\OperationReportFormat;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Jobs\GenerateAdministrationReportJob;
use App\Models\Agency;
use App\Models\Agent;
use App\Support\Administration\Reports\AdministrationReportGenerator;
use App\Support\Administration\Reports\CollectionReportBuilder;
use App\Support\Administration\Reports\CollectionReportFilters;
use App\Support\Administration\Reports\CollectionReportNetwork;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
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
 * estructura), periodo y formato. Lo pequeño se descarga al instante; lo grande
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
        $this->form->fill([
            'type' => AdministrationReportType::CollectionsGeneral->value,
            'scope' => CollectionReportFilters::SCOPE_AGENCY,
            'include_subagents' => true,
            'period' => CollectionReportFilters::PERIOD_THIS_MONTH,
            'collection_status' => CollectionReportFilters::STATUS_ALL,
            'format' => OperationReportFormat::Excel->value,
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Elija el reporte, el periodo y el formato, y pulse «Generar reporte». Los datos son los mismos de «Cobranza Por Mes»: una fila por afiliación con su próxima cuota pendiente.';
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
                            ->viewData(['types' => AdministrationReportType::cases()])
                            ->required()
                            ->in(array_column(AdministrationReportType::cases(), 'value'))
                            ->live(),
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
                Section::make(fn (Get $get): string => self::isStructure($get) ? '3. Periodo' : '2. Periodo')
                    ->description('Por fecha de vencimiento de la cuota pendiente.')
                    ->schema([
                        ToggleButtons::make('period')
                            ->hiddenLabel()
                            ->options(CollectionReportFilters::periodOptions())
                            ->inline()
                            ->required()
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
                            ->required()
                            ->live(),
                    ])
                    ->columnSpanFull(),
                Section::make(fn (Get $get): string => self::isStructure($get) ? '4. Formato' : '3. Formato')
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

        try {
            $filters = CollectionReportFilters::fromArray($data);
        } catch (InvalidArgumentException $exception) {
            $this->notifyError('Revise los datos del reporte', $exception->getMessage());

            return null;
        }

        $count = CollectionReportBuilder::count($filters);

        if ($count === 0) {
            $this->notifyError('No hay datos', 'No hay cuentas por cobrar con el periodo y los filtros seleccionados. Pruebe con «Toda la cartera» o cambie el estatus de cobro.', 'warning');

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
                ->body(number_format($count, 0, ',', '.').' afiliaciones. Puede seguir trabajando: le avisaremos en la campana y aparecerá en «Mis reportes recientes».')
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

        $headline = $count === 0
            ? 'No hay cuentas por cobrar con estos criterios.'
            : 'Se incluirán <strong>'.$formatted.'</strong> '.($count === 1 ? 'afiliación' : 'afiliaciones').'.';

        $structure = match (true) {
            $network === null => null,
            $filters->scope === CollectionReportFilters::SCOPE_AGENCY => 'Estructura: '.$network['agencies'].' '.($network['agencies'] === 1 ? 'agencia' : 'agencias').' y '.$network['agents'].' '.($network['agents'] === 1 ? 'agente' : 'agentes').'.',
            default => 'Estructura: '.$network['agents'].' '.($network['agents'] === 1 ? 'agente' : 'agentes').' (el agente'.($network['agents'] > 1 ? ' y sus subagentes' : '').').',
        };

        $hint = match (true) {
            $count === 0 => 'Pruebe con «Toda la cartera» o cambie el estatus de cobro.',
            $format === OperationReportFormat::Pdf && $count > AdministrationReportGenerator::PDF_ROW_LIMIT => 'El PDF mostrará las primeras '.number_format(AdministrationReportGenerator::PDF_ROW_LIMIT, 0, ',', '.').' afiliaciones y se generará en segundo plano. Para el listado completo use Excel o CSV.',
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

    private static function isStructure(Get $get): bool
    {
        return $get('type') === AdministrationReportType::CollectionsByStructure->value;
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
