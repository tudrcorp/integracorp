<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Benefits\Pages;

use App\Enums\ClinicalUsageAccessContext;
use App\Filament\Business\Concerns\InteractsWithClinicalUsageAccessGate;
use App\Filament\Business\Resources\Benefits\BenefitResource;
use App\Models\Benefit;
use App\Models\BenefitClinicalSetting;
use App\Support\ClinicalEntitlements\PlanClinicalStructurePersistence;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditBenefit extends EditRecord
{
    use InteractsWithClinicalUsageAccessGate;

    protected static string $resource = BenefitResource::class;

    /** @var array<string, mixed> */
    protected array $pendingClinical = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->bootClinicalUsageAccessGate();
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->clinicalUsageAccessHeaderActions(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function clinicalUsageAccessContext(): ClinicalUsageAccessContext
    {
        return ClinicalUsageAccessContext::BenefitEdit;
    }

    protected function clinicalUsageAccessRecordId(): ?int
    {
        $benefit = $this->getRecord();

        return $benefit instanceof Benefit ? (int) $benefit->id : null;
    }

    protected function clinicalUsageAccessSubjectLabel(): ?string
    {
        $benefit = $this->getRecord();
        if (! $benefit instanceof Benefit) {
            return $this->clinicalUsageAccessContext()->label();
        }

        return 'Beneficio '.(filled($benefit->description) ? (string) $benefit->description : (string) $benefit->code);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $setting = BenefitClinicalSetting::query()
            ->where('benefit_id', $this->getRecord()->id)
            ->first();

        if ($setting === null) {
            return $data;
        }

        return array_merge($data, [
            'applies_clinically' => $setting->applies_clinically,
            'channel' => $setting->channel?->value ?? $setting->channel,
            'telemedicine_service_list_id' => $setting->telemedicine_service_list_id,
            'service_id' => $setting->service_id,
            'quota_scope' => $setting->quota_scope?->value ?? $setting->quota_scope,
            'quota' => $setting->quota,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function afterSave(): void
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
