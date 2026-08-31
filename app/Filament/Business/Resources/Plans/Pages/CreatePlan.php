<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Enums\ClinicalUsageAccessContext;
use App\Enums\PlanPricingMode;
use App\Filament\Business\Concerns\InteractsWithClinicalUsageAccessGate;
use App\Filament\Business\Resources\Plans\PlanResource;
use App\Filament\Business\Resources\Plans\Schemas\PlanWizardForm;
use App\Models\Plan;
use App\Support\ClinicalEntitlements\PlanClinicalStructurePersistence;
use App\Support\Plans\PlanCodeGenerator;
use App\Support\Plans\PlanStructurePersistence;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Los planes nuevos se arman con el asistente: coberturas -> beneficios y
 * costos límite -> rangos de edad y tarifas. Los planes históricos se siguen
 * editando con el formulario anterior (ver EditPlan).
 */
class CreatePlan extends CreateRecord
{
    use InteractsWithClinicalUsageAccessGate;

    protected static string $resource = PlanResource::class;

    /** @var array<string, mixed> */
    protected array $pendingFormData = [];

    public function mount(): void
    {
        parent::mount();
        $this->bootClinicalUsageAccessGate();
    }

    protected function getHeaderActions(): array
    {
        return $this->clinicalUsageAccessHeaderActions();
    }

    protected function clinicalUsageAccessContext(): ClinicalUsageAccessContext
    {
        return ClinicalUsageAccessContext::PlanCreate;
    }

    public function form(Schema $schema): Schema
    {
        return PlanWizardForm::configure($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingFormData = $data;

        return [
            'code' => filled($data['code'] ?? null) ? $data['code'] : PlanCodeGenerator::next(),
            'description' => $data['description'] ?? null,
            'business_unit_id' => $data['business_unit_id'] ?? 1,
            'type' => $data['type'] ?? 'BASICO',
            'status' => $data['status'] ?? 'ACTIVO',
            'created_by' => $data['created_by'] ?? (string) (Auth::user()?->name ?? 'sistema'),
            'pricing_mode' => (PlanPricingMode::fromStored($data['pricing_mode'] ?? null) ?? PlanPricingMode::Coberturas)->value,
            'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
        ];
    }

    protected function afterCreate(): void
    {
        PlanStructurePersistence::persist($this->getRecord(), $this->pendingFormData);

        if (! $this->clinicalUsageIsUnlocked()) {
            return;
        }

        PlanClinicalStructurePersistence::persist(
            $this->getRecord(),
            PlanClinicalStructurePersistence::rowsFromPlanForm($this->pendingFormData),
        );
    }
}
