<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Enums\OperationReportFormat;
use App\Enums\OperationReportType;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Jobs\GenerateOperationReportJob;
use App\Support\Filament\FilamentIosButton;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\OperationsDashboardMetrics;
use App\Support\Operations\Reports\OperationReportBuilder;
use App\Support\Operations\Reports\OperationReportFilters;
use App\Support\Operations\Reports\OperationReportGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use UnitEnum;

/**
 * Generador de reportes de Operaciones sobre `operation_service_statistics`.
 *
 * Tres pasos en una sola pantalla: tipo, periodo (con filtros opcionales
 * plegados) y formato. Lo pequeño se descarga al instante; el detalle grande
 * se genera en cola y aparece en «Mis reportes recientes».
 */
class GeneradorDeReportes extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Generador de reportes';

    protected static ?string $title = 'Generador de reportes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'REPORTES';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'generador-de-reportes';

    /**
     * Opciones de filtros cacheadas: valores distintos de columnas indexadas.
     */
    private const FILTER_OPTIONS_TTL_SECONDS = 600;

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
     * @var array{key: string, count: int}|null
     */
    private ?array $countMemo = null;

    public function mount(): void
    {
        $this->form->fill([
            'type' => OperationReportType::ServiceDetail->value,
            'period' => OperationReportFilters::PERIOD_THIS_MONTH,
            'format' => OperationReportFormat::Excel->value,
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Elija el reporte, el periodo y el formato. Los datos salen de la trazabilidad de servicios de Operaciones.';
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
                            ->viewData(['types' => OperationReportType::cases()])
                            ->required()
                            ->in(array_column(OperationReportType::cases(), 'value'))
                            ->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $type = OperationReportType::tryFrom((string) $state);
                                $format = OperationReportFormat::tryFrom((string) $get('format'));

                                if ($type !== null && ($format === null || ! $type->supportsFormat($format))) {
                                    $set('format', $type->formats()[0]->value);
                                }
                            }),
                    ])
                    ->columnSpanFull(),
                Section::make('2. Periodo')
                    ->description('Por fecha de inicio del servicio.')
                    ->visible(fn (Get $get): bool => self::selectedType($get)?->usesFilters() ?? true)
                    ->schema([
                        ToggleButtons::make('period')
                            ->hiddenLabel()
                            ->options(OperationReportFilters::periodOptions())
                            ->inline()
                            ->required()
                            ->live(),
                        Grid::make(2)
                            ->visible(fn (Get $get): bool => $get('period') === OperationReportFilters::PERIOD_CUSTOM)
                            ->schema([
                                DatePicker::make('from')
                                    ->label('Desde')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now())
                                    ->required(fn (Get $get): bool => $get('period') === OperationReportFilters::PERIOD_CUSTOM)
                                    ->live(onBlur: true),
                                DatePicker::make('to')
                                    ->label('Hasta')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now())
                                    ->afterOrEqual('from')
                                    ->required(fn (Get $get): bool => $get('period') === OperationReportFilters::PERIOD_CUSTOM)
                                    ->live(onBlur: true),
                            ]),
                        Section::make('Filtros (opcional)')
                            ->description('Déjelos vacíos para incluir todo.')
                            ->collapsible()
                            ->collapsed()
                            ->compact()
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        self::filterSelect('statuses', 'Estatus del servicio', 'service_status'),
                                        self::filterSelect('coverages', 'Cobertura', 'coverage'),
                                        self::filterSelect('business_lines', 'Línea de negocio', 'business_line'),
                                        self::filterSelect('services', 'Servicio', 'service'),
                                        self::filterSelect('service_providers', 'Proveedor de servicio', 'service_provider')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('3. Formato')
                    ->schema([
                        ToggleButtons::make('format')
                            ->hiddenLabel()
                            ->options(fn (Get $get): array => collect(self::selectedType($get)?->formats() ?? OperationReportFormat::cases())
                                ->mapWithKeys(fn (OperationReportFormat $format): array => [$format->value => $format->label()])
                                ->all())
                            ->icons(collect(OperationReportFormat::cases())
                                ->mapWithKeys(fn (OperationReportFormat $format): array => [$format->value => $format->icon()])
                                ->all())
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
                    ->id('operation-report-generator-form')
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

        $type = OperationReportType::from((string) $data['type']);
        $format = OperationReportFormat::from((string) $data['format']);

        if (! $type->supportsFormat($format)) {
            $this->notifyError('Formato no disponible', '«'.$type->label().'» solo se genera en '.implode(', ', array_map(fn (OperationReportFormat $f): string => $f->label(), $type->formats())).'.');

            return null;
        }

        try {
            $filters = OperationReportFilters::fromArray($data);
        } catch (InvalidArgumentException $exception) {
            $this->notifyError('Revise el periodo', $exception->getMessage());

            return null;
        }

        $count = OperationReportBuilder::count($type, $filters);

        if ($count === 0) {
            $this->notifyError('No hay datos', 'No hay servicios para el periodo y los filtros seleccionados.', 'warning');

            return null;
        }

        $filename = OperationReportGenerator::filename($type, $format);

        if (OperationReportGenerator::shouldQueue($type, $format, $count)) {
            GenerateOperationReportJob::dispatch($type->value, $format->value, $filters->toArray(), (int) $user->getAuthIdentifier(), $filename);
            $this->pendingReports[] = $filename;

            Notification::make()
                ->title('Estamos generando su reporte')
                ->body(number_format($count, 0, ',', '.').' servicios. Puede seguir trabajando: aparecerá en «Mis reportes recientes» y en la campana.')
                ->info()
                ->send();

            return null;
        }

        try {
            OperationReportGenerator::store($type, $format, $filters, (int) $user->getAuthIdentifier(), $filename);
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError('No se pudo generar el reporte', 'Intente nuevamente. Si el problema continúa, acote el periodo.');

            return null;
        }

        $path = OperationReportGenerator::absolutePathFor((int) $user->getAuthIdentifier(), $filename);

        return $path === null ? null : response()->download($path, $filename, ['Content-Type' => $format->mimeType()]);
    }

    /**
     * Revisa si los reportes en cola ya están listos (la vista sólo sondea
     * mientras haya alguno pendiente).
     */
    public function checkPendingReports(): void
    {
        $userId = (int) Auth::id();
        $ready = array_values(array_filter(
            $this->pendingReports,
            fn (string $filename): bool => OperationReportGenerator::exists($userId, $filename),
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

        return $userId === null ? [] : OperationReportGenerator::recent((int) $userId);
    }

    public function downloadUrl(string $filename): string
    {
        return route('operations.reports.download', ['file' => $filename]);
    }

    private function summaryHtml(Get $get): HtmlString
    {
        $type = self::selectedType($get);
        $format = OperationReportFormat::tryFrom((string) $get('format'));

        if ($type === null) {
            return new HtmlString('');
        }

        try {
            $count = $this->matchingCount($type, OperationReportFilters::fromArray([
                'period' => $get('period'),
                'from' => $get('from'),
                'to' => $get('to'),
                'statuses' => $get('statuses'),
                'coverages' => $get('coverages'),
                'business_lines' => $get('business_lines'),
                'services' => $get('services'),
                'service_providers' => $get('service_providers'),
            ]));
        } catch (InvalidArgumentException $exception) {
            return new HtmlString('<p class="text-sm text-amber-700 dark:text-amber-400">'.e($exception->getMessage()).'</p>');
        }

        $formatted = number_format($count, 0, ',', '.');
        $headline = $count === 0
            ? 'No hay servicios con estos criterios.'
            : 'Se incluirán <strong>'.$formatted.'</strong> '.($count === 1 ? 'servicio' : 'servicios').'.';

        $hint = match (true) {
            $count === 0 => 'Amplíe el periodo o quite filtros.',
            $format === OperationReportFormat::Pdf && $type->isDetail() && $count > OperationReportGenerator::PDF_ROW_LIMIT => 'El PDF mostrará los primeros '.number_format(OperationReportGenerator::PDF_ROW_LIMIT, 0, ',', '.').' y se generará en segundo plano. Para el listado completo use Excel o CSV.',
            $format === OperationReportFormat::Pdf && $type->hasManyRows() => 'El PDF (hasta '.number_format(OperationReportGenerator::PDF_ROW_LIMIT, 0, ',', '.').' filas) se generará en segundo plano y le avisaremos al terminar.',
            $format !== null && OperationReportGenerator::shouldQueue($type, $format, $count) => 'Es un reporte grande: se generará en segundo plano y le avisaremos al terminar.',
            default => 'La descarga es inmediata.',
        };

        return new HtmlString(
            '<div class="flex flex-col gap-0.5 rounded-xl bg-gray-50 px-4 py-3 text-sm dark:bg-white/5">'
            .'<span class="text-gray-900 dark:text-white">'.$headline.'</span>'
            .'<span class="text-gray-500 dark:text-gray-400">'.e($hint).'</span>'
            .'</div>'
        );
    }

    private function matchingCount(OperationReportType $type, OperationReportFilters $filters): int
    {
        $key = $type->value.'|'.md5(serialize($filters->toArray()));

        if ($this->countMemo !== null && $this->countMemo['key'] === $key) {
            return $this->countMemo['count'];
        }

        $count = OperationReportBuilder::count($type, $filters);
        $this->countMemo = ['key' => $key, 'count' => $count];

        return $count;
    }

    private static function selectedType(Get $get): ?OperationReportType
    {
        return OperationReportType::tryFrom((string) $get('type'));
    }

    private static function filterSelect(string $name, string $label, string $column): Select
    {
        return Select::make($name)
            ->label($label)
            ->multiple()
            ->searchable()
            ->placeholder('Todos')
            ->options(fn (): array => self::distinctValues($column))
            ->live();
    }

    /**
     * Valores presentes en la columna, dentro del alcance del usuario.
     *
     * @return array<string, string>
     */
    private static function distinctValues(string $column): array
    {
        $user = Auth::user();
        $scopeKey = OperationsSupplierScope::currentSupplierId()
            ?? (in_array('ATENMEDI', is_array($user?->departament) ? $user->departament : [], true) ? 'atenmedi' : 'tdg');

        return Cache::remember(
            'operation-reports:options:'.$column.':'.$scopeKey,
            self::FILTER_OPTIONS_TTL_SECONDS,
            fn (): array => OperationsDashboardMetrics::statisticsQuery()
                ->whereNotNull($column)
                ->where($column, '<>', '')
                ->distinct()
                ->orderBy($column)
                ->limit(500)
                ->pluck($column, $column)
                ->all(),
        );
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
