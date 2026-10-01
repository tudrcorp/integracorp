<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages;

use App\Filament\Actions\ImportAction;
use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Filament\Imports\PlanGeneratorPopulationImporter;
use App\Models\PlanGenerator;
use App\Models\PlanGeneratorPopulation;
use App\Support\Filament\FilamentIosButton;
use App\Support\PlanGenerators\PlanGeneratorCoverageAssignment;
use App\Support\PlanGenerators\PlanGeneratorPopulationStatus;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use InvalidArgumentException;

/**
 * Paso 2 de la pre-afiliación corporativa desde el generador de planes: cargar
 * la población por importación, igual que el padrón de una cotización
 * corporativa en Negocios.
 *
 * El padrón se guarda colgado del plan generado y no se copia a la afiliación
 * hasta que el analista la crea. El botón que continúa al formulario solo se
 * habilita cuando hay población y el import cerró: crear la afiliación con el
 * padrón a medio importar dejaría afiliados fuera sin aviso.
 *
 * Con varias coberturas elegidas, el analista ubica a mano a cada persona en la
 * suya. La regla de edad vive en `PlanGeneratorCoverageAssignment`; esta página
 * solo la expone y nunca asigna por su cuenta.
 */
class PreAffiliationPopulation extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = PlanGeneratorResource::class;

    protected static ?string $title = 'Población de la pre-afiliación';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.business.resources.plan-generators.pages.pre-affiliation-population';

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/pre-affiliation-population';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);

        $this->ensureCorporateSession();
    }

    /**
     * Sin sesión corporativa activa para este plan no hay coberturas elegidas y
     * la afiliación no podría armarse. Se devuelve al analista a la ficha para
     * que pase por «Aprobar cotización».
     */
    protected function ensureCorporateSession(): void
    {
        /** @var PlanGenerator $plan */
        $plan = $this->getRecord();

        $payload = PlanGeneratorPreAffiliationSession::get();
        $activePlanId = is_array($payload) ? (int) ($payload['plan_generator_id'] ?? 0) : 0;
        $type = is_array($payload) ? ($payload['type'] ?? null) : null;

        if ($type === PlanGeneratorPreAffiliationSession::TYPE_CORPORATE && $activePlanId === (int) $plan->getKey()) {
            return;
        }

        Notification::make()
            ->title('Seleccione primero las coberturas')
            ->body('Abra «Aprobar cotización» y elija las coberturas del grupo corporativo antes de cargar la población.')
            ->warning()
            ->send();

        $this->redirect(PlanGeneratorResource::getUrl('view', ['record' => $plan->getKey()]));
    }

    public function getTitle(): string|Htmlable
    {
        return 'Población de la pre-afiliación';
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var PlanGenerator $plan */
        $plan = $this->getRecord();

        return 'Paso 2 de 2 · '.((string) $plan->name).' · Nro. Control '.((string) $plan->control_number);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make('importPopulation')
                ->label('Importar población')
                ->icon('fluentui-database-arrow-up-20')
                ->color('success')
                ->modalHeading('Importar población')
                ->modalDescription('Mismo archivo que el padrón de cotizaciones corporativas. Separador punto y coma (;), máximo 5 MB.')
                ->importer(PlanGeneratorPopulationImporter::class)
                ->csvDelimiter(';')
                ->job(ImportCsv::class)
                ->chunkSize(100)
                ->options(fn (): array => [
                    'plan_generator_id' => $this->getRecord()->getKey(),
                ])
                ->fileRules([
                    File::types(['csv', 'txt'])->max(5120),
                ])
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
            Action::make('clearPopulation')
                ->label('Vaciar población')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Vaciar la población cargada')
                ->modalDescription('Se borran todas las filas importadas de este plan generado. La afiliación corporativa todavía no existe, así que no se pierde ninguna afiliación; habrá que volver a importar el padrón.')
                ->modalSubmitActionLabel('Vaciar población')
                ->visible(fn (): bool => PlanGeneratorPopulationStatus::importedCount($this->getRecord()) > 0)
                ->action(function (): void {
                    /** @var PlanGenerator $plan */
                    $plan = $this->getRecord();

                    $deleted = $plan->populations()->delete();
                    $plan->forceFill(['population_import_id' => null])->save();
                    PlanGeneratorCoverageAssignment::flush();

                    SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_POPULATION_CLEARED', 'business.plan-generators.population.clear', [
                        'plan_generator_id' => $plan->getKey(),
                        'deleted_rows' => $deleted,
                    ]);

                    Notification::make()
                        ->title('Población vaciada')
                        ->body($deleted.' fila(s) eliminada(s). Importe el padrón corregido para continuar.')
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                ]),
            Action::make('continueToAffiliation')
                ->label('Continuar a la pre-afiliación')
                ->icon(Heroicon::OutlinedArrowRight)
                ->color('primary')
                ->disabled(fn (): bool => ! PlanGeneratorPopulationStatus::canContinue($this->getRecord()))
                ->tooltip(fn (): ?string => PlanGeneratorPopulationStatus::blockedReason($this->getRecord()))
                ->action(function (): void {
                    /** @var PlanGenerator $plan */
                    $plan = $this->getRecord();

                    if (! PlanGeneratorPopulationStatus::canContinue($plan)) {
                        Notification::make()
                            ->title('Todavía no se puede continuar')
                            ->body((string) PlanGeneratorPopulationStatus::blockedReason($plan))
                            ->warning()
                            ->send();

                        return;
                    }

                    $report = PlanGeneratorCoverageAssignment::report($plan);
                    $declared = PlanGeneratorPopulationStatus::declaredPopulation();

                    // El formulario de afiliación toma personas y montos de la
                    // sesión: se reemplaza lo cotizado por lo realmente ubicado.
                    PlanGeneratorPreAffiliationSession::storeCorporateAssignment($plan, $report);

                    SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_POPULATION_READY', 'business.plan-generators.population.continue', [
                        'plan_generator_id' => $plan->getKey(),
                        'imported_rows' => $report['total'],
                        'declared_population' => $declared,
                        'persons_by_coverage' => array_map(
                            static fn (array $amounts): int => $amounts['persons'],
                            $report['by_coverage'],
                        ),
                        'assignment_signature' => $report['signature'],
                    ]);

                    $this->redirect(AffiliationCorporateResource::getUrl('create', panel: 'business'));
                })
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
            Action::make('backToPlan')
                ->label('Volver al plan')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => PlanGeneratorResource::getUrl('view', ['record' => $this->getRecord()->getKey()]))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getRecord()->populations()->getQuery())
            ->heading('Población cargada')
            ->description('Ubique a cada persona en la cobertura que le corresponde: seleccione varias y use «Asignar a cobertura», o «Asignar» en la fila. Solo se admite una cobertura cuyo rango de edad incluya la edad del padrón.')
            ->emptyStateHeading('Sin población cargada')
            ->emptyStateDescription('Use «Importar población» para subir el padrón en CSV.')
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->striped()
            ->defaultSort('last_name')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            // Solo mientras entra el padrón: con la tabla quieta, el informe de
            // coberturas no se recalcula cada 5 segundos.
            ->poll(fn (): ?string => PlanGeneratorPopulationStatus::isImportRunning($this->getRecord()) ? '5s' : null)
            ->columns([
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nro_identificacion')
                    ->label('Cédula')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('column_key')
                    ->label('Cobertura')
                    ->badge()
                    ->state(fn (PlanGeneratorPopulation $record): string => $this->coverageCell($record)['label'])
                    ->color(fn (PlanGeneratorPopulation $record): string => $this->coverageCell($record)['color'])
                    ->icon(fn (PlanGeneratorPopulation $record): ?string => $this->coverageCell($record)['icon'])
                    ->description(fn (PlanGeneratorPopulation $record): ?string => $this->coverageCell($record)['description'])
                    ->tooltip(fn (PlanGeneratorPopulation $record): ?string => $this->coverageCell($record)['tooltip']),
                TextColumn::make('eligible_coverages')
                    ->label('Admitida en')
                    ->state(fn (PlanGeneratorPopulation $record): string => $this->eligibleCoveragesLine($record))
                    ->color(fn (string $state): ?string => str_starts_with($state, 'Ninguna') ? 'danger' : 'gray')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('position_company')
                    ->label('Cargo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Correo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('coverage')
                    ->label('Cobertura')
                    ->placeholder('Todas')
                    ->options(fn (): array => [
                        '__unassigned' => 'Sin asignar',
                        ...array_map(
                            static fn (array $coverage): string => $coverage['label'],
                            PlanGeneratorCoverageAssignment::report($this->getRecord())['coverages'],
                        ),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        return $value === '__unassigned'
                            ? $query->whereNull('column_key')
                            : $query->where('column_key', $value);
                    }),
            ])
            ->recordActions([
                Action::make('assignCoverage')
                    ->label(fn (PlanGeneratorPopulation $record): string => $record->column_key === null ? 'Asignar' : 'Cambiar')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('primary')
                    ->button()
                    ->size(Size::Small)
                    ->disabled(fn (PlanGeneratorPopulation $record): bool => $this->ageFilteredOptions($record) === [])
                    ->tooltip(fn (PlanGeneratorPopulation $record): ?string => $this->ageFilteredOptions($record) === []
                        ? 'Su edad no cabe en ninguna de las coberturas elegidas.'
                        : null)
                    ->modalHeading(fn (PlanGeneratorPopulation $record): string => 'Cobertura de '.trim($record->last_name.' '.$record->first_name))
                    ->modalDescription(fn (PlanGeneratorPopulation $record): string => 'Edad en el padrón: '.((string) $record->age).' años. Solo se ofrecen las coberturas cuyo rango de edad la admite.')
                    ->modalSubmitActionLabel('Guardar cobertura')
                    ->modalWidth(Width::Large)
                    ->fillForm(fn (PlanGeneratorPopulation $record): array => ['column_key' => $record->column_key])
                    ->schema(fn (PlanGeneratorPopulation $record): array => [
                        Select::make('column_key')
                            ->label('Cobertura')
                            ->options($this->ageFilteredOptions($record))
                            ->native(false)
                            ->required()
                            ->validationMessages(['required' => 'Elija la cobertura de esta persona.']),
                    ])
                    ->action(function (PlanGeneratorPopulation $record, array $data): void {
                        $this->assignAndNotify([$record->getKey()], (string) ($data['column_key'] ?? ''));
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('assignCoverageBulk')
                    ->label('Asignar a cobertura')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('primary')
                    ->modalHeading('Asignar a cobertura')
                    ->modalDescription('Las personas seleccionadas cuya edad no caiga en un rango de la cobertura elegida no se asignan: se le indicará quiénes y por qué.')
                    ->modalSubmitActionLabel('Asignar')
                    ->modalWidth(Width::Large)
                    ->schema(fn (): array => [
                        Select::make('column_key')
                            ->label('Cobertura')
                            ->options(PlanGeneratorCoverageAssignment::coverageOptions($this->getRecord()))
                            ->native(false)
                            ->required()
                            ->validationMessages(['required' => 'Elija la cobertura.']),
                    ])
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, array $data): void {
                        $this->assignAndNotify($records->modelKeys(), (string) ($data['column_key'] ?? ''));
                    }),
                BulkAction::make('clearCoverageBulk')
                    ->label('Quitar cobertura')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar la cobertura asignada')
                    ->modalDescription('Las personas seleccionadas vuelven a «Sin asignar». No se borra a nadie del padrón, pero habrá que ubicarlas de nuevo antes de continuar.')
                    ->modalSubmitActionLabel('Quitar cobertura')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $cleared = PlanGeneratorCoverageAssignment::unassign($this->getRecord(), $records->modelKeys());

                        Notification::make()
                            ->title($cleared > 0 ? 'Cobertura retirada' : 'Nada que retirar')
                            ->body($cleared > 0
                                ? $cleared.' persona(s) quedaron sin asignar.'
                                : 'Las personas seleccionadas ya estaban sin asignar.')
                            ->color($cleared > 0 ? 'success' : 'gray')
                            ->send();
                    }),
            ])
            ->checkIfRecordIsSelectableUsing(fn (): bool => ! PlanGeneratorPopulationStatus::isImportRunning($this->getRecord()));
    }

    /**
     * Asigna y le cuenta al analista quién quedó afuera y por qué.
     *
     * @param  array<int, int|string>  $populationIds
     */
    protected function assignAndNotify(array $populationIds, string $columnKey): void
    {
        /** @var PlanGenerator $plan */
        $plan = $this->getRecord();

        try {
            $result = PlanGeneratorCoverageAssignment::assign($plan, $populationIds, $columnKey, Auth::user()?->name);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('No se pudo asignar la cobertura')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $label = PlanGeneratorCoverageAssignment::report($plan)['coverages'][$columnKey]['label'] ?? 'la cobertura';

        if ($result['assigned'] > 0) {
            Notification::make()
                ->title('Cobertura asignada')
                ->body($result['assigned'].' persona(s) quedaron en '.$label.'.')
                ->success()
                ->send();
        }

        if ($result['rejected'] !== []) {
            $lines = array_map(
                static fn (array $rejected): string => e('• '.$rejected['name'].': '.$rejected['reason'].'.'),
                array_slice($result['rejected'], 0, 8),
            );

            if (count($result['rejected']) > 8) {
                $lines[] = '• … y '.(count($result['rejected']) - 8).' más.';
            }

            Notification::make()
                ->title(count($result['rejected']).' persona(s) no se asignaron a '.$label)
                ->body(implode('<br>', $lines))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /**
     * Estado de la cobertura de una fila, resuelto contra el informe del
     * request para no repetir consultas por fila.
     *
     * @return array{label: string, color: string, icon: string|null, description: string|null, tooltip: string|null}
     */
    protected function coverageCell(PlanGeneratorPopulation $record): array
    {
        $report = PlanGeneratorCoverageAssignment::report($this->getRecord());

        if ($record->column_key === null) {
            return [
                'label' => 'Sin asignar',
                'color' => 'warning',
                'icon' => 'heroicon-m-exclamation-triangle',
                'description' => null,
                'tooltip' => 'Use «Asignar» o seleccione varias personas y «Asignar a cobertura».',
            ];
        }

        $coverage = $report['coverages'][$record->column_key] ?? null;
        $placement = $report['placements'][(int) $record->getKey()] ?? null;

        if ($coverage === null || $placement === null) {
            return [
                'label' => $coverage['label'] ?? 'Cobertura retirada',
                'color' => 'danger',
                'icon' => 'heroicon-m-x-circle',
                'description' => 'No corresponde',
                'tooltip' => $coverage === null
                    ? 'Su cobertura ya no forma parte de esta pre-afiliación. Reasígnela.'
                    : 'Su edad ya no cae en ningún rango de '.$coverage['label'].' ('.PlanGeneratorCoverageAssignment::rangesLine($coverage).'). Reasígnela.',
            ];
        }

        return [
            'label' => $coverage['label'],
            'color' => 'success',
            'icon' => 'heroicon-m-check-circle',
            'description' => $placement['range_label'].' · US$ '.number_format($placement['fee'], 2, ',', '.').' anual',
            'tooltip' => null,
        ];
    }

    protected function eligibleCoveragesLine(PlanGeneratorPopulation $record): string
    {
        $age = PlanGeneratorCoverageAssignment::parseAge($record->age);

        if ($age === null) {
            return 'Ninguna — edad inválida en el padrón';
        }

        $labels = [];

        foreach (PlanGeneratorCoverageAssignment::report($this->getRecord())['coverages'] as $coverage) {
            if ($coverage['defects'] === [] && PlanGeneratorCoverageAssignment::rangeForAge($coverage, $age) !== null) {
                $labels[] = $coverage['label'];
            }
        }

        return $labels === [] ? 'Ninguna — falla en la creación del plan' : implode(' · ', $labels);
    }

    /**
     * @return array<string, string>
     */
    protected function ageFilteredOptions(PlanGeneratorPopulation $record): array
    {
        return PlanGeneratorCoverageAssignment::coverageOptions(
            $this->getRecord(),
            PlanGeneratorCoverageAssignment::parseAge($record->age),
            filterByAge: true,
        );
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-plan-generator-pre-affiliation-population-page',
            'fi-resource-'.str_replace('/', '-', static::getResource()::getSlug()),
            'fi-resource-record-'.$this->getRecord()->getKey(),
        ];
    }
}
