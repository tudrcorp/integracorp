<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Tables\Actions;

use App\Enums\PlanGeneratorPopulationUnit;
use App\Filament\Business\Resources\PlanGenerators\Actions\AdjustRateAmountsAction;
use App\Models\Benefit;
use App\Models\PlanGenerator;
use App\Support\PlanGenerators\PlanGeneratorBrandColor;
use App\Support\PlanGenerators\PlanGeneratorMatrixState;
use App\Support\PlanGenerators\PlanGeneratorPopulationValidator;
use App\Support\PlanGenerators\PlanGeneratorTemplateCloner;
use App\Support\SecurityAudit;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Deriva una cotización nueva a partir de un registro de «Planes Generados».
 *
 * El analista selecciona un solo registro, ajusta en la modal las columnas, los
 * beneficios y las tarifas que trae la plantilla, y obtiene otra cotización
 * colgada del mismo registro base. La plantilla no se toca, así que el mismo
 * registro sirve para armar tantas cotizaciones distintas como haga falta.
 */
final class DeriveQuotationBulkAction
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function make(): BulkAction
    {
        return BulkAction::make('deriveQuotation')
            ->label('Generar cotización desde plantilla')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('success')
            ->modalWidth(Width::ScreenTwoExtraLarge)
            ->modalHeading('Generar cotización desde plantilla')
            ->modalDescription('Ajuste columnas, beneficios y tarifas. Se creará una cotización nueva colgada del mismo registro base; la plantilla no se modifica.')
            ->modalSubmitActionLabel('Crear cotización')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->deselectRecordsAfterCompletion()
            ->mountUsing(function (BulkAction $action, ?Schema $schema, Collection $records): void {
                $template = self::resolveSingleTemplate($action, $records);

                $schema?->fill(PlanGeneratorTemplateCloner::formStateFromTemplate($template));
            })
            ->schema(self::schema())
            ->action(function (BulkAction $action, array $data, Collection $records, ?Schema $schema): void {
                $template = self::resolveSingleTemplate($action, $records);
                $formState = self::matrixStateFromModal($action, $data, $schema);

                self::assertMatrixIsUsable($action, $formState);

                $derived = PlanGeneratorTemplateCloner::create(
                    $template,
                    $formState,
                    Auth::user()?->name,
                );

                SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_QUOTATION_DERIVED', 'business.plan-generators.derive-quotation', [
                    'template_id' => $template->getKey(),
                    'base_id' => $derived->parent_id,
                    'derived_id' => $derived->getKey(),
                    'control_number' => $derived->control_number,
                    'client_data' => $derived->client_data,
                ]);

                Notification::make()
                    ->title('Cotización creada')
                    ->body(sprintf(
                        'Nro. Control %s para «%s». Quedó agrupada bajo el registro base en la tabla.',
                        (string) $derived->control_number,
                        (string) $derived->client_data,
                    ))
                    ->success()
                    ->send();
            });
    }

    /**
     * La acción trabaja sobre un único registro: la matriz de dos plantillas
     * distintas no se puede mezclar en una sola modal. `halt()` evita que la
     * modal se abra con una selección que no sirve.
     */
    private static function resolveSingleTemplate(BulkAction $action, Collection $records): PlanGenerator
    {
        if ($records->count() !== 1) {
            Notification::make()
                ->title('Seleccione un solo registro')
                ->body('Esta acción usa un registro como plantilla. Marque únicamente la cotización que quiere replicar.')
                ->warning()
                ->send();

            $action->halt();
        }

        /** @var PlanGenerator $template */
        $template = $records->first();

        return $template;
    }

    /**
     * Estado de la matriz tal como quedó en la modal.
     *
     * Las tres claves de la matriz son campos `Hidden` que el editor escribe
     * directo sobre la propiedad Livewire, así que se leen del estado en crudo
     * y no del arreglo validado —igual que hace `matrixFormStateForPersistence()`
     * en las páginas de Crear y Editar—.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function matrixStateFromModal(BulkAction $action, array $data, ?Schema $schema): array
    {
        $rawState = [];

        if ($schema !== null && filled($statePath = $schema->getStatePath())) {
            $rawState = (array) (data_get($action->getLivewire(), $statePath) ?? []);
        }

        // `keyedColumns()` y no `normalizeColumns()`: conserva las columnas sin
        // encabezado para poder avisar de ellas. Descartarlas acá las haría
        // desaparecer de la cotización en silencio.
        $columns = PlanGeneratorMatrixState::keyedColumns(
            (array) ($rawState['columns'] ?? $data['columns'] ?? []),
        );

        return [
            ...$data,
            'columns' => $columns,
            'rows' => PlanGeneratorMatrixState::ensureRowsHaveCells(
                (array) ($rawState['rows'] ?? $data['rows'] ?? []),
                $columns,
            ),
            'rate_rows' => PlanGeneratorMatrixState::ensureRateRowsHaveCells(
                (array) ($rawState['rate_rows'] ?? $data['rate_rows'] ?? []),
                $columns,
            ),
        ];
    }

    /**
     * Una cotización sin columnas, sin beneficios o sin rangos etarios genera
     * un PDF vacío y descuadra el total grupal. Se corta antes de escribir en
     * base de datos y la modal queda abierta con todo lo cargado.
     *
     * @param  array<string, mixed>  $formState
     */
    private static function assertMatrixIsUsable(BulkAction $action, array $formState): void
    {
        $columns = (array) ($formState['columns'] ?? []);

        $unnamedColumns = count($columns) - count(PlanGeneratorMatrixState::normalizeColumns($columns));

        $benefits = collect((array) ($formState['rows'] ?? []))
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['benefit_label'] ?? null));

        $ageRanges = collect((array) ($formState['rate_rows'] ?? []))
            ->filter(fn (mixed $rateRow): bool => is_array($rateRow) && filled($rateRow['age_range_label'] ?? null));

        $problems = [];

        if ($columns === []) {
            $problems[] = 'al menos una columna del plan con nombre';
        } elseif ($unnamedColumns > 0) {
            $problems[] = $unnamedColumns === 1
                ? 'ponerle nombre a 1 columna del plan'
                : 'ponerle nombre a '.$unnamedColumns.' columnas del plan';
        }

        if ($benefits->isEmpty()) {
            $problems[] = 'al menos un beneficio seleccionado';
        }

        if ($ageRanges->isEmpty()) {
            $problems[] = 'al menos un rango etario con nombre';
        }

        if ($problems === []) {
            return;
        }

        Notification::make()
            ->title('La matriz está incompleta')
            ->body('Falta '.implode(', ', $problems).'. Complete la matriz y vuelva a intentarlo.')
            ->danger()
            ->persistent()
            ->send();

        $action->halt();
    }

    /**
     * @return array<int, mixed>
     */
    private static function schema(): array
    {
        return [
            Hidden::make('template_id'),
            Hidden::make('base_id'),

            Section::make('Datos de la cotización derivada')
                ->icon(Heroicon::OutlinedDocumentText)
                ->description('Vienen de la plantilla. Cambie lo que distinga a esta cotización: el Nro. Control ya viene sugerido y libre.')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->schema([
                    Grid::make(1)
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                        ->schema([
                            Grid::make()
                                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                                ->schema([
                                    TextInput::make('control_number')
                                        ->label('Nro. Control')
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('Ej: 2045-1')
                                        ->helperText('Identifica la cotización ante el cliente; debe ser único.')
                                        ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                                            if (blank($value)) {
                                                return;
                                            }

                                            $taken = PlanGenerator::query()
                                                ->where('control_number', (string) $value)
                                                ->exists();

                                            if ($taken) {
                                                $fail('Ya existe una cotización con el Nro. Control '.$value.'.');
                                            }
                                        }),
                                    TextInput::make('name')
                                        ->label('Nombre del plan')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Ej: PLAN ACCIDENTES - AVIOR')
                                        ->afterStateUpdatedJs(<<<'JS'
                                            $set('name', $state.toUpperCase());
                                        JS)
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 2]),
                                    Select::make('status')
                                        ->label('Estatus')
                                        ->options([
                                            'PRE-APROBADO' => 'PRE-APROBADO',
                                            'ACTIVO' => 'ACTIVO',
                                            'INACTIVO' => 'INACTIVO',
                                        ])
                                        ->default('PRE-APROBADO')
                                        ->required()
                                        ->native(false),
                                    TextInput::make('client_data')
                                        ->label('Datos del cliente')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Ej: AVIOR AIRLINES, C.A.')
                                        ->afterStateUpdatedJs(<<<'JS'
                                            $set('client_data', $state.toUpperCase());
                                        JS)
                                        ->columnSpan(['default' => 1, 'md' => 2]),
                                    TextInput::make('agent_name')
                                        ->label('Agente')
                                        ->required()
                                        ->maxLength(255)
                                        ->afterStateUpdatedJs(<<<'JS'
                                            $set('agent_name', $state.toUpperCase());
                                        JS),
                                    DatePicker::make('issued_at')
                                        ->label('Fecha de emisión')
                                        ->required()
                                        ->default(now())
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->closeOnDateSelection(),
                                    ToggleButtons::make('population_unit')
                                        ->label('¿Qué representa el total?')
                                        ->options(PlanGeneratorPopulationUnit::options())
                                        ->default(PlanGeneratorPopulationUnit::Poblacion->value)
                                        ->inline()
                                        ->grouped()
                                        ->live()
                                        ->required()
                                        ->columnSpan(['default' => 1, 'md' => 2]),
                                    TextInput::make('population_summary')
                                        ->label(fn (Get $get): string => 'Total ('.PlanGeneratorPopulationUnit::resolve($get('population_unit'))->label().')')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->placeholder('Ej: 1000')
                                        ->helperText(fn (Get $get): string => PlanGeneratorPopulationValidator::helperText(
                                            (string) ($get('population_summary') ?? ''),
                                            (array) ($get('rate_rows') ?? []),
                                            $get('population_unit'),
                                        ))
                                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                            $message = PlanGeneratorPopulationValidator::validationMessage(
                                                (string) $value,
                                                (array) ($get('rate_rows') ?? []),
                                                $get('population_unit'),
                                            );

                                            if ($message !== null) {
                                                $fail($message);
                                            }
                                        }),
                                    ColorPicker::make('brand_color')
                                        ->label('Color de la cotización PDF')
                                        ->hex()
                                        ->default(PlanGeneratorBrandColor::DEFAULT)
                                        ->required(),
                                ]),
                        ]),
                ]),

            Section::make('Matriz de la cotización')
                ->icon(Heroicon::OutlinedTableCells)
                ->description('Quite o agregue columnas, beneficios y rangos etarios. El total grupal se recalcula solo.')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->schema([
                    Grid::make(1)
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                        ->schema([
                            Hidden::make('columns')->default([])->dehydrated(),
                            Hidden::make('rows')->default([])->dehydrated(),
                            Hidden::make('rate_rows')->default([])->dehydrated(),
                            Toggle::make('include_monthly_total')
                                ->label('Incluir cálculo mensual')
                                ->helperText('Muestra la fila «Total Mensual» en la tabla de total grupal (tarifa anual ÷ 12).')
                                ->live()
                                ->inline(false)
                                ->onColor('primary')
                                ->offColor('gray')
                                ->columnSpanFull(),
                            View::make('filament.business.plan-generators.stacked-matrices-editor')
                                // La clave es obligatoria: Filament resuelve una
                                // acción registrada en un componente por ella.
                                ->key('derivedQuotationMatrixEditor')
                                // El ajuste global de tarifas también está a mano
                                // al derivar: el caso típico es replicar la
                                // plantilla con otro precio.
                                ->registerActions([
                                    AdjustRateAmountsAction::make(),
                                ])
                                ->viewData(fn (Get $get): array => [
                                    'manageColumns' => true,
                                    'columns' => PlanGeneratorMatrixState::keyedColumns((array) ($get('columns') ?? [])),
                                    'rows' => (array) ($get('rows') ?? []),
                                    'rateRows' => (array) ($get('rate_rows') ?? []),
                                    'populationUnitLabel' => PlanGeneratorPopulationUnit::resolve($get('population_unit'))->label(),
                                    'includeMonthlyTotal' => (bool) $get('include_monthly_total'),
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
        ];
    }
}
