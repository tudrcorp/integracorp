<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Enums\ClinicalUsageAccessContext;
use App\Filament\Business\Concerns\InteractsWithClinicalUsageAccessGate;
use App\Filament\Business\Resources\Plans\PlanResource;
use App\Models\Plan;
use App\Support\ClinicalEntitlements\PlanBenefitClinicalFormSchema;
use App\Support\ClinicalEntitlements\PlanClinicalCompleteness;
use App\Support\ClinicalEntitlements\PlanClinicalStructurePersistence;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ManagePlanClinicalUsage extends Page
{
    use CanUseDatabaseTransactions;
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
        return $this->clinicalUsageAccessHeaderActions();
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

    public function getSubheading(): string|Htmlable|null
    {
        $plan = $this->getRecord();
        if (! $plan instanceof Plan) {
            return null;
        }

        if (PlanClinicalCompleteness::isComplete($plan)) {
            return 'Listo para telemedicina: cada beneficio del plan tiene servicio y cupo. Las tarifas no se modifican al guardar aquí.';
        }

        $missing = PlanClinicalCompleteness::missingBenefitLabels($plan);
        $assignedEmpty = PlanClinicalCompleteness::assignedBenefitIds($plan) === [];

        return 'Incompleto: el médico no verá servicios tipo 1 hasta que complete el mapa. Faltan: '.(
            $assignedEmpty ? 'asignar beneficios comerciales primero' : ($missing === [] ? 'completar servicio y cupo en cada fila' : implode(', ', $missing))
        );
    }

    protected function fillForm(): void
    {
        $plan = $this->getRecord();
        if (! $plan instanceof Plan) {
            return;
        }

        $this->form->fill([
            'clinical_rows' => PlanClinicalStructurePersistence::hydrate($plan),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mapa beneficio → servicio → cupo')
                    ->description('Esto no cambia cotizaciones ni tarifas. Solo define qué puede asignar el médico y cuántas veces. Un beneficio, un servicio. El médico no verá tipo 1 hasta que todas las filas estén completas.')
                    ->schema([
                        Repeater::make('clinical_rows')
                            ->label('Beneficios del plan')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => filled($state['benefit_label'] ?? null)
                                ? (string) $state['benefit_label']
                                : 'Beneficio')
                            ->schema([
                                Hidden::make('benefit_id'),
                                Hidden::make('benefit_label'),
                                ...PlanBenefitClinicalFormSchema::fields(),
                            ])
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
                ->submit('save')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }

    public function save(): void
    {
        $this->assertClinicalUsageUnlocked();

        $plan = $this->getRecord();
        if (! $plan instanceof Plan) {
            return;
        }

        try {
            $this->beginDatabaseTransaction();
            $rows = (array) ($this->form->getState()['clinical_rows'] ?? []);
            PlanClinicalStructurePersistence::persist($plan, $rows, Auth::user()?->name);
            $this->commitDatabaseTransaction();
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            Notification::make()
                ->title('No se pudo guardar el uso clínico')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $complete = PlanClinicalCompleteness::isComplete($plan->fresh());

        Notification::make()
            ->title($complete ? 'Uso clínico completo' : 'Uso clínico guardado (aún incompleto)')
            ->body($complete
                ? 'El médico ya puede ver los servicios tipo 1 de este plan. Cotizaciones y tarifas no se tocaron.'
                : 'Revise las filas marcadas: cada beneficio que aplica en consulta necesita servicio y cupo.')
            ->success()
            ->send();

        $this->fillForm();
    }
}
