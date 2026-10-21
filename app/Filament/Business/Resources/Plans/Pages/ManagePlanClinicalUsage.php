<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Enums\ClinicalUsageAccessContext;
use App\Enums\PlanPricingMode;
use App\Filament\Business\Concerns\InteractsWithClinicalUsageAccessGate;
use App\Filament\Business\Resources\Plans\PlanResource;
use App\Models\Benefit;
use App\Models\Coverage;
use App\Models\Plan;
use App\Models\PlanClinicalUsageEvent;
use App\Models\User;
use App\Support\ClinicalEntitlements\PlanClinicalCompleteness;
use App\Support\ClinicalEntitlements\PlanClinicalUsageConflictException;
use App\Support\ClinicalEntitlements\PlanClinicalUsageRowSchema;
use App\Support\ClinicalEntitlements\PlanClinicalUsageTrace;
use App\Support\ClinicalEntitlements\PlanClinicalUsageWorkbench;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Throwable;

class ManagePlanClinicalUsage extends Page
{
    use InteractsWithClinicalUsageAccessGate;
    use InteractsWithRecord;

    protected static string $resource = PlanResource::class;

    protected static ?string $title = 'Uso clínico del plan';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.business.resources.plans.pages.manage-plan-clinical-usage';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Huella de lo guardado al abrir la pantalla. Si al guardar no coincide,
     * otro usuario guardó en medio y no se pisa su trabajo.
     */
    #[Locked]
    public string $loadedFingerprint = '';

    /**
     * Plan desde el que se copió en esta visita (queda en la traza al guardar).
     */
    #[Locked]
    public ?int $copiedFromPlanId = null;

    #[Locked]
    public bool $hasUnsavedWork = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/uso-clinico';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
        $this->bootClinicalUsageAccessGate();
        $this->fillForm();
    }

    protected function getHeaderActions(): array
    {
        if (! $this->clinicalUsageIsUnlocked()) {
            return $this->clinicalUsageAccessHeaderActions();
        }

        return [
            ActionGroup::make([
                $this->copyFromPlanAction(),
                $this->addBenefitAction(),
                $this->historyAction(),
            ])
                ->label('Acciones')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->button()
                ->color('gray'),
        ];
    }

    protected function clinicalUsageAccessContext(): ClinicalUsageAccessContext
    {
        return ClinicalUsageAccessContext::PlanUsage;
    }

    protected function clinicalUsageAccessBlocksPage(): bool
    {
        return true;
    }

    protected function clinicalUsageAccessRecordId(): ?int
    {
        $plan = $this->getRecord();

        return $plan instanceof Plan ? (int) $plan->id : null;
    }

    protected function clinicalUsageAccessSubjectLabel(): ?string
    {
        $plan = $this->getRecord();
        if (! $plan instanceof Plan) {
            return $this->clinicalUsageAccessContext()->label();
        }

        $name = filled($plan->description) ? (string) $plan->description : (string) $plan->code;

        return 'Plan '.$name;
    }

    protected function onClinicalUsageUnlocked(): void
    {
        $this->fillForm();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        $plan = $this->getRecord();
        if (! $plan instanceof Plan) {
            return 'Uso clínico';
        }

        $name = filled($plan->description) ? (string) $plan->description : (string) $plan->code;

        return 'Uso clínico · '.$name;
    }

    public function getHeading(): string|Htmlable
    {
        $plan = $this->currentPlan();
        if (! $plan instanceof Plan) {
            return 'Uso clínico';
        }

        $total = count(PlanClinicalCompleteness::assignedBenefitIds($plan));
        $missing = count(PlanClinicalCompleteness::missingBenefitIds($plan));

        return new HtmlString(view('filament.business.resources.plans.partials.clinical-usage-header', [
            'name' => PlanClinicalUsageWorkbench::planName($plan),
            'code' => filled($plan->code) ? (string) $plan->code : null,
            'isPackage' => $plan->pricingMode() === PlanPricingMode::Paquete,
            'inactive' => strtoupper((string) $plan->status) === 'INACTIVO',
            'total' => $total,
            'missing' => $missing,
            'complete' => $total > 0 && $missing === 0,
        ])->render());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    protected function fillForm(): void
    {
        $plan = $this->getRecord();
        if (! $plan instanceof Plan) {
            return;
        }

        $this->loadedFingerprint = PlanClinicalUsageWorkbench::fingerprint($plan);
        $this->copiedFromPlanId = null;
        $this->hasUnsavedWork = false;

        $this->form->fill([
            'clinical_rows' => PlanClinicalUsageWorkbench::hydrate($plan),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Avance')
                    ->hiddenLabel()
                    ->schema([
                        Placeholder::make('progress')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => new HtmlString(view(
                                'filament.business.resources.plans.partials.clinical-usage-progress',
                                [
                                    'summary' => PlanClinicalUsageWorkbench::summarize((array) ($get('clinical_rows') ?? [])),
                                    'hasUnsavedWork' => $this->hasUnsavedWork,
                                    'copiedFrom' => $this->copiedFromPlanName(),
                                ],
                            )->render())),
                    ])
                    ->columnSpanFull(),
                Section::make('Beneficio → servicio → cupo')
                    ->description('Una fila por beneficio. «Servicios Macro» es el select «Tipo de servicio» de la consulta; Medicamentos, Laboratorio, Imagenología y Especialista son las tildes. Apague «¿Aplica?» en beneficios que no se usan en consulta (funerario, muerte accidental…).')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->schema([
                        Repeater::make('clinical_rows')
                            ->hiddenLabel()
                            ->table(PlanClinicalUsageRowSchema::columns())
                            ->compact()
                            ->schema(PlanClinicalUsageRowSchema::fields(fn (): ?int => $this->clinicalUsageAccessRecordId()))
                            ->addable(false)
                            ->reorderable(false)
                            ->deletable()
                            ->deleteAction(fn (Action $action): Action => $action
                                ->tooltip('Quitar (solo filas nuevas aún sin guardar)')
                                ->visible(function (array $arguments, Repeater $component): bool {
                                    $row = $component->getRawItemState($arguments['item'] ?? '');

                                    return ($row['origin'] ?? PlanClinicalUsageWorkbench::ORIGIN_PLAN) !== PlanClinicalUsageWorkbench::ORIGIN_PLAN;
                                }))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->columns(1)->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent()
                ->visible(fn (): bool => $this->clinicalUsageIsUnlocked()),
        ]);
    }

    public function getFormContentComponent(): Form
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('manage-plan-clinical-usage-form')
            ->livewireSubmitHandler('save')
            ->footer([
                $this->getFormActionsContentComponent(),
            ]);
    }

    public function getFormActionsContentComponent(): Actions
    {
        return Actions::make($this->getFormActions())->fullWidth(false)->sticky();
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar uso clínico')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->submit('save')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
            Action::make('discard')
                ->label('Descartar cambios')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (): bool => $this->hasUnsavedWork)
                ->requiresConfirmation()
                ->modalHeading('¿Descartar los cambios sin guardar?')
                ->modalDescription('La pantalla vuelve a lo que está guardado. La copia y los beneficios agregados que no guardó se pierden.')
                ->modalSubmitActionLabel('Sí, descartar')
                ->action(function (): void {
                    $this->assertClinicalUsageUnlocked();
                    $this->fillForm();

                    Notification::make()
                        ->title('Cambios descartados')
                        ->body('La pantalla muestra lo que está guardado.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function copyFromPlanAction(): Action
    {
        return Action::make('copyFromPlan')
            ->label('Copiar de otro plan')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('primary')
            ->modalHeading('Copiar uso clínico de otro plan')
            ->modalDescription('Elija un plan con el uso clínico completo. Verá exactamente qué cambia antes de aplicar. Nada se guarda hasta que pulse «Guardar uso clínico».')
            ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Aplicar copia')
            ->schema([
                Select::make('source_plan_id')
                    ->label('Plan de origen')
                    ->placeholder('Buscar plan…')
                    ->options(fn (): array => $this->currentPlan() instanceof Plan
                        ? PlanClinicalUsageWorkbench::copySourceOptions($this->currentPlan())
                        : [])
                    ->searchable()
                    ->required()
                    ->live()
                    ->helperText('Solo aparecen los planes que tienen el uso clínico completo.')
                    ->noSearchResultsMessage('Ningún plan con uso clínico completo coincide.'),
                Radio::make('mode')
                    ->label('¿Qué hacer con lo que este plan ya tiene?')
                    ->options([
                        PlanClinicalUsageWorkbench::COPY_FILL_PENDING => 'Completar solo los pendientes (recomendado)',
                        PlanClinicalUsageWorkbench::COPY_OVERWRITE => 'Reemplazar todo lo que coincida',
                    ])
                    ->descriptions([
                        PlanClinicalUsageWorkbench::COPY_FILL_PENDING => 'Lo que ya está listo en este plan no se toca.',
                        PlanClinicalUsageWorkbench::COPY_OVERWRITE => 'Los beneficios que también tiene el plan de origen quedan iguales a él.',
                    ])
                    ->default(PlanClinicalUsageWorkbench::COPY_FILL_PENDING)
                    ->required()
                    ->live()
                    ->visible(fn (Get $get): bool => filled($get('source_plan_id'))),
                Placeholder::make('preview')
                    ->label('Vista previa')
                    ->visible(fn (Get $get): bool => filled($get('source_plan_id')))
                    ->content(function (Get $get): HtmlString {
                        $preview = $this->copyPreview($get('source_plan_id'), $get('mode'));

                        return new HtmlString($preview === null ? '' : view(
                            'filament.business.resources.plans.partials.clinical-usage-copy-preview',
                            ['preview' => $preview],
                        )->render());
                    }),
                CheckboxList::make('extra_benefit_ids')
                    ->label('Beneficios que tiene el plan de origen y este plan no')
                    ->helperText(fn (): string => 'Marque los que quiera agregar a este plan con la misma configuración. Se vinculan al guardar.'.$this->linkImpactNote())
                    ->options(fn (Get $get): array => $this->copyPreview($get('source_plan_id'), $get('mode'))['extra'] ?? [])
                    ->visible(fn (Get $get): bool => ($this->copyPreview($get('source_plan_id'), $get('mode'))['extra'] ?? []) !== [])
                    ->bulkToggleable()
                    ->columns(1),
            ])
            ->action(function (array $data): void {
                $this->assertClinicalUsageUnlocked();
                $this->applyCopy($data);
            });
    }

    protected function addBenefitAction(): Action
    {
        return Action::make('addBenefit')
            ->label('Agregar beneficio')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('gray')
            ->modalHeading('Agregar beneficio al plan')
            ->modalDescription(fn (): string => 'Aparecerá como una fila nueva para que configure su uso clínico. Se vincula al plan cuando pulse «Guardar uso clínico».'.$this->linkImpactNote())
            ->modalIcon(Heroicon::OutlinedPlusCircle)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Agregar a la tabla')
            ->schema([
                ToggleButtons::make('source')
                    ->label('¿De dónde sale el beneficio?')
                    ->options([
                        'catalog' => 'Ya existe en el catálogo',
                        'new' => 'Es nuevo: crearlo',
                    ])
                    ->icons([
                        'catalog' => Heroicon::OutlinedRectangleStack,
                        'new' => Heroicon::OutlinedSparkles,
                    ])
                    ->default('catalog')
                    ->inline()
                    ->required()
                    ->live(),
                Select::make('benefit_ids')
                    ->label('Beneficios del catálogo')
                    ->placeholder('Buscar por nombre o código…')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => PlanClinicalUsageWorkbench::catalogBenefitOptions($this->currentRows()))
                    ->helperText('Solo se muestran beneficios activos que este plan todavía no tiene. Si el catálogo trae una configuración clínica, se precarga.')
                    ->required(fn (Get $get): bool => $get('source') === 'catalog')
                    ->visible(fn (Get $get): bool => $get('source') === 'catalog'),
                TextInput::make('new_description')
                    ->label('Nombre del beneficio nuevo')
                    ->placeholder('Ej: ORIENTACIÓN NUTRICIONAL')
                    ->helperText('Se guarda en mayúsculas y recibe su código TDEC-BN automáticamente. Precio y comisiones se completan luego en el catálogo de beneficios.')
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => $get('source') === 'new')
                    ->visible(fn (Get $get): bool => $get('source') === 'new')
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        $existing = PlanClinicalUsageWorkbench::findCatalogBenefitByDescription((string) $value);
                        if ($existing instanceof Benefit) {
                            $fail('Ya existe en el catálogo como «'.trim((string) $existing->description).'». Elíjalo en «Ya existe en el catálogo».');

                            return;
                        }

                        $normalized = PlanClinicalUsageWorkbench::normalizeBenefitDescription((string) $value);
                        foreach ($this->currentRows() as $row) {
                            if (PlanClinicalUsageWorkbench::normalizeBenefitDescription((string) ($row['benefit_label'] ?? '')) === $normalized) {
                                $fail('Ese beneficio ya está en la tabla.');

                                return;
                            }
                        }
                    }),
            ])
            ->action(function (array $data): void {
                $this->assertClinicalUsageUnlocked();
                $this->addBenefits($data);
            });
    }

    protected function historyAction(): Action
    {
        return Action::make('history')
            ->label('Historial')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->slideOver()
            ->modalHeading('Historial del uso clínico')
            ->modalDescription('Quién cambió qué y cuándo en este plan. Los registros no se pueden editar ni borrar.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(function (): Htmlable {
                $plan = $this->currentPlan();
                $tableReady = PlanClinicalUsageTrace::tableExists();

                $events = $plan instanceof Plan && $tableReady
                    ? PlanClinicalUsageEvent::query()
                        ->where('plan_id', $plan->id)
                        ->latest('id')
                        ->limit(100)
                        ->get()
                    : collect();

                return view('filament.business.resources.plans.partials.clinical-usage-history', [
                    'events' => $events,
                    'tableReady' => $tableReady,
                ]);
            });
    }

    public function save(): void
    {
        $this->assertClinicalUsageUnlocked();

        $plan = $this->currentPlan();
        $user = Auth::user();
        if (! $plan instanceof Plan || ! $user instanceof User) {
            return;
        }

        $rows = (array) ($this->form->getState()['clinical_rows'] ?? []);

        try {
            $result = PlanClinicalUsageWorkbench::save($plan, $rows, $this->loadedFingerprint, $user, $this->copiedFromPlanId);
        } catch (PlanClinicalUsageConflictException $exception) {
            PlanClinicalUsageTrace::record(PlanClinicalUsageTrace::SAVE_REJECTED, (int) $plan->id, 'Guardado rechazado: otro usuario había guardado antes. No se escribió nada.', [], null, $user);

            Notification::make()
                ->title('Otro usuario guardó primero')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->actions([
                    Action::make('reload')
                        ->label('Recargar ahora')
                        ->button()
                        ->url(PlanResource::getUrl('uso-clinico', ['record' => $plan])),
                ])
                ->send();

            return;
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('No se pudo guardar el uso clínico')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('No se pudo guardar el uso clínico')
                ->body('No se guardó nada. Intente de nuevo; si se repite, avise a soporte con la hora del intento.')
                ->danger()
                ->send();

            return;
        }

        $complete = PlanClinicalCompleteness::isComplete($plan->fresh());

        $details = array_filter([
            $result['changed'] > 0 ? $result['changed'].' beneficio(s) con cambios' : null,
            $result['linked'] !== [] ? count($result['linked']).' vinculado(s) al plan' : null,
            $result['created'] !== [] ? count($result['created']).' creado(s) en el catálogo' : null,
        ]);

        Notification::make()
            ->title($complete ? 'Uso clínico completo y guardado' : 'Uso clínico guardado (aún incompleto)')
            ->body(($details !== [] ? ucfirst(implode(', ', $details)).'. ' : 'No había cambios. ')
                .($complete
                    ? 'El médico ya puede usar los servicios de este plan. Tarifas y cotizaciones no se tocaron.'
                    : 'Complete las filas en ámbar para que el médico vea los servicios de tipo 1.'))
            ->success()
            ->send();

        $this->fillForm();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyCopy(array $data): void
    {
        $plan = $this->currentPlan();
        $source = Plan::query()->find((int) ($data['source_plan_id'] ?? 0));

        if (! $plan instanceof Plan || ! $source instanceof Plan || (int) $source->id === (int) $plan->id
            || ! array_key_exists((int) $source->id, PlanClinicalUsageWorkbench::copySourceOptions($plan))) {
            Notification::make()
                ->title('Ese plan no se puede usar como origen')
                ->body('Elija un plan distinto con el uso clínico completo.')
                ->danger()
                ->send();

            return;
        }

        $mode = ($data['mode'] ?? null) === PlanClinicalUsageWorkbench::COPY_OVERWRITE
            ? PlanClinicalUsageWorkbench::COPY_OVERWRITE
            : PlanClinicalUsageWorkbench::COPY_FILL_PENDING;

        $result = PlanClinicalUsageWorkbench::applyCopy(
            $this->currentRows(),
            $source,
            $mode,
            array_values((array) ($data['extra_benefit_ids'] ?? [])),
        );

        if ($result['applied'] === [] && $result['added'] === []) {
            Notification::make()
                ->title('No había nada que copiar')
                ->body('Con la opción elegida, este plan ya coincide con '.PlanClinicalUsageWorkbench::planName($source).'.')
                ->warning()
                ->send();

            return;
        }

        $this->form->fill(['clinical_rows' => $result['rows']]);
        $this->copiedFromPlanId = (int) $source->id;
        $this->hasUnsavedWork = true;

        PlanClinicalUsageTrace::record(
            PlanClinicalUsageTrace::COPY_APPLIED,
            (int) $plan->id,
            'Copia de '.PlanClinicalUsageWorkbench::planName($source).' aplicada en pantalla ('
                .($mode === PlanClinicalUsageWorkbench::COPY_OVERWRITE ? 'reemplazando lo que coincide' : 'solo pendientes')
                .'): '.count($result['applied']).' fila(s) copiadas, '.count($result['added']).' beneficio(s) agregados. Pendiente de guardar.',
            ['mode' => $mode, 'applied' => $result['applied'], 'added' => $result['added'], 'matched_by_name' => $result['matched_by_name']],
            (int) $source->id,
        );

        Notification::make()
            ->title('Copia aplicada. Revise y guarde')
            ->body(count($result['applied']).' fila(s) copiadas'
                .($result['added'] !== [] ? ' y '.count($result['added']).' beneficio(s) agregados' : '')
                .'. Ajuste lo que haga falta y pulse «Guardar uso clínico».')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function addBenefits(array $data): void
    {
        $rows = $this->currentRows();
        $added = [];

        if (($data['source'] ?? 'catalog') === 'new') {
            $description = PlanClinicalUsageWorkbench::normalizeBenefitDescription((string) ($data['new_description'] ?? ''));
            if ($description === '' || PlanClinicalUsageWorkbench::findCatalogBenefitByDescription($description) instanceof Benefit) {
                return;
            }

            $rows[PlanClinicalUsageWorkbench::newKey()] = PlanClinicalUsageWorkbench::rowForNewBenefit($description);
            $added[] = $description;
        } else {
            $allowed = PlanClinicalUsageWorkbench::catalogBenefitOptions($rows);
            $ids = array_values(array_filter(
                array_map('intval', (array) ($data['benefit_ids'] ?? [])),
                static fn (int $id): bool => isset($allowed[$id]),
            ));

            foreach (Benefit::query()->with('clinicalSetting')->whereIn('id', $ids)->orderBy('description')->get() as $benefit) {
                $rows[PlanClinicalUsageWorkbench::newKey()] = PlanClinicalUsageWorkbench::rowForCatalogBenefit($benefit);
                $added[] = trim((string) $benefit->description);
            }
        }

        if ($added === []) {
            return;
        }

        $this->form->fill(['clinical_rows' => $rows]);
        $this->hasUnsavedWork = true;

        Notification::make()
            ->title(count($added) === 1 ? 'Beneficio agregado a la tabla' : count($added).' beneficios agregados a la tabla')
            ->body('Están al final, marcados como nuevos. Configure su uso clínico y pulse «Guardar uso clínico» para vincularlos al plan.')
            ->success()
            ->send();
    }

    /**
     * @return array{apply: list<array<string, mixed>>, keep: list<array<string, string>>, extra: array<int, string>, by_name: int}|null
     */
    protected function copyPreview(mixed $sourcePlanId, mixed $mode): ?array
    {
        $plan = $this->currentPlan();
        $source = filled($sourcePlanId) ? Plan::query()->find((int) $sourcePlanId) : null;

        if (! $plan instanceof Plan || ! $source instanceof Plan || (int) $source->id === (int) $plan->id) {
            return null;
        }

        return once(fn (): array => PlanClinicalUsageWorkbench::previewCopy(
            $this->currentRows(),
            $source,
            $mode === PlanClinicalUsageWorkbench::COPY_OVERWRITE ? PlanClinicalUsageWorkbench::COPY_OVERWRITE : PlanClinicalUsageWorkbench::COPY_FILL_PENDING,
        ));
    }

    /**
     * @return array<array-key, array<string, mixed>>
     */
    protected function currentRows(): array
    {
        return array_filter(
            (array) ($this->data['clinical_rows'] ?? []),
            static fn (mixed $row): bool => is_array($row),
        );
    }

    protected function currentPlan(): ?Plan
    {
        $plan = $this->getRecord();

        return $plan instanceof Plan ? $plan : null;
    }

    protected function copiedFromPlanName(): ?string
    {
        if ($this->copiedFromPlanId === null) {
            return null;
        }

        $source = Plan::query()->find($this->copiedFromPlanId);

        return $source instanceof Plan ? PlanClinicalUsageWorkbench::planName($source) : null;
    }

    /**
     * Qué pasa en la estructura comercial al vincular un beneficio, según el
     * modo de precio del plan.
     */
    protected function linkImpactNote(): string
    {
        $plan = $this->currentPlan();
        if (! $plan instanceof Plan) {
            return '';
        }

        if ($plan->pricingMode() === PlanPricingMode::Paquete) {
            return ' Entra al paquete del plan; las tarifas no cambian.';
        }

        $coverages = Coverage::query()->where('plan_id', $plan->id)->count();

        return ' Queda sin costo límite (sin límite) en '.$coverages.' cobertura(s) del plan; las tarifas no cambian. Si necesita un tope en USD, ajústelo luego en el asistente del plan.';
    }
}
