<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\WhiteCompany;
use App\Support\Administration\Insights\AdministrativeReportFilters;
use App\Support\Administration\Insights\AdministrativeReportPdf;
use App\Support\Administration\Reports\AdministrationReportGenerator;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
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
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use UnitEnum;

/**
 * Informe administrativo en PDF: resumen general, ventas, cobranza y bajas con gráficos,
 * tablas y lectura automática, de toda la empresa o de una agencia, un agente o una
 * empresa aliada. La página solo pide el periodo y el alcance; el PDF se descarga al instante.
 *
 * Tiene permiso de menú propio: no basta con pertenecer a Administración.
 */
class InformeAdministrativo extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Informe administrativo';

    protected static ?string $title = 'Informe administrativo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'REPORTES';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'informe-administrativo';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Lo pide la lista compartida de «Mis reportes recientes». Aquí siempre está vacía:
     * el informe se genera al instante, sin cola.
     *
     * @var list<string>
     */
    public array $pendingReports = [];

    public function mount(): void
    {
        $defaults = [
            'period' => AdministrativeReportFilters::PERIOD_THIS_YEAR,
            'scope' => AdministrativeReportFilters::SCOPE_ALL,
            'include_subagents' => true,
        ];

        $this->form->fill($defaults);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Elija el periodo y de quién es el informe, y pulse «Generar PDF»: se descarga al instante y queda una copia en «Mis reportes recientes» por 7 días.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Periodo y alcance')
                ->columnSpanFull()
                ->schema([
                    ToggleButtons::make('period')
                        ->label('Periodo')
                        ->options(AdministrativeReportFilters::periodOptions())
                        ->inline()
                        ->required()
                        ->live(),
                    Grid::make(2)
                        ->visible(fn (Get $get): bool => $get('period') === AdministrativeReportFilters::PERIOD_CUSTOM)
                        ->schema([
                            DatePicker::make('from')
                                ->label('Desde')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->required(fn (Get $get): bool => $get('period') === AdministrativeReportFilters::PERIOD_CUSTOM),
                            DatePicker::make('to')
                                ->label('Hasta')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->afterOrEqual('from')
                                ->validationMessages(['after_or_equal' => '«Hasta» debe ser igual o posterior a «Desde».'])
                                ->required(fn (Get $get): bool => $get('period') === AdministrativeReportFilters::PERIOD_CUSTOM),
                        ]),
                    ToggleButtons::make('scope')
                        ->label('¿De quién es el informe?')
                        ->options(AdministrativeReportFilters::scopeOptions())
                        ->icons([
                            AdministrativeReportFilters::SCOPE_ALL => 'heroicon-o-globe-americas',
                            AdministrativeReportFilters::SCOPE_AGENCY => 'heroicon-o-building-office-2',
                            AdministrativeReportFilters::SCOPE_AGENT => 'heroicon-o-user',
                            AdministrativeReportFilters::SCOPE_WHITE_COMPANY => 'heroicon-o-building-storefront',
                        ])
                        ->inline()
                        ->required()
                        ->live(),
                    Select::make('agency_code')
                        ->label('Agencia')
                        ->placeholder('Escriba el código o el nombre de la agencia')
                        ->helperText('Incluye automáticamente las agencias y agentes de su estructura.')
                        ->searchable()
                        ->searchDebounce(350)
                        ->getSearchResultsUsing(fn (string $search): array => CentroDeReportes::searchAgencies($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => CentroDeReportes::searchAgencies((string) $value)[(string) $value] ?? (string) $value)
                        ->visible(fn (Get $get): bool => $get('scope') === AdministrativeReportFilters::SCOPE_AGENCY)
                        ->required(fn (Get $get): bool => $get('scope') === AdministrativeReportFilters::SCOPE_AGENCY),
                    Grid::make(['default' => 1, 'md' => 3])
                        ->visible(fn (Get $get): bool => $get('scope') === AdministrativeReportFilters::SCOPE_AGENT)
                        ->schema([
                            Select::make('agent_id')
                                ->label('Agente')
                                ->placeholder('Escriba el nombre o el código del agente')
                                ->searchable()
                                ->searchDebounce(350)
                                ->getSearchResultsUsing(fn (string $search): array => CentroDeReportes::searchAgents($search))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => CentroDeReportes::searchAgents((string) $value)[(int) $value] ?? null)
                                ->required(fn (Get $get): bool => $get('scope') === AdministrativeReportFilters::SCOPE_AGENT)
                                ->columnSpan(['md' => 2]),
                            Toggle::make('include_subagents')
                                ->label('Incluir sus subagentes')
                                ->inline(false),
                        ]),
                    Select::make('white_company_id')
                        ->label('Empresa aliada')
                        ->placeholder('Seleccione la empresa aliada')
                        ->helperText('Incluye su agencia raíz y toda su descendencia.')
                        ->options(fn (): array => WhiteCompany::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('scope') === AdministrativeReportFilters::SCOPE_WHITE_COMPANY)
                        ->required(fn (Get $get): bool => $get('scope') === AdministrativeReportFilters::SCOPE_WHITE_COMPANY),
                ]),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->columns(1)->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('administrative-report-form')
                ->livewireSubmitHandler('downloadPdf')
                ->footer([
                    Actions::make([
                        Action::make('downloadPdf')
                            ->label('Generar PDF')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->color('primary')
                            ->submit('downloadPdf')
                            ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('primary')]),
                    ])->fullWidth(false),
                ]),
            View::make('filament.operations.pages.partials.recent-reports'),
        ]);
    }

    /**
     * Genera el PDF en el momento y lo descarga. Arma y dibuja en menos de un segundo
     * incluso con 36 meses, así que no pasa por la cola: el analista no espera turno
     * del worker. Queda una copia en «Mis reportes recientes» por 7 días.
     */
    public function downloadPdf(): ?BinaryFileResponse
    {
        $data = $this->form->getState();
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        try {
            $filters = AdministrativeReportFilters::fromArray($data);
        } catch (InvalidArgumentException $exception) {
            $this->notifyError('Revise el periodo y el alcance', $exception->getMessage());

            return null;
        }

        $userId = (int) $user->getAuthIdentifier();
        $filename = AdministrativeReportPdf::filename();

        SecurityAudit::log('AUDIT_ADMIN_ADMINISTRATIVE_REPORT_REQUESTED', 'administration.administrative-report.pdf', [
            'panel' => 'administration',
            'filters' => $filters->toArray(),
        ], $user);

        try {
            AdministrativeReportPdf::store($filters, $userId, $filename);
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError('No se pudo generar el informe', 'Intente nuevamente. Si el problema continúa, acote el periodo.');

            return null;
        }

        $path = AdministrationReportGenerator::absolutePathFor($userId, $filename);

        return $path === null ? null : response()->download($path, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Solo los informes administrativos del usuario (no los demás reportes del Centro).
     *
     * @return list<array{name: string, label: string, size: string, created_at: \Carbon\CarbonImmutable, format: \App\Enums\OperationReportFormat|null}>
     */
    public function getRecentReportsProperty(): array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return [];
        }

        return array_values(array_filter(
            AdministrationReportGenerator::recent((int) $userId),
            static fn (array $report): bool => str_starts_with($report['name'], 'reporte-administracion-'.AdministrativeReportPdf::FILE_SLUG.'-'),
        ));
    }

    public function downloadUrl(string $filename): string
    {
        return route('administration.reports.download', ['file' => $filename]);
    }

    private function notifyError(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body)->danger()->send();
    }
}
