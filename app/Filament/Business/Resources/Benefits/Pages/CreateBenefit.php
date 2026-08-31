<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Benefits\Pages;

use App\Enums\ClinicalUsageAccessContext;
use App\Filament\Business\Concerns\InteractsWithClinicalUsageAccessGate;
use App\Filament\Business\Resources\Benefits\BenefitResource;
use App\Support\ClinicalEntitlements\PlanClinicalStructurePersistence;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBenefit extends CreateRecord
{
    use InteractsWithClinicalUsageAccessGate;

    protected static string $resource = BenefitResource::class;

    /** @var array<string, mixed> */
    protected array $pendingClinical = [];

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
        return ClinicalUsageAccessContext::BenefitCreate;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingClinical = $data;

        unset(
            $data['applies_clinically'],
            $data['channel'],
            $data['telemedicine_service_list_id'],
            $data['service_id'],
            $data['quota_scope'],
            $data['quota'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->clinicalUsageIsUnlocked()) {
            return;
        }

        PlanClinicalStructurePersistence::persistBenefitDefault(
            (int) $this->getRecord()->id,
            $this->pendingClinical,
            Auth::user()?->name,
        );
    }
}
