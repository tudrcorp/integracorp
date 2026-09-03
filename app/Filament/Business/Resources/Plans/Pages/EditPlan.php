<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Enums\ClinicalUsageAccessContext;
use App\Enums\PlanPricingMode;
use App\Filament\Business\Concerns\InteractsWithClinicalUsageAccessGate;
use App\Filament\Business\Resources\Plans\PlanResource;
use App\Filament\Business\Resources\Plans\Schemas\PlanForm;
use App\Filament\Business\Resources\Plans\Schemas\PlanWizardForm;
use App\Models\Plan;
use App\Support\ClinicalEntitlements\PlanClinicalStructurePersistence;
use App\Support\PlanCreationPersistence;
use App\Support\Plans\PlanQuotability;
use App\Support\Plans\PlanStructurePersistence;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

/**
 * Un plan armado con el asistente se reedita con el asistente. Los planes
 * históricos conservan el formulario anterior: su estructura alimenta
 * cotizaciones, afiliaciones y PDFs ya emitidos, y reescribirla con las reglas
 * nuevas al guardar cambiaría precios vigentes.
 */
class EditPlan extends EditRecord
{
    use InteractsWithClinicalUsageAccessGate;

    protected static string $resource = PlanResource::class;

    /** @var array<string, mixed> */
    protected array $pendingFormData = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->bootClinicalUsageAccessGate();
    }

    public function form(Schema $schema): Schema
    {
        return $this->usesWizard()
            ? PlanWizardForm::configure($schema)
            : PlanForm::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->clinicalUsageAccessHeaderActions(),
            Action::make('usoClinico')
                ->label('Uso clínico')
                ->url(fn (): string => PlanResource::getUrl('uso-clinico', ['record' => $this->getRecord()])),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function clinicalUsageAccessContext(): ClinicalUsageAccessContext
    {
        return ClinicalUsageAccessContext::PlanEdit;
    }

    protected function clinicalUsageAccessRequiresGate(): bool
    {
        return $this->usesWizard();
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        return $this->usesWizard()
            ? PlanClinicalStructurePersistence::mergeIntoFormData(
                $record,
                array_merge($data, PlanStructurePersistence::hydrate($record)),
            )
            : array_merge($data, PlanCreationPersistence::hydrateFormData($record));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingFormData = $data;

        unset($data['created_by']);

        if (! $this->usesWizard()) {
            return PlanCreationPersistence::preparePlanAttributes($data);
        }

        return [
            'description' => $data['description'] ?? $this->getRecord()->description,
            'business_unit_id' => $data['business_unit_id'] ?? $this->getRecord()->business_unit_id,
            'type' => $data['type'] ?? $this->getRecord()->type,
            ...PlanQuotability::attributesFromForm($data),
            'status' => $data['status'] ?? $this->getRecord()->status,
            'pricing_mode' => (PlanPricingMode::fromStored($data['pricing_mode'] ?? null) ?? $this->getRecord()->pricingMode())->value,
            'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($this->usesWizard()) {
            PlanStructurePersistence::persist($record, $this->pendingFormData);

            if ($this->clinicalUsageIsUnlocked()) {
                PlanClinicalStructurePersistence::persist(
                    $record,
                    PlanClinicalStructurePersistence::rowsFromPlanForm($this->pendingFormData),
                );
            }

            return;
        }

        PlanCreationPersistence::syncRelations($record, $this->pendingFormData);
    }

    private function usesWizard(): bool
    {
        $record = $this->getRecord();

        return $record instanceof Plan && $record->usesStructureWizard();
    }
}
