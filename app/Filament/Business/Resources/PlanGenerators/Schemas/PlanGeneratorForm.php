<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Schemas;

use App\Models\Benefit;
use App\Support\PlanGenerators\PlanGeneratorBrandColor;
use App\Support\PlanGenerators\PlanGeneratorMatrixState;
use App\Support\PlanGenerators\PlanGeneratorPopulationValidator;
use App\Support\PlanGenerators\PlanGeneratorQuotationState;
use App\Support\PlanGenerators\PlanGeneratorQuotationValidator;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class PlanGeneratorForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('planGeneratorFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Identificación')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([
                                Section::make('Identificación del plan')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->description('Nombre y estatus del plan generado.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label('Nombre del plan')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->placeholder('Ej: Plan Ideal Corporativo')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        Select::make('status')
                                                            ->label('Estatus')
                                                            ->options([
                                                                'ACTIVO' => 'ACTIVO',
                                                                'INACTIVO' => 'INACTIVO',
                                                            ])
                                                            ->default('ACTIVO')
                                                            ->required()
                                                            ->native(false),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Propuesta comercial')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Section::make('Propuesta comercial')
                                    ->description('Datos del cliente y contexto comercial para el documento generado.')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextInput::make('control_number')
                                                            ->label('Nro. Control')
                                                            ->required()
                                                            ->maxLength(50)
                                                            ->placeholder('Ej: 2078')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        TextInput::make('client_data')
                                                            ->label('Datos del cliente')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->placeholder('Ej: Distribuidora FT 0214, C.A.')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        DatePicker::make('issued_at')
                                                            ->label('Fecha de emisión')
                                                            ->required()
                                                            ->default(now())
                                                            ->native(false)
                                                            ->displayFormat('d/m/Y')
                                                            ->closeOnDateSelection(),
                                                        TextInput::make('agent_name')
                                                            ->label('Agente')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->placeholder('Ej: Eiram Briceño'),
                                                        TextInput::make('population_summary')
                                                            ->label('Población')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->live(onBlur: true)
                                                            ->placeholder('Ej: 101 personas')
                                                            ->helperText(fn (Get $get): string => PlanGeneratorPopulationValidator::helperText(
                                                                (string) ($get('population_summary') ?? ''),
                                                                (array) ($get('rate_rows') ?? []),
                                                            ))
                                                            ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                                                $message = PlanGeneratorPopulationValidator::validationMessage(
                                                                    (string) $value,
                                                                    (array) ($get('rate_rows') ?? []),
                                                                );

                                                                if ($message !== null) {
                                                                    $fail($message);
                                                                }
                                                            })
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        ColorPicker::make('brand_color')
                                                            ->label('Color de la cotización PDF')
                                                            ->helperText('Se aplica a encabezados, títulos y acentos del documento generado.')
                                                            ->hex()
                                                            ->default(PlanGeneratorBrandColor::DEFAULT)
                                                            ->required()
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Cuerpo de la cotización')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->schema([
                                Section::make('Cuerpo de la cotización')
                                    ->description('Defina cuántas páginas tendrá el PDF, indique dónde va el plan generado y cargue una imagen en cada página restante.')
                                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextInput::make('quotation_page_count')
                                                            ->label('Número de páginas')
                                                            ->numeric()
                                                            ->minValue(1)
                                                            ->maxValue(30)
                                                            ->live()
                                                            ->placeholder('Ej: 5')
                                                            ->helperText(fn (Get $get): string => PlanGeneratorQuotationValidator::helperText(
                                                                filled($get('quotation_page_count')) ? (int) $get('quotation_page_count') : null,
                                                                filled($get('plan_page_number')) ? (int) $get('plan_page_number') : null,
                                                                (array) ($get('quotation_pages') ?? []),
                                                            ))
                                                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                                                $count = max(0, (int) $state);
                                                                $planPageNumber = filled($get('plan_page_number'))
                                                                    ? (int) $get('plan_page_number')
                                                                    : null;

                                                                if ($count > 0 && ($planPageNumber === null || $planPageNumber < 1 || $planPageNumber > $count)) {
                                                                    $planPageNumber = 1;
                                                                    $set('plan_page_number', 1);
                                                                }

                                                                if ($count === 0) {
                                                                    $set('plan_page_number', null);
                                                                    $set('quotation_pages', []);

                                                                    return;
                                                                }

                                                                $set('quotation_pages', PlanGeneratorQuotationState::syncImagePagesForQuotation(
                                                                    (array) ($get('quotation_pages') ?? []),
                                                                    $count,
                                                                    $planPageNumber,
                                                                ));
                                                            })
                                                            ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                                                $message = PlanGeneratorQuotationValidator::validationMessage(
                                                                    filled($value) ? (int) $value : null,
                                                                    filled($get('plan_page_number')) ? (int) $get('plan_page_number') : null,
                                                                    (array) ($get('quotation_pages') ?? []),
                                                                );

                                                                if ($message !== null) {
                                                                    $fail($message);
                                                                }
                                                            }),
                                                        Select::make('plan_page_number')
                                                            ->label('Página del plan generado')
                                                            ->options(function (Get $get): array {
                                                                $count = (int) ($get('quotation_page_count') ?? 0);

                                                                if ($count < 1) {
                                                                    return [];
                                                                }

                                                                return collect(range(1, $count))
                                                                    ->mapWithKeys(fn (int $pageNumber): array => [
                                                                        $pageNumber => "Página {$pageNumber}",
                                                                    ])
                                                                    ->all();
                                                            })
                                                            ->native(false)
                                                            ->live()
                                                            ->placeholder('Seleccione la página')
                                                            ->visible(fn (Get $get): bool => (int) ($get('quotation_page_count') ?? 0) > 0)
                                                            ->required(fn (Get $get): bool => (int) ($get('quotation_page_count') ?? 0) > 0)
                                                            ->helperText('En esa página se renderizarán las matrices del plan; no requiere cargar imagen.')
                                                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                                                $planPageNumber = filled($state) ? (int) $state : null;
                                                                $count = (int) ($get('quotation_page_count') ?? 0);

                                                                if ($count < 1 || $planPageNumber === null || $planPageNumber < 1) {
                                                                    return;
                                                                }

                                                                $set('quotation_pages', PlanGeneratorQuotationState::syncImagePagesForQuotation(
                                                                    (array) ($get('quotation_pages') ?? []),
                                                                    $count,
                                                                    $planPageNumber,
                                                                ));
                                                            }),
                                                    ]),
                                                Placeholder::make('quotation_plan_page_notice')
                                                    ->label('Página reservada para el plan')
                                                    ->content(fn (Get $get): string => filled($get('plan_page_number'))
                                                        ? 'La página '.(int) $get('plan_page_number').' mostrará el plan generado. Solo cargue imágenes para las páginas listadas abajo.'
                                                        : 'Seleccione arriba en qué página debe aparecer el plan generado.')
                                                    ->visible(fn (Get $get): bool => (int) ($get('quotation_page_count') ?? 0) > 0),
                                                Repeater::make('quotation_pages')
                                                    ->label('Imágenes por página')
                                                    ->hiddenLabel()
                                                    ->addable(false)
                                                    ->deletable(false)
                                                    ->reorderable(false)
                                                    ->visible(fn (Get $get): bool => (int) ($get('quotation_page_count') ?? 0) > 0
                                                        && filled($get('plan_page_number')))
                                                    ->itemLabel(fn (array $state): string => 'Página '.((int) ($state['page_number'] ?? 0)))
                                                    ->schema([
                                                        Hidden::make('page_number')
                                                            ->dehydrated(),
                                                        FileUpload::make('image')
                                                            ->label('Imagen de la página')
                                                            ->image()
                                                            ->disk('public')
                                                            ->directory('plan-generator-quotation')
                                                            ->visibility('public')
                                                            ->imageEditor()
                                                            ->required()
                                                            ->helperText('Esta imagen se convertirá en una página completa del PDF.'),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Columnas del plan')
                            ->icon(Heroicon::OutlinedViewColumns)
                            ->schema([
                                Section::make('Columnas del plan')
                                    ->description('Las columnas definidas aquí se replican automáticamente en beneficios y tarifas, en el mismo orden.')
                                    ->icon(Heroicon::OutlinedViewColumns)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Repeater::make('columns')
                                                    ->label('Columnas')
                                                    ->hiddenLabel()
                                                    ->addActionLabel('Agregar columna')
                                                    ->reorderable()
                                                    ->collapsible()
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                                        $columns = PlanGeneratorMatrixState::normalizeColumns((array) ($get('columns') ?? []));
                                                        $set('rows', PlanGeneratorMatrixState::ensureRowsHaveCells(
                                                            (array) ($get('rows') ?? []),
                                                            $columns,
                                                        ));
                                                        $set('rate_rows', PlanGeneratorMatrixState::ensureRateRowsHaveCells(
                                                            (array) ($get('rate_rows') ?? []),
                                                            $columns,
                                                        ));
                                                    })
                                                    ->itemLabel(fn (array $state): string => filled($state['header_label'] ?? null)
                                                        ? (string) $state['header_label']
                                                        : 'Nueva columna')
                                                    ->schema([
                                                        Hidden::make('column_key')
                                                            ->default(fn (): string => (string) Str::uuid())
                                                            ->dehydrated(),
                                                        TextInput::make('header_label')
                                                            ->label('Encabezado de columna')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->live(onBlur: true)
                                                            ->placeholder('Ej: Ideal US$ 5K, Inicial')
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->defaultItems(0)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Matrices del plan')
                            ->icon(Heroicon::OutlinedTableCells)
                            ->schema([
                                Section::make('Matrices del plan')
                                    ->description('Beneficios y tarifa individual anual comparten las mismas columnas del plan, alineadas verticalmente.')
                                    ->icon(Heroicon::OutlinedTableCells)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Hidden::make('rows')
                                                    ->default([])
                                                    ->dehydrated()
                                                    ->columnSpanFull(),
                                                Hidden::make('rate_rows')
                                                    ->default([])
                                                    ->dehydrated()
                                                    ->columnSpanFull(),
                                                View::make('filament.business.plan-generators.stacked-matrices-editor')
                                                    ->viewData(fn (Get $get): array => [
                                                        'columns' => PlanGeneratorMatrixState::normalizeColumns((array) ($get('columns') ?? [])),
                                                        'rows' => (array) ($get('rows') ?? []),
                                                        'rateRows' => (array) ($get('rate_rows') ?? []),
                                                        'benefitOptions' => Benefit::query()
                                                            ->whereNotNull('description')
                                                            ->where('description', '!=', '')
                                                            ->orderBy('description')
                                                            ->pluck('description')
                                                            ->map(fn (string $description): string => (string) $description)
                                                            ->unique()
                                                            ->values()
                                                            ->all(),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
